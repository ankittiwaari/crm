<?php

use App\Enums\ClientStatus;
use App\Enums\PricingModel;
use App\Enums\SubscriptionStatus;
use App\Models\Client;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-20 10:00:00');
});

/**
 * @return array<string, mixed>
 */
function subscriptionPayload(Product $product, array $overrides = []): array
{
    return [
        'product_id' => $product->id,
        'status' => 'active',
        'billing_cycle' => 'yearly',
        'pricing_model' => 'flat',
        'rate' => '48,000',
        'start_date' => '2023-10-27',
        'renewal_notice_days' => 30,
        'grace_days' => 15,
        ...$overrides,
    ];
}

test('guests are redirected to the login page', function () {
    $client = Client::factory()->create();

    $this->post(route('clients.subscriptions.store', $client), [])->assertRedirect(route('login'));
});

test('adding a product to an existing client works out the current period from the start date', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user)
        ->post(route('clients.subscriptions.store', $client), subscriptionPayload($product, ['unit_count' => 450]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('clients.show', $client));

    $subscription = $client->subscriptions()->sole();

    expect($subscription->rate)->toBe(4_800_000)
        ->and($subscription->quantity)->toBe(1)
        ->and($subscription->unit_count)->toBe(450)
        ->and($subscription->current_period_start->toDateString())->toBe('2025-10-27')
        ->and($subscription->current_period_end->toDateString())->toBe('2026-10-26');
});

test('the first rate is recorded as the initial deal in the rate history', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    $this->actingAs($user)->post(route('clients.subscriptions.store', $client), subscriptionPayload(Product::factory()->create()));

    $revision = $client->subscriptions()->sole()->rateRevisions()->sole();

    expect($revision->old_rate)->toBeNull()
        ->and($revision->new_rate)->toBe(4_800_000)
        ->and($revision->reason)->toBe('Initial deal')
        ->and($revision->effective_from->toDateString())->toBe('2023-10-27')
        ->and($revision->user_id)->toBe($user->id);
});

test('a given current period end overrides the worked-out period', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    $this->actingAs($user)->post(route('clients.subscriptions.store', $client), subscriptionPayload(
        Product::factory()->create(),
        ['current_period_end' => '2027-03-31'],
    ))->assertSessionHasNoErrors();

    $subscription = $client->subscriptions()->sole();

    expect($subscription->current_period_start->toDateString())->toBe('2026-04-01')
        ->and($subscription->current_period_end->toDateString())->toBe('2027-03-31');
});

test('a lead becomes an active client when a product is added', function () {
    $user = User::factory()->create();
    $client = Client::factory()->lead()->create();

    $this->actingAs($user)->post(route('clients.subscriptions.store', $client), subscriptionPayload(Product::factory()->create()));

    expect($client->refresh()->status)->toBe(ClientStatus::Active);
});

test('per-unit deals need a quantity and charge rate times quantity', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user)
        ->post(route('clients.subscriptions.store', $client), subscriptionPayload($product, ['pricing_model' => 'per_unit', 'rate' => '110']))
        ->assertSessionHasErrors(['quantity' => 'Enter how many units (e.g. students) are being charged for.']);

    $this->actingAs($user)
        ->post(route('clients.subscriptions.store', $client), subscriptionPayload($product, [
            'pricing_model' => 'per_unit', 'rate' => '110', 'quantity' => 450,
        ]))
        ->assertSessionHasNoErrors();

    expect($client->subscriptions()->sole()->amountPerCycle())->toBe(4_950_000);
});

test('flat deals always have a quantity of one', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    $this->actingAs($user)->post(route('clients.subscriptions.store', $client), subscriptionPayload(
        Product::factory()->create(),
        ['quantity' => 450],
    ));

    expect($client->subscriptions()->sole()->quantity)->toBe(1);
});

test('one-time charges have no renewal period', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    $this->actingAs($user)->post(route('clients.subscriptions.store', $client), subscriptionPayload(
        Product::factory()->create(),
        ['billing_cycle' => 'one_time', 'rate' => '15000'],
    ))->assertSessionHasNoErrors();

    $subscription = $client->subscriptions()->sole();

    expect($subscription->current_period_end)->toBeNull()
        ->and($subscription->annualValue())->toBe(0);
});

test('promo details are cleared when the deal is not promotional', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    $this->actingAs($user)->post(route('clients.subscriptions.store', $client), subscriptionPayload(
        Product::factory()->create(),
        ['is_promotional' => false, 'promo_ends_on' => '2027-03-31', 'promo_note' => 'Launch offer'],
    ));

    $subscription = $client->subscriptions()->sole();

    expect($subscription->promo_ends_on)->toBeNull()
        ->and($subscription->promo_note)->toBeNull();
});

test('a deleted product cannot be added', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $product = Product::factory()->create();
    $product->delete();

    $this->actingAs($user)
        ->post(route('clients.subscriptions.store', $client), subscriptionPayload($product))
        ->assertSessionHasErrors('product_id');
});

test('editing the terms does not change the rate', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->create();

    $this->actingAs($user)
        ->patch(route('subscriptions.update', $subscription), [
            ...subscriptionPayload($subscription->product, ['rate' => '1']),
            'status' => 'suspended',
            'start_date' => $subscription->start_date->toDateString(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('clients.show', $subscription->client_id));

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Suspended)
        ->and($subscription->rate)->toBe(4_800_000);
});

test('changing the billing cycle recalculates the current period', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->create(['start_date' => '2026-01-01']);

    $this->actingAs($user)
        ->patch(route('subscriptions.update', $subscription), [
            ...subscriptionPayload($subscription->product),
            'start_date' => '2026-01-01',
            'billing_cycle' => 'quarterly',
        ])
        ->assertSessionHasNoErrors();

    expect($subscription->refresh()->current_period_start->toDateString())->toBe('2026-10-01')
        ->and($subscription->current_period_end->toDateString())->toBe('2026-12-31');
});

test('a subscription added by mistake can be removed', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->create();

    $this->actingAs($user)
        ->delete(route('subscriptions.destroy', $subscription))
        ->assertRedirect(route('clients.show', $subscription->client_id));

    $this->assertSoftDeleted($subscription);
});

test('the client page shows each subscription with its amount, renewal countdown and rate history', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->renewingIn(7)->create();
    $subscription->rateRevisions()->create([
        'new_pricing_model' => PricingModel::Flat, 'new_rate' => 4_800_000, 'new_quantity' => 1,
        'effective_from' => '2025-10-27', 'reason' => 'Initial deal',
    ]);

    $this->actingAs($user)
        ->get(route('clients.show', $subscription->client_id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscriptions.0.amount_per_cycle', 4_800_000)
            ->where('subscriptions.0.annual_value', 4_800_000)
            ->where('subscriptions.0.days_until_renewal', 7)
            ->where('subscriptions.0.rate_revisions.0.reason', 'Initial deal')
            ->has('products'));
});
