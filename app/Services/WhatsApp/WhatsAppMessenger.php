<?php

namespace App\Services\WhatsApp;

use App\Enums\MessageChannel;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Client;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Invoices\InvoiceMessages;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Queues WhatsApp template messages to clients through the Cloud API. Only contacts who agreed
 * to WhatsApp messages are messaged, and every message is logged against the client.
 */
class WhatsAppMessenger
{
    public function __construct(private InvoiceMessages $links) {}

    /**
     * Send an invoice (or a reminder for it) with the PDF attached.
     */
    public function invoice(Invoice $invoice, Contact $contact, string $type = WhatsAppTemplates::INVOICE_ISSUED, ?User $user = null): Message
    {
        if ($invoice->isDraft()) {
            throw new WhatsAppException('Issue the invoice before sending it.');
        }

        $values = [
            $contact->name,
            (string) $invoice->number,
            Money::format($type === WhatsAppTemplates::PAYMENT_REMINDER ? $invoice->balanceDue() : $invoice->total),
            $invoice->due_date->format('d M Y'),
            $this->businessName($invoice->seller_snapshot),
        ];

        $header = [
            'type' => 'header',
            'parameters' => [[
                'type' => 'document',
                'document' => ['link' => $this->links->publicPdfUrl($invoice), 'filename' => $invoice->fileName()],
            ]],
        ];

        return $this->queue($invoice->client, $contact, $type, $invoice, $values, [$header], $user);
    }

    public function renewal(Subscription $subscription, Contact $contact, ?User $user = null): Message
    {
        $renewsOn = $subscription->current_period_end?->addDay();

        $values = [
            $contact->name,
            $subscription->product->name,
            $renewsOn?->format('d M Y') ?? '',
            Money::format($subscription->amountPerCycle()),
            $this->businessName(),
        ];

        return $this->queue($subscription->client, $contact, WhatsAppTemplates::RENEWAL_DUE, $subscription, $values, [], $user);
    }

    public function paymentReceived(Payment $payment, Contact $contact, ?User $user = null): Message
    {
        $invoice = $payment->invoice;

        $values = [
            $contact->name,
            Money::format($payment->amount),
            (string) $invoice->number,
            $this->businessName($invoice->seller_snapshot),
        ];

        return $this->queue($invoice->client, $contact, WhatsAppTemplates::PAYMENT_RECEIVED, $invoice, $values, [], $user);
    }

    /**
     * Why a contact cannot be messaged automatically, or null when they can.
     */
    public function blocker(Contact $contact, string $type): ?string
    {
        $profile = CompanyProfile::current();

        return match (true) {
            ! $profile->whatsappReady() => 'WhatsApp is not connected yet. Set it up in Settings › WhatsApp.',
            $profile->whatsappTemplate($type) === null => 'Choose the "'.WhatsAppTemplates::definitions()[$type]['label'].'" template in Settings › WhatsApp.',
            $contact->whatsappTarget() === null => "{$contact->name} has no WhatsApp number.",
            $contact->whatsapp_opted_in_at === null => "{$contact->name} has not agreed to WhatsApp messages.",
            default => null,
        };
    }

    /**
     * @param  list<string>  $values
     * @param  list<array<string, mixed>>  $headerComponents
     */
    private function queue(Client $client, Contact $contact, string $type, Model $messageable, array $values, array $headerComponents, ?User $user): Message
    {
        if ($reason = $this->blocker($contact, $type)) {
            throw new WhatsAppException($reason);
        }

        $template = CompanyProfile::current()->whatsappTemplate($type);

        $message = new Message([
            'client_id' => $client->id,
            'contact_id' => $contact->id,
            'user_id' => $user?->id,
            'channel' => MessageChannel::WhatsAppApi,
            'direction' => 'outbound',
            'status' => 'queued',
            'template' => $type,
            'recipient' => (string) $contact->whatsappTarget(),
            'body' => WhatsAppTemplates::preview($type, $values),
            'payload' => [
                'template' => $template['name'] ?? $type,
                'language' => $template['language'] ?? 'en',
                'components' => [
                    ...$headerComponents,
                    [
                        'type' => 'body',
                        'parameters' => array_map(fn (string $value): array => ['type' => 'text', 'text' => $value], $values),
                    ],
                ],
            ],
        ]);
        $message->messageable()->associate($messageable);
        $message->save();

        SendWhatsAppMessage::dispatch($message)->afterCommit();

        return $message;
    }

    /**
     * @param  array<string, mixed>|null  $seller
     */
    private function businessName(?array $seller = null): string
    {
        $profile = CompanyProfile::current();

        return (string) ($seller['trade_name'] ?? $seller['legal_name'] ?? $profile->displayName() ?? config('app.name'));
    }
}
