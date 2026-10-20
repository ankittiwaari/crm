<?php

namespace App\Http\Controllers;

use App\Actions\Invoices\RecordPayment;
use App\Http\Requests\Invoices\PaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class InvoicePaymentController extends Controller
{
    public function store(PaymentRequest $request, Invoice $invoice, RecordPayment $recordPayment): RedirectResponse
    {
        $payment = $recordPayment->handle($invoice, $request->payment(), $request->user());

        $invoice->refresh();
        $message = $invoice->balanceDue() === 0
            ? __(':amount recorded. Invoice fully paid.', ['amount' => Money::format($payment->amount)])
            : __(':amount recorded. :balance still due.', ['amount' => Money::format($payment->amount), 'balance' => Money::format($invoice->balanceDue())]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('invoices.show', $invoice);
    }

    public function destroy(Payment $payment, RecordPayment $recordPayment): RedirectResponse
    {
        $recordPayment->delete($payment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment removed.')]);

        return to_route('invoices.show', $payment->invoice_id);
    }
}
