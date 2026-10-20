<?php

use App\Enums\InvoiceStatus;
use App\Enums\ReminderType;
use App\Enums\SubscriptionStatus;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\Reminder;
use App\Models\Subscription;

beforeEach(function () {
    $this->travelTo('2026-10-20 10:00:00');
    CompanyProfile::factory()->create();
});

test('a renewal reminder is created once when the renewal enters the notice window', function () {
    $due = Subscription::factory()->renewingIn(20)->create(['renewal_notice_days' => 30]);
    Subscription::factory()->renewingIn(60)->create(['renewal_notice_days' => 30]);

    $this->artisan('crm:daily-checks')->assertSuccessful();
    $this->artisan('crm:daily-checks')->assertSuccessful();

    $reminder = Reminder::query()->where('type', ReminderType::Renewal)->sole();

    expect($reminder->remindable->is($due))->toBeTrue()
        ->and($reminder->title)->toContain('renews on 10 Nov 2026')
        ->and($reminder->due_on->toDateString())->toBe('2026-10-20');
});

test('auto-renew subscriptions get one draft renewal invoice for the next period', function () {
    $subscription = Subscription::factory()->renewingIn(10)->create(['auto_renew' => true]);

    $this->artisan('crm:daily-checks');
    $this->artisan('crm:daily-checks');

    $invoice = Invoice::query()->with('items')->sole();

    expect($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->client_id)->toBe($subscription->client_id)
        ->and($invoice->items->sole()->period_start->toDateString())->toBe('2026-10-31');
});

test('subscriptions without auto-renew get no draft invoice', function () {
    Subscription::factory()->renewingIn(10)->create(['auto_renew' => false]);

    $this->artisan('crm:daily-checks');

    expect(Invoice::count())->toBe(0);
});

test('promos ending within 30 days get a review reminder', function () {
    Subscription::factory()->renewingIn(200)->create(['is_promotional' => true, 'promo_ends_on' => '2026-11-15']);
    Subscription::factory()->renewingIn(200)->create(['is_promotional' => true, 'promo_ends_on' => '2027-03-31']);

    $this->artisan('crm:daily-checks');

    expect(Reminder::query()->where('type', ReminderType::PromoReview)->sole()->title)->toContain('promo ends on 15 Nov 2026');
});

test('overdue invoices get a payment reminder, repeated weekly while unpaid', function () {
    Invoice::factory()->overdueBy(3)->create(['number' => 'INV/2026-27/0004']);
    Invoice::factory()->create();

    $this->artisan('crm:daily-checks');
    expect(Reminder::query()->where('type', ReminderType::PaymentFollowUp)->count())->toBe(1);

    $this->travel(2)->days();
    $this->artisan('crm:daily-checks');
    expect(Reminder::query()->where('type', ReminderType::PaymentFollowUp)->count())->toBe(1);

    $this->travel(5)->days();
    $this->artisan('crm:daily-checks');
    expect(Reminder::query()->where('type', ReminderType::PaymentFollowUp)->count())->toBe(2);
});

test('subscriptions are expired only after the grace period', function () {
    $inGrace = Subscription::factory()->renewingIn(-10)->create(['grace_days' => 15]);
    $lapsed = Subscription::factory()->renewingIn(-16)->create(['grace_days' => 15]);

    $this->artisan('crm:daily-checks');

    expect($inGrace->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($inGrace->renewalState())->toBe('in_grace')
        ->and($lapsed->refresh()->status)->toBe(SubscriptionStatus::Expired);
});
