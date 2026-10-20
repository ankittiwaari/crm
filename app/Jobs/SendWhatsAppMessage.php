<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends one queued WhatsApp message. Meta rejecting the message fails it straight away (retrying
 * would not help); network problems are retried.
 */
class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(public Message $message) {}

    public function handle(WhatsAppGateway $gateway): void
    {
        if ($this->message->status !== 'queued') {
            return;
        }

        $payload = (array) $this->message->payload;

        try {
            $id = $gateway->sendTemplate(
                $this->message->recipient,
                (string) ($payload['template'] ?? ''),
                (string) ($payload['language'] ?? 'en'),
                array_values(array_filter((array) ($payload['components'] ?? []), is_array(...))),
            );
        } catch (WhatsAppException $exception) {
            $this->markFailed($exception->getMessage());

            return;
        }

        $this->message->update([
            'status' => 'sent',
            'provider_message_id' => $id,
            'sent_at' => now(),
            'status_updated_at' => now(),
            'error' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed($exception?->getMessage() ?? 'Sending failed.');
    }

    private function markFailed(string $error): void
    {
        $this->message->update(['status' => 'failed', 'error' => $error, 'status_updated_at' => now()]);
    }
}
