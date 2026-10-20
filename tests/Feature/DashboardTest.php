<?php

use App\Models\Client;
use App\Models\CompanyProfile;
use App\Models\Product;
use App\Models\Reminder;
use App\Models\Subscription;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard lists the business details invoices still need', function () {
    $user = User::factory()->create();
    CompanyProfile::factory()->create(['gstin' => null, 'upi_id' => null, 'bank_account_number' => null]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('businessProfile.missing', ['GSTIN', 'Bank or UPI details']));
});

test('the dashboard shows a complete business profile', function () {
    $user = User::factory()->create();
    CompanyProfile::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('businessProfile.gstin', '29AAGCB7383J1Z4')
            ->where('businessProfile.state', 'Karnataka')
            ->where('businessProfile.msme_category', 'Micro')
            ->where('businessProfile.missing', []));
});

test('the setup checklist knows when products and clients exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('setup.has_products', false)
            ->where('setup.has_clients', false));

    Product::factory()->create();
    Client::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('setup.has_products', true)
            ->where('setup.has_clients', true));
});

test('the dashboard totals recurring revenue and lists renewals and promos due soon', function () {
    $user = User::factory()->create();
    $sunrise = Client::factory()->create(['name' => 'Sunrise Public School']);
    Subscription::factory()->for($sunrise)->renewingIn(7)->create();
    Subscription::factory()->renewingIn(90)->create([
        'billing_cycle' => 'monthly', 'rate' => 500_000, 'is_promotional' => true, 'promo_ends_on' => today()->addDays(3),
    ]);
    Subscription::factory()->renewingIn(2)->create(['status' => 'cancelled']);
    $archived = Client::factory()->create();
    Subscription::factory()->for($archived)->renewingIn(4)->create();
    $archived->delete();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.annual_recurring', 4_800_000 + 12 * 500_000)
            ->where('metrics.live_subscriptions', 2)
            ->where('metrics.renewals_count', 1)
            ->where('metrics.renewals_value', 4_800_000)
            ->has('upcoming', 2)
            ->where('upcoming.0.kind', 'promo_ends')
            ->where('upcoming.1.kind', 'renewal')
            ->where('upcoming.1.client_name', 'Sunrise Public School'));
});

test('the dashboard shows reminders due today and clients in their grace period', function () {
    $user = User::factory()->create();
    Reminder::factory()->create(['title' => 'Call Sunrise']);
    Reminder::factory()->create(['due_on' => today()->addDay()]);
    Subscription::factory()->renewingIn(-5)->create(['grace_days' => 15]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('today', 1)
            ->where('today.0.title', 'Call Sunrise')
            ->where('upcoming.0.kind', 'in_grace'));
});
