<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Invoices\CancelInvoice;
use App\Actions\Invoices\FinaliseInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Actions\Invoices\SaveDraftInvoice;
use App\Enums\MessageChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\InvoiceRequest;
use App\Http\Requests\Invoices\PaymentRequest;
use App\Http\Resources\InvoiceResource;
use App\Mail\InvoiceMail;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SubscriptionPeriod;
use App\Queries\InvoiceList;
use App\Services\Invoices\InvoiceMessages;
use App\Services\Invoices\InvoicePdf;
use App\Services\Invoices\InvoiceSuggestions;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppMessenger;
use App\Services\WhatsApp\WhatsAppTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InvoiceController extends Controller
{
    /**
     * Invoices with ?filter=draft|unpaid|overdue|paid|cancelled, ?search= and ?client_id=; totals are in meta.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $list = new InvoiceList($request);

        return InvoiceResource::collection($list->paginate())
            ->additional(['meta' => ['summary' => InvoiceList::summary()]]);
    }

    /**
     * Lines suggested from a client's subscriptions, amounts in paise.
     */
    public function suggestions(Request $request, Client $client, InvoiceSuggestions $suggestions): JsonResponse
    {
        return response()->json(['data' => $suggestions->forClient($client, $request->integer('subscription_id') ?: null)]);
    }

    public function store(InvoiceRequest $request, SaveDraftInvoice $saveDraft): InvoiceResource
    {
        return $this->resource($saveDraft->handle($request->invoiceAttributes(), $request->items()));
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return $this->resource($invoice);
    }

    public function update(InvoiceRequest $request, Invoice $invoice, SaveDraftInvoice $saveDraft): InvoiceResource
    {
        abort_unless($invoice->isDraft(), 409, 'Issued invoices cannot be edited.');

        return $this->resource($saveDraft->handle($request->invoiceAttributes(), $request->items(), $invoice));
    }

    public function destroy(Invoice $invoice): Response
    {
        abort_unless($invoice->isDraft(), 409, 'Issued invoices cannot be deleted. Cancel them instead.');

        $invoice->delete();

        return response()->noContent();
    }

    public function finalise(Invoice $invoice, FinaliseInvoice $finalise): InvoiceResource
    {
        return $this->resource($finalise->handle($invoice));
    }

    public function cancel(Request $request, Invoice $invoice, CancelInvoice $cancel): InvoiceResource
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return $this->resource($cancel->handle($invoice, $validated['reason']));
    }

    public function pdf(Invoice $invoice, InvoicePdf $pdf): HttpResponse
    {
        return response($pdf->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$invoice->fileName().'"',
        ]);
    }

    /**
     * Log the message and return a WhatsApp click-to-chat link for the app to open.
     */
    public function whatsapp(Request $request, Invoice $invoice, InvoiceMessages $messages): JsonResponse
    {
        abort_if($invoice->isDraft(), 409, 'Issue the invoice before sending it.');

        $contact = Contact::query()->whereBelongsTo($invoice->client)->findOrFail($request->integer('contact_id'));
        $number = $contact->whatsappTarget() ?? throw ValidationException::withMessages(['contact_id' => 'This contact has no WhatsApp number.']);
        $text = $messages->whatsappText($invoice, $contact);

        $invoice->messages()->create([
            'client_id' => $invoice->client_id,
            'contact_id' => $contact->id,
            'user_id' => $request->user()?->id,
            'channel' => MessageChannel::WhatsAppLink,
            'template' => 'invoice_issued',
            'recipient' => $number,
            'body' => $text,
            'sent_at' => now(),
        ]);

        return response()->json(['data' => [
            'url' => 'https://wa.me/'.ltrim($number, '+').'?text='.rawurlencode($text),
            'text' => $text,
        ]]);
    }

    /**
     * Send through the WhatsApp Cloud API with the PDF attached (a payment reminder when overdue).
     */
    public function sendWhatsApp(Request $request, Invoice $invoice, WhatsAppMessenger $messenger): InvoiceResource
    {
        $contact = Contact::query()->whereBelongsTo($invoice->client)->findOrFail($request->integer('contact_id'));

        try {
            $messenger->invoice(
                $invoice,
                $contact,
                $invoice->isOverdue() ? WhatsAppTemplates::PAYMENT_REMINDER : WhatsAppTemplates::INVOICE_ISSUED,
                $request->user(),
            );
        } catch (WhatsAppException $exception) {
            throw ValidationException::withMessages(['contact_id' => $exception->getMessage()]);
        }

        return $this->resource($invoice);
    }

    public function email(Request $request, Invoice $invoice): InvoiceResource
    {
        abort_if($invoice->isDraft(), 409, 'Issue the invoice before sending it.');

        $contact = $request->integer('contact_id')
            ? Contact::query()->whereBelongsTo($invoice->client)->find($request->integer('contact_id'))
            : null;
        $email = $contact->email ?? $invoice->client->email
            ?? throw ValidationException::withMessages(['email' => 'Add an email address for this client or contact first.']);

        Mail::to($email, $contact?->name)->queue(new InvoiceMail($invoice, $contact?->name));

        $invoice->messages()->create([
            'client_id' => $invoice->client_id,
            'contact_id' => $contact?->id,
            'user_id' => $request->user()?->id,
            'channel' => MessageChannel::Email,
            'template' => 'invoice_issued',
            'recipient' => $email,
            'sent_at' => now(),
        ]);

        return $this->resource($invoice);
    }

    public function recordPayment(PaymentRequest $request, Invoice $invoice, RecordPayment $recordPayment): InvoiceResource
    {
        $recordPayment->handle($invoice, $request->payment(), $request->user());

        return $this->resource($invoice->refresh());
    }

    public function deletePayment(Payment $payment, RecordPayment $recordPayment): InvoiceResource
    {
        $recordPayment->delete($payment);

        return $this->resource($payment->invoice->refresh());
    }

    private function resource(Invoice $invoice): InvoiceResource
    {
        $invoice->load([
            'items',
            'client:id,name',
            'payments' => fn ($query) => $query->latest('paid_on')->latest('id'),
            'messages' => fn ($query) => $query->latest('sent_at'),
        ]);

        return InvoiceResource::make($invoice)->additional([
            'renews' => SubscriptionPeriod::query()
                ->whereBelongsTo($invoice)
                ->with('subscription.product')
                ->get()
                ->map(fn (SubscriptionPeriod $period): array => [
                    'subscription_id' => $period->subscription_id,
                    'product_name' => $period->subscription->product->name,
                    'period_end' => $period->period_end->toDateString(),
                    'status' => $period->status->value,
                ]),
        ]);
    }
}
