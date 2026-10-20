<?php

namespace App\Actions\Invoices;

use App\Actions\Renewals\RenewalLedger;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\Invoices\InvoiceNumberGenerator;
use App\Services\Invoices\InvoiceParties;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinaliseInvoice
{
    public function __construct(
        private SaveDraftInvoice $saveDraft,
        private InvoiceNumberGenerator $numbers,
        private InvoiceParties $parties,
        private RenewalLedger $ledger,
    ) {}

    /**
     * Issue a draft: recalculate it against current details, freeze the seller and client
     * details, and give it the next number for its financial year.
     */
    public function handle(Invoice $invoice): Invoice
    {
        if (! $invoice->isDraft()) {
            throw ValidationException::withMessages(['invoice' => 'This invoice has already been issued.']);
        }

        $profile = CompanyProfile::current();
        $missing = $profile->missingInvoiceDetails();

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'invoice' => 'Complete your Business Profile before issuing invoices. Missing: '.implode(', ', $missing).'.',
            ]);
        }

        if (! $invoice->items()->exists()) {
            throw ValidationException::withMessages(['invoice' => 'Add at least one line before issuing the invoice.']);
        }

        return DB::transaction(function () use ($invoice, $profile): Invoice {
            $items = array_values($invoice->items->map(fn (InvoiceItem $item): array => [
                ...$item->only([
                    'subscription_id', 'product_id', 'description', 'sac_code', 'quantity',
                    'unit_price', 'discount', 'tax_rate',
                ]),
                'period_start' => $item->period_start?->toDateString(),
                'period_end' => $item->period_end?->toDateString(),
            ])->all());

            $this->saveDraft->handle($invoice->only(['client_id', 'issue_date', 'due_date', 'notes', 'terms']), $items, $invoice);
            $invoice->unsetRelation('items');

            $invoice->forceFill([
                ...$this->numbers->next($invoice->issue_date, $profile->invoice_prefix),
                'status' => InvoiceStatus::Issued,
                'seller_snapshot' => $this->parties->seller($profile),
                'client_snapshot' => $this->parties->client(Client::withTrashed()->findOrFail($invoice->client_id)),
                'finalised_at' => now(),
            ])->save();

            $this->ledger->registerInvoice($invoice);

            return $invoice;
        });
    }
}
