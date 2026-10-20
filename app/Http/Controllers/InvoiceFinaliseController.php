<?php

namespace App\Http\Controllers;

use App\Actions\Invoices\FinaliseInvoice;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class InvoiceFinaliseController extends Controller
{
    public function __invoke(Invoice $invoice, FinaliseInvoice $finalise): RedirectResponse
    {
        $finalise->handle($invoice);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice :number issued.', ['number' => $invoice->number])]);

        return to_route('invoices.show', $invoice);
    }
}
