<?php

namespace App\Actions\Renewals;

use App\Enums\InvoiceStatus;
use App\Enums\PeriodStatus;
use App\Enums\ReminderType;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Reminder;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Keeps subscriptions' renewal dates in step with invoices: a period moves a subscription forward
 * only when its invoice is fully paid, or when you renew it manually (an override).
 */
class RenewalLedger
{
    /**
     * Record the periods an issued invoice bills for, awaiting payment.
     */
    public function registerInvoice(Invoice $invoice): void
    {
        $items = $invoice->items()
            ->whereNotNull('subscription_id')
            ->whereNotNull('period_start')
            ->whereNotNull('period_end')
            ->get();

        foreach ($items as $item) {
            $this->registerItem($invoice, $item);
        }
    }

    /**
     * Bring the ledger in line with an invoice's payment status.
     */
    public function syncInvoice(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $periods = SubscriptionPeriod::query()->whereBelongsTo($invoice)->with('subscription')->get();

            foreach ($periods as $period) {
                match (true) {
                    $invoice->status === InvoiceStatus::Paid => $this->markPaid($period, $invoice),
                    $invoice->status === InvoiceStatus::Cancelled => $this->detachCancelled($period),
                    default => $this->markUnpaid($period),
                };
            }

            if ($invoice->status === InvoiceStatus::Paid) {
                $this->completeReminders(Reminder::query()->whereMorphedTo('remindable', $invoice));
            }
        });
    }

    /**
     * Renew the next period without (full) payment, e.g. a trusted client or a cheque in transit.
     * A waived period is a free extension that will not be invoiced.
     */
    public function override(Subscription $subscription, string $reason, bool $waive = false, ?User $user = null): SubscriptionPeriod
    {
        $next = $subscription->nextPeriod();

        if ($next === null || (! $subscription->status->isLive() && $subscription->status !== SubscriptionStatus::Expired)) {
            throw ValidationException::withMessages(['reason' => 'Only active recurring subscriptions can be renewed.']);
        }

        return DB::transaction(function () use ($subscription, $next, $reason, $waive, $user): SubscriptionPeriod {
            $period = $subscription->periods()->firstOrNew(['period_start' => $next[0]->toDateString()]);

            if ($period->exists && $period->status->isSettled()) {
                throw ValidationException::withMessages(['reason' => 'This period is already renewed.']);
            }

            $period->fill([
                'period_end' => $next[1],
                'status' => $waive ? PeriodStatus::Waived : PeriodStatus::Overridden,
                'settled_at' => now(),
                'override_reason' => $reason,
                'overridden_by' => $user?->id,
            ])->save();

            $this->advance($subscription, $period);

            return $period;
        });
    }

    /**
     * Undo a manual renewal while the period is still unpaid.
     */
    public function revert(SubscriptionPeriod $period): void
    {
        if (! in_array($period->status, [PeriodStatus::Overridden, PeriodStatus::Waived], true)) {
            throw ValidationException::withMessages(['period' => 'Only manual renewals can be undone. Paid periods change when payments do.']);
        }

        $subscription = $period->subscription;

        if (! $subscription->current_period_end?->equalTo($period->period_end)) {
            throw ValidationException::withMessages(['period' => 'A later period is already renewed. Undo that one first.']);
        }

        DB::transaction(function () use ($period, $subscription): void {
            if ($period->invoice_id !== null) {
                $period->forceFill([
                    'status' => PeriodStatus::PendingPayment,
                    'settled_at' => null,
                    'override_reason' => null,
                    'overridden_by' => null,
                ])->save();
            } else {
                $period->delete();
            }

            $this->retreat($subscription, $period);
        });
    }

    private function registerItem(Invoice $invoice, InvoiceItem $item): void
    {
        $subscription = Subscription::withTrashed()->find($item->subscription_id);

        if ($subscription === null || $subscription->billing_cycle->months() === null) {
            return;
        }

        $period = $subscription->periods()->firstOrNew(['period_start' => $item->period_start?->toDateString()]);

        if ($period->exists && $period->status === PeriodStatus::Paid) {
            return;
        }

        $period->period_end = $item->period_end ?? $period->period_end;
        $period->invoice_id = $invoice->id;
        $period->status ??= PeriodStatus::PendingPayment;
        $period->save();
    }

    private function markPaid(SubscriptionPeriod $period, Invoice $invoice): void
    {
        if ($period->status !== PeriodStatus::Paid) {
            $period->forceFill(['status' => PeriodStatus::Paid, 'settled_at' => $invoice->paid_at ?? now()])->save();
        }

        $this->advance($period->subscription, $period);
    }

    private function markUnpaid(SubscriptionPeriod $period): void
    {
        if ($period->status !== PeriodStatus::Paid) {
            return;
        }

        $period->forceFill(['status' => PeriodStatus::PendingPayment, 'settled_at' => null])->save();
        $this->retreat($period->subscription, $period);
    }

    private function detachCancelled(SubscriptionPeriod $period): void
    {
        if ($period->status === PeriodStatus::PendingPayment) {
            $period->delete();
        } else {
            $period->forceFill(['invoice_id' => null])->save();
        }
    }

    /**
     * Move the subscription's current period forward to a settled period.
     */
    private function advance(Subscription $subscription, SubscriptionPeriod $period): void
    {
        if ($subscription->current_period_end !== null && $period->period_end->lte($subscription->current_period_end)) {
            return;
        }

        $subscription->forceFill([
            'current_period_start' => $period->period_start,
            'current_period_end' => $period->period_end,
            'status' => $subscription->status === SubscriptionStatus::Expired ? SubscriptionStatus::Active : $subscription->status,
        ])->save();

        $this->completeReminders(Reminder::query()
            ->whereMorphedTo('remindable', $subscription)
            ->where('type', ReminderType::Renewal));
    }

    /**
     * Move the subscription back to the period before, when the latest one is no longer settled.
     */
    private function retreat(Subscription $subscription, SubscriptionPeriod $period): void
    {
        if (! $subscription->current_period_end?->equalTo($period->period_end)) {
            return;
        }

        $end = $period->period_start->subDay();

        $subscription->forceFill([
            'current_period_start' => $subscription->billing_cycle->periodStartFor($end),
            'current_period_end' => $end,
        ])->save();
    }

    /**
     * @param  Builder<Reminder>  $reminders
     */
    private function completeReminders(Builder $reminders): void
    {
        $reminders->pending()->update(['completed_at' => now()]);
    }
}
