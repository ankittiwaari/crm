<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Services\Invoices\InvoicePdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice, public ?string $recipientName = null) {}

    public function envelope(): Envelope
    {
        $seller = $this->invoice->seller_snapshot['trade_name'] ?? $this->invoice->seller_snapshot['legal_name'] ?? config('app.name');

        return new Envelope(subject: "Invoice {$this->invoice->number} from {$seller}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invoice');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => app(InvoicePdf::class)->render($this->invoice), $this->invoice->fileName())
                ->withMime('application/pdf'),
        ];
    }
}
