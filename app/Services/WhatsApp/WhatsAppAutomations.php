<?php

namespace App\Services\WhatsApp;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

/**
 * Automatic messages for clients with WhatsApp automation switched on: renewal coming up,
 * overdue invoice reminders and payment thank-yous. Anything that cannot be sent is skipped.
 */
class WhatsAppAutomations
{
    public function __construct(private WhatsAppMessenger $messenger) {}

    public function renewalDue(Subscription $subscription): void
    {
        $this->attempt($subscription->client, WhatsAppTemplates::RENEWAL_DUE, fn (Contact $contact) => $this->messenger->renewal($subscription, $contact));
    }

    public function invoiceOverdue(Invoice $invoice): void
    {
        $this->attempt($invoice->client, WhatsAppTemplates::PAYMENT_REMINDER, fn (Contact $contact) => $this->messenger->invoice($invoice, $contact, WhatsAppTemplates::PAYMENT_REMINDER));
    }

    public function paymentRecorded(Payment $payment): void
    {
        $this->attempt($payment->invoice->client, WhatsAppTemplates::PAYMENT_RECEIVED, fn (Contact $contact) => $this->messenger->paymentReceived($payment, $contact));
    }

    /**
     * @param  callable(Contact): mixed  $send
     */
    private function attempt(?Client $client, string $type, callable $send): void
    {
        if ($client === null || ! $client->whatsapp_automation) {
            return;
        }

        $contact = $client->billingContact();

        if ($contact === null || $this->messenger->blocker($contact, $type) !== null) {
            return;
        }

        try {
            $send($contact);
        } catch (WhatsAppException $exception) {
            Log::warning('Automatic WhatsApp message skipped.', ['client' => $client->id, 'type' => $type, 'reason' => $exception->getMessage()]);
        }
    }
}
