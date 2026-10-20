<?php

use App\Enums\PricingModel;
use App\Models\Subscription;
use App\Models\User;

test('changing the rate keeps the old rate in the history', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->create();

    $this->actingAs($user)
        ->post(route('subscriptions.rate-changes.store', $subscription), [
            'pricing_model' => 'flat',
            'rate' => '54,000',
            'effective_from' => '2026-10-27',
            'reason' => 'Renewal increase',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('clients.show', $subscription->client_id));

    $revision = $subscription->rateRevisions()->sole();

    expect($subscription->refresh()->rate)->toBe(5_400_000)
        ->and($revision->old_rate)->toBe(4_800_000)
        ->and($revision->new_rate)->toBe(5_400_000)
        ->and($revision->reason)->toBe('Renewal increase')
        ->and($revision->user_id)->toBe($user->id);
});

test('a flat deal can move to per-unit pricing', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->create();

    $this->actingAs($user)->post(route('subscriptions.rate-changes.store', $subscription), [
        'pricing_model' => 'per_unit',
        'rate' => '120',
        'quantity' => 450,
        'effective_from' => '2026-10-27',
        'reason' => 'Promo ended',
    ])->assertSessionHasNoErrors();

    $subscription->refresh();

    expect($subscription->pricing_model)->toBe(PricingModel::PerUnit)
        ->and($subscription->amountPerCycle())->toBe(5_400_000)
        ->and($subscription->rateRevisions()->sole()->old_pricing_model)->toBe(PricingModel::Flat);
});

test('recording the same rate again is rejected', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->create();

    $this->actingAs($user)
        ->post(route('subscriptions.rate-changes.store', $subscription), [
            'pricing_model' => 'flat',
            'rate' => '48000',
            'effective_from' => '2026-10-27',
            'reason' => 'Renewal',
        ])
        ->assertSessionHasErrors(['rate' => 'This is the same as the current rate. Change the rate, quantity or pricing to record a revision.']);

    expect($subscription->rateRevisions()->count())->toBe(0);
});

test('a rate change needs a reason', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->create();

    $this->actingAs($user)
        ->post(route('subscriptions.rate-changes.store', $subscription), [
            'pricing_model' => 'flat',
            'rate' => '50000',
            'effective_from' => '2026-10-27',
            'reason' => '',
        ])
        ->assertSessionHasErrors(['reason' => 'Say why the rate changed, e.g. "Renewal increase" or "Added transport module".']);
});
