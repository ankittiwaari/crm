<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\Reminder;
use App\Models\Subscription;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class DailyDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Reminder>  $reminders  Due today or earlier.
     * @param  Collection<int, Subscription>  $inGrace
     * @param  Collection<int, Invoice>  $overdue
     */
    public function __construct(
        public Collection $reminders,
        public Collection $inGrace,
        public Collection $overdue,
        public int $renewalsValue,
        public int $renewalsCount,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Today: '.$this->reminders->count().' to do, '.Money::format($this->overdue->sum(fn (Invoice $invoice) => $invoice->balanceDue())).' overdue')
            ->greeting('Good morning!');

        if ($this->reminders->isNotEmpty()) {
            $mail->line('**To do today**');

            foreach ($this->reminders as $reminder) {
                $mail->line('• '.$reminder->title.($reminder->due_on->lt(today()) ? ' _(since '.$reminder->due_on->format('d M').')_' : ''));
            }
        }

        if ($this->inGrace->isNotEmpty()) {
            $mail->line('**In grace period (renewal unpaid)**');

            foreach ($this->inGrace as $subscription) {
                $mail->line("• {$subscription->client->name}, {$subscription->product->name}: grace ends {$subscription->graceEndsOn()?->format('d M')}");
            }
        }

        if ($this->overdue->isNotEmpty()) {
            $mail->line('**Overdue invoices**');

            foreach ($this->overdue as $invoice) {
                $mail->line("• {$invoice->number}, ".($invoice->client_snapshot['name'] ?? '').': '.Money::format($invoice->balanceDue())." ({$invoice->daysOverdue()} days)");
            }
        }

        if ($this->renewalsCount > 0) {
            $mail->line("Renewals in the next 30 days: {$this->renewalsCount}, worth ".Money::format($this->renewalsValue).' + GST.');
        }

        return $mail->action('Open today', route('dashboard'));
    }

    public function hasContent(): bool
    {
        return $this->reminders->isNotEmpty() || $this->inGrace->isNotEmpty() || $this->overdue->isNotEmpty();
    }
}
