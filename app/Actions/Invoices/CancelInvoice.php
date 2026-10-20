<?php

namespace App\Actions\Invoices;

use App\Actions\Renewals\RenewalLedger;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

class CancelInvoice
{
    public function __construct(private RenewalLedger $ledger) {}

    /**
     * Cancel an issued invoice. Its number stays used, so the numbering has no gaps.
     */
    public function handle(Invoice $invoice, string $reason): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Issued || $invoice->payments()->exists()) {
            throw ValidationException::withMessages([
                'reason' => 'Only issued invoices without payments can be cancelled. Remove the payments first.',
            ]);
        }

        $invoice->forceFill([
            'status' => InvoiceStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ])->save();

        $this->ledger->syncInvoice($invoice);

        return $invoice;
    }
}
