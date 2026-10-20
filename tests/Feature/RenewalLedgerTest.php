<?php

use App\Enums\PeriodStatus;
use App\Enums\ReminderType;
use App\Enums\SubscriptionStatus;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\Reminder;
use App\Models\Subscription;
use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-10-20 10:00:00');
    CompanyProfile::factory()->create();
    $this->user = User::factory()->create();
});

/**
 * Issue a renewal invoice for the period after the subscription's current one.
 */
function issueRenewalInvoice(Subscription $subscription): Invoice
{
    [$start, $end] = $subscription->nextPeriod();

    test()->actingAs(test()->user)->post(route('invoices.store'), [
        'client_id' => $subscription->client_id,
        'issue_date' => today()->toDateString(),
        'due_date' => today()->addDays(30)->toDateString(),
        'items' => [[
            'subscription_id' => $subscription->id, 'description' => 'School ERP renewal', 'sac_code' => '998314',
            'quantity' => 1, 'unit_price' => '48000', 'tax_rate' => '18',
            'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
        ]],
    ]);
    $invoice = Invoice::query()->latest('id')->firstOrFail();
    test()->actingAs(test()->user)->post(route('invoices.finalise', $invoice));

    return $invoice->refresh();
}

function pay(Invoice $invoice, string $amount): void
{
    test()->actingAs(test()->user)->post(route('invoices.payments.store', $invoice), [
        'amount' => $amount, 'paid_on' => today()->toDateString(), 'method' => 'upi',
    ]);
}

test('issuing a renewal invoice records the period as awaiting payment without renewing yet', function () {
    $subscription = Subscription::factory()->renewingIn(6)->create();

    issueRenewalInvoice($subscription);

    $period = $subscription->periods()->sole();

    expect($period->status)->toBe(PeriodStatus::PendingPayment)
        ->and($period->period_start->toDateString())->toBe('2026-10-27')
        ->and($subscription->refresh()->current_period_end->toDateString())->toBe('2026-10-26');
});

test('paying the invoice in full renews the subscription', function () {
    $subscription = Subscription::factory()->renewingIn(6)->create();
    $invoice = issueRenewalInvoice($subscription);

    pay($invoice, '56640');

    $subscription->refresh();

    expect($subscription->current_period_start->toDateString())->toBe('2026-10-27')
        ->and($subscription->current_period_end->toDateString())->toBe('2027-10-26')
        ->and($subscription->periods()->sole()->status)->toBe(PeriodStatus::Paid);
});

test('a part payment does not renew the subscription', function () {
    $subscription = Subscription::factory()->renewingIn(6)->create();
    $invoice = issueRenewalInvoice($subscription);

    pay($invoice, '20000');

    expect($subscription->refresh()->current_period_end->toDateString())->toBe('2026-10-26')
        ->and($subscription->periods()->sole()->status)->toBe(PeriodStatus::PendingPayment);
});

test('removing the payment that settled the invoice moves the renewal back', function () {
    $subscription = Subscription::factory()->renewingIn(6)->create();
    $invoice = issueRenewalInvoice($subscription);
    pay($invoice, '56640');

    $this->actingAs($this->user)->delete(route('payments.destroy', $invoice->payments()->sole()));

    $subscription->refresh();

    expect($subscription->current_period_start->toDateString())->toBe('2025-10-27')
        ->and($subscription->current_period_end->toDateString())->toBe('2026-10-26')
        ->and($subscription->periods()->sole()->status)->toBe(PeriodStatus::PendingPayment);
});

test('cancelling an unpaid renewal invoice removes its pending period', function () {
    $subscription = Subscription::factory()->renewingIn(6)->create();
    $invoice = issueRenewalInvoice($subscription);

    $this->actingAs($this->user)->post(route('invoices.cancel', $invoice), ['reason' => 'Wrong rate']);

    expect($subscription->periods()->count())->toBe(0);
});

test('paying renews an expired subscription and reactivates it', function () {
    $subscription = Subscription::factory()->renewingIn(-30)->create(['status' => SubscriptionStatus::Expired]);
    $invoice = issueRenewalInvoice($subscription);

    pay($invoice, '56640');

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Active);
});

