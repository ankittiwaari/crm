<?php

namespace App\Http\Controllers;

use App\Actions\Invoices\SaveDraftInvoice;
use App\Enums\PaymentMethod;
use App\Http\Requests\Invoices\InvoiceRequest;
use App\Http\Requests\Products\ProductRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Client;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SubscriptionPeriod;
use App\Queries\InvoiceList;
use App\Services\Invoices\InvoiceMessages;
use App\Services\Invoices\InvoiceParties;
use App\Services\Invoices\InvoiceSuggestions;
use App\Services\WhatsApp\WhatsAppMessenger;
use App\Services\WhatsApp\WhatsAppTemplates;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceParties $parties) {}

    public function index(Request $request): Response
    {
        $list = new InvoiceList($request);

        return Inertia::render('invoices/index', [
            'invoices' => InvoiceResource::collection($list->paginate()),
            'filters' => $list->filters(),
            'summary' => InvoiceList::summary(),
        ]);
    }

    public function create(Request $request, InvoiceSuggestions $suggestions): Response
    {
        $client = $request->integer('client') ? Client::query()->find($request->integer('client')) : null;
        $profile = CompanyProfile::current();

        $prefill = $client
            ? $suggestions->forClient($client, $request->integer('subscription') ?: null)
            : [];

        return $this->builder(null, $client, $suggestions, [
            'client_id' => $client?->id,
            'issue_date' => today()->toDateString(),
            'due_date' => today()->addDays($profile->payment_terms_days)->toDateString(),
            'notes' => $profile->invoice_notes,
            'terms' => $profile->invoice_terms,
            'items' => $request->integer('subscription') ? $this->rupeeLines($prefill) : [],
        ]);
    }

    public function store(InvoiceRequest $request, SaveDraftInvoice $saveDraft): RedirectResponse
    {
        $invoice = $saveDraft->handle($request->invoiceAttributes(), $request->items());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft saved. Review it, then issue it.')]);

        return to_route('invoices.show', $invoice);
    }

    public function show(Invoice $invoice, WhatsAppMessenger $whatsapp): Response
    {
        $invoice->load(['items', 'payments' => fn ($query) => $query->latest('paid_on')->latest('id'), 'messages' => fn ($query) => $query->latest('sent_at')]);
        $client = Client::withTrashed()->findOrFail($invoice->client_id);
        $client->load(['contacts' => fn ($query) => $query->orderByDesc('receives_invoices')->orderByDesc('is_primary')]);

        return Inertia::render('invoices/show', [
            'invoice' => InvoiceResource::make($invoice)->resolve(),
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'contacts' => $client->contacts->map(fn ($contact): array => [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'designation' => $contact->designation,
                    'email' => $contact->email,
                    'whatsapp_target' => $contact->whatsappTarget(),
                    'receives_invoices' => $contact->receives_invoices,
                    'whatsapp_api_blocker' => $whatsapp->blocker(
                        $contact,
                        $invoice->isOverdue() ? WhatsAppTemplates::PAYMENT_REMINDER : WhatsAppTemplates::INVOICE_ISSUED,
                    ),
                ])->all(),
            ],
            'renews' => SubscriptionPeriod::query()
                ->whereBelongsTo($invoice)
                ->with('subscription.product')
                ->get()
                ->map(fn (SubscriptionPeriod $period): array => [
                    'product_name' => $period->subscription->product->name,
                    'period_end' => $period->period_end->toDateString(),
                    'status' => $period->status->value,
                ]),
            'missingProfileDetails' => $invoice->isDraft() ? CompanyProfile::current()->missingInvoiceDetails() : [],
            'paymentMethods' => PaymentMethod::options(),
            'linkDays' => InvoiceMessages::LINK_DAYS,
            'whatsappApiReady' => CompanyProfile::current()->whatsappReady(),
        ]);
    }

    public function edit(Invoice $invoice, InvoiceSuggestions $suggestions): Response|RedirectResponse
    {
        if (! $invoice->isDraft()) {
            return to_route('invoices.show', $invoice);
        }

        $invoice->load('items');
        $resource = InvoiceResource::make($invoice)->resolve();

        return $this->builder($invoice, Client::query()->find($invoice->client_id), $suggestions, [
            'client_id' => $invoice->client_id,
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'notes' => $invoice->notes,
            'terms' => $invoice->terms,
            'items' => array_map(fn (array $item): array => [
                'subscription_id' => $item['subscription_id'],
                'product_id' => $item['product_id'],
                'description' => $item['description'],
                'sac_code' => $item['sac_code'],
                'period_start' => $item['period_start'] ?? '',
                'period_end' => $item['period_end'] ?? '',
                'quantity' => (string) $item['quantity'],
                'unit_price' => $item['unit_price_rupees'],
                'discount' => $item['discount_rupees'],
                'tax_rate' => $item['tax_rate'],
            ], $resource['items']),
        ]);
    }

    public function update(InvoiceRequest $request, Invoice $invoice, SaveDraftInvoice $saveDraft): RedirectResponse
    {
        abort_unless($invoice->isDraft(), 409, 'Issued invoices cannot be edited.');

        $saveDraft->handle($request->invoiceAttributes(), $request->items(), $invoice);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft updated.')]);

        return to_route('invoices.show', $invoice);
    }

    /**
     * Delete a draft. Issued invoices are cancelled instead, so numbers stay gapless.
     */
    public function destroy(Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->isDraft(), 409, 'Issued invoices cannot be deleted. Cancel them instead.');

        $invoice->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft deleted.')]);

        return to_route('invoices.index');
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function builder(?Invoice $invoice, ?Client $client, InvoiceSuggestions $suggestions, array $form): Response
    {
        $profile = CompanyProfile::current();
        $placeOfSupply = $client ? $this->parties->placeOfSupply($client, $profile) : null;

        return Inertia::render('invoices/edit', [
            'invoiceId' => $invoice?->id,
            'form' => $form,
            'client' => $client ? [
                'id' => $client->id,
                'name' => $client->name,
                'gstin' => $client->gstin,
                'state_name' => $client->state()?->label(),
                'city' => $client->city,
            ] : null,
            'taxType' => $this->parties->taxType($placeOfSupply, $profile)->value,
            'suggestions' => $client ? $this->rupeeLines($suggestions->forClient($client)) : [],
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'city']),
            'products' => Product::query()->active()->orderBy('name')->get()->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'sac_code' => $product->sac_code,
                'tax_rate' => (string) (float) $product->tax_rate,
                'list_price_rupees' => $product->list_price === null ? '' : Money::toRupees($product->list_price),
            ]),
            'taxRates' => ProductRequest::TAX_RATES,
            'defaultSacCode' => '998314',
            'paymentTermsDays' => $profile->payment_terms_days,
            'msmeMaxDays' => $profile->show_msme_clause ? CompanyProfile::MSME_MAX_PAYMENT_DAYS : null,
        ]);
    }

    /**
     * Suggested lines with amounts as rupee strings for the form.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function rupeeLines(array $lines): array
    {
        return array_map(fn (array $line): array => [
            ...$line,
            'quantity' => (string) $line['quantity'],
            'unit_price' => Money::toRupees((int) $line['unit_price']),
            'discount' => '',
            'tax_rate' => (string) (float) $line['tax_rate'],
            'period_start' => $line['period_start'] ?? '',
            'period_end' => $line['period_end'] ?? '',
        ], $lines);
    }
}
