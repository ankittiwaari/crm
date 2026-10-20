<?php

namespace App\Actions\Invoices;

use App\Actions\Renewals\RenewalLedger;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppAutomations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordPayment
{
    public function __construct(
        private RenewalLedger $ledger,
        private WhatsAppAutomations $whatsapp,
    ) {}

    /**
     * Record money received (and any TDS the client deducted) against an issued invoice.
     *
     * @param  array{amount: int, tds_amount?: int|null, paid_on: string, method: string, reference?: string|null, notes?: string|null}  $data
     */
    public function handle(Invoice $invoice, array $data, ?User $user = null): Payment
    {
        if (! $invoice->status->isOpen()) {
            throw ValidationException::withMessages(['amount' => 'Payments can only be recorded against unpaid invoices.']);
        }

        $settles = $data['amount'] + (int) ($data['tds_amount'] ?? 0);

        if ($settles > $invoice->balanceDue()) {
            throw ValidationException::withMessages([
                'amount' => 'Amount plus TDS is more than the balance due of ₹'.number_format($invoice->balanceDue() / 100, 2).'.',
            ]);
        }

        $payment = DB::transaction(function () use ($invoice, $data, $user): Payment {
            $payment = $invoice->payments()->create([
                ...$data,
                'tds_amount' => (int) ($data['tds_amount'] ?? 0),
                'user_id' => $user?->id,
            ]);

            $this->refresh($invoice);

            return $payment;
        });

        $this->whatsapp->paymentRecorded($payment);

        return $payment;
    }

    /**
     * Remove a payment recorded by mistake.
     */
    public function delete(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $payment->delete();

            $this->refresh($payment->invoice);
        });
    }

    /**
     * Recalculate how much is settled, move the invoice between unpaid, partly paid and paid,
     * and renew (or un-renew) the subscriptions it bills for.
     */
    private function refresh(Invoice $invoice): void
    {
        $settled = (int) $invoice->payments()->sum(DB::raw('amount + tds_amount'));
        $latest = $invoice->payments()->max('paid_on');

        $status = match (true) {
            $settled >= $invoice->total => InvoiceStatus::Paid,
            $settled > 0 => InvoiceStatus::PartiallyPaid,
            default => InvoiceStatus::Issued,
        };

        $invoice->forceFill([
            'amount_settled' => $settled,
            'status' => $status,
            'paid_at' => $status === InvoiceStatus::Paid ? $latest : null,
        ])->save();

        $this->ledger->syncInvoice($invoice);
    }
}
