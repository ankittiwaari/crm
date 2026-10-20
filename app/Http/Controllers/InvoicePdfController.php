<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Invoices\InvoicePdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfController extends Controller
{
    /**
     * The invoice PDF for you: shown in the browser, or downloaded with ?download=1.
     */
    public function show(Request $request, Invoice $invoice, InvoicePdf $pdf): Response
    {
        return $this->respond($invoice, $pdf, $request->boolean('download'));
    }

    /**
     * The invoice PDF for a client, through a signed, expiring link (no login needed).
     */
    public function public(Invoice $invoice, InvoicePdf $pdf): Response
    {
        abort_if($invoice->isDraft(), 404);

        return $this->respond($invoice, $pdf, false);
    }

    private function respond(Invoice $invoice, InvoicePdf $pdf, bool $download): Response
    {
        return response($pdf->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$invoice->fileName().'"',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
