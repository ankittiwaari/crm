<?php

namespace App\Actions\Renewals;

use App\Actions\Invoices\SaveDraftInvoice;
use App\Enums\ReminderType;
use App\Enums\SubscriptionStatus;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Reminder;
use App\Models\Subscription;
use App\Services\Invoices\InvoiceSuggestions;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The once-a-day sweep: reminders for renewals, ending promos and overdue invoices,
 * draft renewal invoices for auto-renew subscriptions, and expiring lapsed subscriptions.
 * Safe to run more than once a day: every reminder has a unique key.
 */
class RunDailyChecks
{
    /**
     * How far ahead to warn about promos ending.
     */
    public const int PROMO_NOTICE_DAYS = 30;

    public function __construct(
        private InvoiceSuggestions $suggestions,
        private SaveDraftInvoice $saveDraft,
    ) {}

    /**
     * @return array{renewal_reminders: int, draft_invoices: int, promo_reminders: int, payment_reminders: int, expired: int}
     */
    public function handle(): array
    {
        return [
            ...$this->renewals(),
            'promo_reminders' => $this->promos(),
            'payment_reminders' => $this->overdueInvoices(),
            'expired' => $this->expireLapsed(),
        ];
    }

    /**
     * @return array{renewal_reminders: int, draft_invoices: int}
     */
    private function renewals(): array
    {
        $reminders = 0;
        $drafts = 0;

        $subscriptions = Subscription::query()
            ->live()
            ->whereHas('client')
            ->whereNotNull('current_period_end')
            ->with(['client', 'product'])
            ->get()
            ->filter(fn (Subscription $subscription): bool => $subscription->current_period_end
                ->subDays($subscription->renewal_notice_days)
                ->lte(today()));

        foreach ($subscriptions as $subscription) {
            $renewsOn = $subscription->current_period_end->addDay();

            $reminders += $this->remind($subscription, "renewal:{$subscription->id}:{$renewsOn->toDateString()}", [
                'client_id' => $subscription->client_id,
                'type' => ReminderType::Renewal,
                'title' => "{$subscription->client->name}: {$subscription->product->name} renews on {$renewsOn->format('d M Y')} (".
                    Money::format($subscription->amountPerCycle()).' + GST)',
            ]);

            if ($subscription->auto_renew && $this->draftRenewalInvoice($subscription)) {
                $drafts++;
            }
        }

        return ['renewal_reminders' => $reminders, 'draft_invoices' => $drafts];
    }

    /**
     * Prepare a draft invoice for the next period unless one already exists.
     */
    private function draftRenewalInvoice(Subscription $subscription): bool
    {
        $next = $subscription->nextPeriod();

        if ($next === null || InvoiceItem::query()
            ->where('subscription_id', $subscription->id)
            ->whereDate('period_start', $next[0])
            ->whereHas('invoice', fn (Builder $query) => $query->where('status', '!=', 'cancelled'))
            ->exists()) {
            return false;
        }

        $lines = array_values(array_filter(
            $this->suggestions->forClient($subscription->client, $subscription->id),
            fn (array $line): bool => ($line['is_renewal'] ?? false) === true,
        ));

        if ($lines === []) {
            return false;
        }

        $profile = CompanyProfile::current();

        $this->saveDraft->handle([
            'client_id' => $subscription->client_id,
            'issue_date' => today()->toDateString(),
            'due_date' => today()->addDays($profile->payment_terms_days)->toDateString(),
            'notes' => $profile->invoice_notes,
            'terms' => $profile->invoice_terms,
        ], $lines);

        return true;
    }

    private function promos(): int
    {
        $created = 0;

        $subscriptions = Subscription::query()
            ->live()
            ->whereHas('client')
            ->where('is_promotional', true)
            ->whereBetween('promo_ends_on', [today(), today()->addDays(self::PROMO_NOTICE_DAYS)])
            ->with(['client', 'product'])
            ->get();

        foreach ($subscriptions as $subscription) {
            $created += $this->remind($subscription, "promo:{$subscription->id}:{$subscription->promo_ends_on?->toDateString()}", [
                'client_id' => $subscription->client_id,
                'type' => ReminderType::PromoReview,
                'title' => "{$subscription->client->name}: {$subscription->product->name} promo ends on {$subscription->promo_ends_on?->format('d M Y')}. Review the rate.",
            ]);
        }

        return $created;
    }

    /**
     * One reminder when an invoice becomes overdue, then another every week it stays unpaid.
     */
    private function overdueInvoices(): int
    {
        $created = 0;

        foreach (Invoice::query()->overdue()->with('client')->get() as $invoice) {
            $week = intdiv($invoice->daysOverdue(), 7);
            $name = $invoice->client_snapshot['name'] ?? $invoice->client->name;

            $created += $this->remind($invoice, "overdue:{$invoice->id}:{$week}", [
                'client_id' => $invoice->client_id,
                'type' => ReminderType::PaymentFollowUp,
                'title' => "{$name}: {$invoice->number} is {$invoice->daysOverdue()} days overdue (".Money::format($invoice->balanceDue()).')',
            ]);
        }

        return $created;
    }

    /**
     * Subscriptions past their grace period without renewal become expired.
     */
    private function expireLapsed(): int
    {
        return Subscription::query()
            ->live()
            ->whereNotNull('current_period_end')
            ->get()
            ->filter(fn (Subscription $subscription): bool => $subscription->renewalState() === 'lapsed')
            ->each(fn (Subscription $subscription) => $subscription->update(['status' => SubscriptionStatus::Expired]))
            ->count();
    }

    /**
     * Create the reminder unless one with this key already exists.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function remind(Model $remindable, string $key, array $attributes): int
    {
        $reminder = Reminder::query()->firstOrNew(['dedupe_key' => $key]);

        if ($reminder->exists) {
            return 0;
        }

        $reminder->fill([...$attributes, 'due_on' => today()]);
        $reminder->remindable()->associate($remindable);
        $reminder->save();

        return 1;
    }
}
