<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdf
{
    public function __construct(private InvoiceDocument $document) {}

    /**
     * Render the invoice as A4 PDF bytes. Only the characters used are embedded from the fonts,
     * which keeps the file small enough to share comfortably on WhatsApp.
     */
    public function render(Invoice $invoice): string
    {
        return Pdf::loadView('pdf.invoice', ['invoice' => $this->document->data($invoice)])
            ->setPaper('a4')
            ->setOption('enable_font_subsetting', true)
            ->output();
    }
}
