<?php

namespace App\Http\Controllers;

use App\Actions\Invoices\CancelInvoice;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceCancelController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice, CancelInvoice $cancel): RedirectResponse
    {
        $validated = $request->validate(
            ['reason' => ['required', 'string', 'max:255']],
            ['reason.required' => 'Say why the invoice is being cancelled.'],
        );

        $cancel->handle($invoice, $validated['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice :number cancelled.', ['number' => $invoice->number])]);

        return to_route('invoices.show', $invoice);
    }
}
