<?php

namespace App\Jobs;

use App\Actions\Clients\LogActivity;
use App\Enums\ActivityType;
use App\Enums\MessageChannel;
use App\Enums\ReminderType;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Reminder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class ProcessWhatsAppWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * Later statuses never move a message backwards (a late "delivered" after "read").
     */
    private const array STATUS_ORDER = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    public function handle(LogActivity $logActivity): void
    {
        foreach ((array) ($this->payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = (array) ($change['value'] ?? []);

                match ($change['field'] ?? null) {
                    'messages' => $this->messages($value, $logActivity),
                    'message_template_status_update' => $this->templateStatus($value),
                    default => null,
                };
            }
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function messages(array $value, LogActivity $logActivity): void
    {
        foreach ((array) ($value['statuses'] ?? []) as $status) {
            $this->status((array) $status);
        }

        $names = collect((array) ($value['contacts'] ?? []))
            ->mapWithKeys(fn ($contact): array => [(string) ($contact['wa_id'] ?? '') => (string) ($contact['profile']['name'] ?? '')]);

        foreach ((array) ($value['messages'] ?? []) as $incoming) {
            $this->incoming((array) $incoming, $names->all(), $logActivity);
        }
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function status(array $status): void
    {
        $message = Message::query()->where('provider_message_id', (string) ($status['id'] ?? ''))->first();
        $new = (string) ($status['status'] ?? '');

        if ($message === null || $new === '') {
            return;
        }

        if ($new === 'failed') {
            $error = $status['errors'][0] ?? [];
            $message->update([
                'status' => 'failed',
                'error' => trim(($error['title'] ?? 'Failed').'. '.($error['error_data']['details'] ?? $error['message'] ?? '')),
                'status_updated_at' => now(),
            ]);

            return;
        }

        if ((self::STATUS_ORDER[$new] ?? -1) > (self::STATUS_ORDER[$message->status] ?? -1)) {
            $message->update(['status' => $new, 'status_updated_at' => now()]);
        }
    }

    /**
     * A client wrote to you: log it on their timeline and remind you to reply.
     *
     * @param  array<string, mixed>  $incoming
     * @param  array<string, string>  $names
     */
    private function incoming(array $incoming, array $names, LogActivity $logActivity): void
    {
        $id = (string) ($incoming['id'] ?? '');
        $from = '+'.ltrim((string) ($incoming['from'] ?? ''), '+');

        if ($id === '' || Message::query()->where('provider_message_id', $id)->exists()) {
            return;
        }

        $text = $this->text($incoming);
        $contact = Contact::query()
            ->where(fn ($query) => $query->where('whatsapp_number', $from)->orWhere('phone', $from))
            ->whereHas('client')
            ->first();
        $sender = $contact->name ?? ($names[ltrim($from, '+')] ?? null) ?: $from;

        if ($contact !== null) {
            Message::query()->create([
                'client_id' => $contact->client_id,
                'contact_id' => $contact->id,
                'channel' => MessageChannel::WhatsAppApi,
                'direction' => 'inbound',
                'status' => 'received',
                'provider_message_id' => $id,
                'template' => 'inbound',
                'recipient' => $from,
                'body' => $text,
                'sent_at' => now(),
            ]);

            $logActivity->handle($contact->client, [
                'type' => ActivityType::WhatsApp,
                'body' => "{$contact->name} wrote: {$text}",
            ]);
        }

        Reminder::query()->firstOrCreate(['dedupe_key' => "whatsapp:{$id}"], [
            'client_id' => $contact?->client_id,
            'type' => ReminderType::FollowUp,
            'title' => 'Reply on WhatsApp to '.$sender.($contact ? '' : ' (not a saved contact)').': '.Str::limit($text, 80),
            'due_on' => today(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $incoming
     */
    private function text(array $incoming): string
    {
        return match ($incoming['type'] ?? null) {
            'text' => (string) ($incoming['text']['body'] ?? ''),
            'button' => (string) ($incoming['button']['text'] ?? ''),
            'interactive' => (string) ($incoming['interactive']['button_reply']['title'] ?? $incoming['interactive']['list_reply']['title'] ?? 'Replied'),
            'image', 'document', 'audio', 'video', 'sticker' => 'Sent a '.$incoming['type'].($incoming[$incoming['type']]['caption'] ?? null ? ': '.$incoming[$incoming['type']]['caption'] : ''),
            default => 'Sent a message',
        };
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function templateStatus(array $value): void
    {
        $name = (string) ($value['message_template_name'] ?? '');

        if ($name === '') {
            return;
        }

        $profile = CompanyProfile::current();
        $profile->forceFill([
            'whatsapp_template_statuses' => [...($profile->whatsapp_template_statuses ?? []), $name => (string) ($value['event'] ?? '')],
        ])->save();
    }
}