test('renewal reminders are completed once the subscription renews', function () {
    $subscription = Subscription::factory()->renewingIn(6)->create();
    $reminder = Reminder::factory()->create(['type' => ReminderType::Renewal]);
    $reminder->remindable()->associate($subscription)->save();
    $invoice = issueRenewalInvoice($subscription);

    pay($invoice, '56640');

    expect($reminder->refresh()->completed_at)->not->toBeNull();
});

test('marking renewed without payment needs a reason and advances one period', function () {
    $subscription = Subscription::factory()->renewingIn(6)->create();

    $this->actingAs($this->user)
        ->post(route('subscriptions.renewals.store', $subscription), ['reason' => ''])
        ->assertSessionHasErrors(['reason' => 'Say why you are renewing without payment, e.g. "Cheque received, not yet cleared".']);

    $this->actingAs($this->user)
        ->post(route('subscriptions.renewals.store', $subscription), ['reason' => 'Cheque received'])
        ->assertRedirect(route('clients.show', $subscription->client_id));

    $period = $subscription->periods()->sole();

    expect($subscription->refresh()->current_period_end->toDateString())->toBe('2027-10-26')
        ->and($period->status)->toBe(PeriodStatus::Overridden)
        ->and($period->override_reason)->toBe('Cheque received')
        ->and($period->overridden_by)->toBe($this->user->id);
});

test('a free extension is recorded as waived', function () {
    $subscription = Subscription::factory()->renewingIn(6)->create();

    $this->actingAs($this->user)->post(route('subscriptions.renewals.store', $subscription), ['reason' => 'Compensation for downtime', 'waive' => true]);

    expect($subscription->periods()->sole()->status)->toBe(PeriodStatus::Waived);
});

test('paying an invoice for a manually renewed period marks it paid without renewing twice', function () {
    $subscription = Subscription::factory()->renewingIn(6)->create();
    $invoice = issueRenewalInvoice($subscription);
    $this->actingAs($this->user)->post(route('subscriptions.renewals.store', $subscription), ['reason' => 'Cheque in transit']);

    pay($invoice, '56640');

    expect($subscription->refresh()->current_period_end->toDateString())->toBe('2027-10-26')
        ->and($subscription->periods()->sole()->status)->toBe(PeriodStatus::Paid);
});

test('a manual renewal can be undone, which also restores the pending invoice period', function () {
    $subscription = Subscription::factory()->renewingIn(6)->create();
    issueRenewalInvoice($subscription);
    $this->actingAs($this->user)->post(route('subscriptions.renewals.store', $subscription), ['reason' => 'Cheque in transit']);

    $this->actingAs($this->user)
        ->delete(route('renewals.destroy', $subscription->periods()->sole()))
        ->assertRedirect(route('clients.show', $subscription->client_id));

    expect($subscription->refresh()->current_period_end->toDateString())->toBe('2026-10-26')
        ->and($subscription->periods()->sole()->status)->toBe(PeriodStatus::PendingPayment);
});

test('only the latest manual renewal can be undone', function () {
    $subscription = Subscription::factory()->renewingIn(6)->create();
    $this->actingAs($this->user)->post(route('subscriptions.renewals.store', $subscription), ['reason' => 'First']);
    $this->actingAs($this->user)->post(route('subscriptions.renewals.store', $subscription->refresh()), ['reason' => 'Second']);
    $first = $subscription->periods()->oldest('period_start')->first();

    $this->actingAs($this->user)
        ->delete(route('renewals.destroy', $first))
        ->assertSessionHasErrors(['period' => 'A later period is already renewed. Undo that one first.']);
});

test('a paid period cannot be undone as a manual renewal', function () {
    $subscription = Subscription::factory()->renewingIn(6)->create();
    pay(issueRenewalInvoice($subscription), '56640');

    $this->actingAs($this->user)
        ->delete(route('renewals.destroy', $subscription->periods()->sole()))
        ->assertSessionHasErrors('period');
});
