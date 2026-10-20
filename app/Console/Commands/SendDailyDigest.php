<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Reminder;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\DailyDigest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('crm:daily-digest')]
#[Description('Email the morning summary: reminders due, clients in grace and overdue invoices')]
class SendDailyDigest extends Command
{
    public function handle(): int
    {
        $live = Subscription::query()->live()->whereHas('client')->with(['client', 'product'])->get();
        $renewals = $live->filter(fn (Subscription $subscription): bool => $subscription->current_period_end?->betweenIncluded(today(), today()->addDays(30)) ?? false);

        $digest = new DailyDigest(
            reminders: Reminder::query()->due()->orderBy('due_on')->get(),
            inGrace: $live->filter(fn (Subscription $subscription): bool => $subscription->renewalState() === 'in_grace')->values(),
            overdue: Invoice::query()->overdue()->orderBy('due_date')->get(),
            renewalsValue: $renewals->sum(fn (Subscription $subscription): int => $subscription->amountPerCycle()),
            renewalsCount: $renewals->count(),
        );

        if (! $digest->hasContent()) {
            $this->components->info('Nothing to report today. No email sent.');

            return self::SUCCESS;
        }

        Notification::send(User::query()->whereNotNull('email_verified_at')->get(), $digest);

        $this->components->info('Daily summary sent.');

        return self::SUCCESS;
    }
}
