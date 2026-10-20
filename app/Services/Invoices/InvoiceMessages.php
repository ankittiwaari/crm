<?php

namespace App\Services\Invoices;

use App\Models\Contact;
use App\Models\Invoice;
use App\Support\Money;
use Illuminate\Support\Facades\URL;

/**
 * Message text and links for sending an invoice to a client.
 */
class InvoiceMessages
{
    /**
     * How long the PDF link in a message keeps working.
     */
    public const int LINK_DAYS = 30;

    /**
     * A signed link that lets the client open the PDF without logging in.
     */
    public function publicPdfUrl(Invoice $invoice): string
    {
        return URL::temporarySignedRoute(
            'invoices.public-pdf',
            now()->addDays(self::LINK_DAYS),
            ['invoice' => $invoice->id],
        );
    }

    public function whatsappText(Invoice $invoice, ?Contact $contact): string
    {
        $seller = $invoice->seller_snapshot ?? [];
        $greeting = $contact ? "Dear {$contact->name}," : 'Hello,';
        $amount = Money::format($invoice->balanceDue());

        $lines = [
            $greeting,
            '',
            "Please find invoice {$invoice->number} for {$amount}, due by {$invoice->due_date->format('d M Y')}.",
            '',
            'View or download: '.$this->publicPdfUrl($invoice),
        ];

        if (filled($seller['upi_id'] ?? null)) {
            $lines[] = "Pay by UPI to {$seller['upi_id']} or scan the QR code on the invoice.";
        }

        $lines[] = '';
        $lines[] = 'Thank you,';
        $lines[] = $seller['trade_name'] ?? $seller['legal_name'] ?? '';

        return trim(implode("\n", $lines));
    }
}
