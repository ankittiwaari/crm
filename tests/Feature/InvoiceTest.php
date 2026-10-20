<?php

use App\Enums\InvoiceStatus;
use App\Enums\TaxType;
use App\Models\Client;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-20 10:00:00');
    CompanyProfile::factory()->create(['state_code' => '29', 'gstin' => '29AAGCB7383J1Z4']);
});

/**
 * @return array<string, mixed>
 */
function invoicePayload(Client $client, array $overrides = []): array
{
    return [
        'client_id' => $client->id,
        'issue_date' => '2026-10-20',
        'due_date' => '2026-11-19',
        'items' => [
            ['description' => 'School ERP, Yearly subscription', 'sac_code' => '998314', 'quantity' => 1, 'unit_price' => '48,000', 'tax_rate' => '18', 'period_start' => '2026-10-27', 'period_end' => '2027-10-26'],
            ['description' => 'Biometric attendance integration', 'sac_code' => '998314', 'quantity' => 1, 'unit_price' => '6000', 'tax_rate' => '18'],
        ],
        ...$overrides,
    ];
}

test('guests are redirected to the login page', function () {
    $this->get(route('invoices.index'))->assertRedirect(route('login'));
});

test('a draft is saved with GST worked out from the client\'s state', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['state_code' => '29']);

    $this->actingAs($user)
        ->post(route('invoices.store'), invoicePayload($client))
        ->assertSessionHasNoErrors();

    $invoice = Invoice::sole();

    expect($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->number)->toBeNull()
        ->and($invoice->tax_type)->toBe(TaxType::IntraState)
        ->and($invoice->taxable_total)->toBe(5_400_000)
        ->and($invoice->cgst_total)->toBe(486_000)
        ->and($invoice->sgst_total)->toBe(486_000)
        ->and($invoice->total)->toBe(6_372_000)
        ->and($invoice->items)->toHaveCount(2)
        ->and($invoice->items[0]->period_end->toDateString())->toBe('2027-10-26');
});

test('a client in another state is charged IGST, using the state in their GSTIN', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['state_code' => '29', 'gstin' => '27AAPFU0939F1ZV']);

    $this->actingAs($user)->post(route('invoices.store'), invoicePayload($client));

    $invoice = Invoice::sole();

    expect($invoice->place_of_supply)->toBe('27')
        ->and($invoice->tax_type)->toBe(TaxType::InterState)
        ->and($invoice->igst_total)->toBe(972_000)
        ->and($invoice->cgst_total)->toBe(0);
});

test('a draft needs a client, at least one line and a due date on or after the invoice date', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->post(route('invoices.store'), invoicePayload($client, ['items' => [], 'due_date' => '2026-10-01']))
        ->assertSessionHasErrors([
            'items' => 'Add at least one line.',
            'due_date' => 'The due date cannot be before the invoice date.',
        ]);
});

test('invalid line amounts are rejected with clear messages', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->post(route('invoices.store'), invoicePayload($client, ['items' => [
            ['description' => '', 'sac_code' => '998314', 'quantity' => 1, 'unit_price' => 'abc', 'tax_rate' => '15'],
        ]]))
        ->assertSessionHasErrors([
            'items.0.description' => 'Describe what this line is for.',
            'items.0.unit_price' => 'Enter the rate in rupees, e.g. 48000.',
            'items.0.tax_rate' => 'Choose a GST rate: 0, 5, 12, 18 or 28%.',
        ]);
});

test('the new invoice page suggests the renewal of a subscription that is due', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'School ERP']);
    $subscription = Subscription::factory()->for($product)->renewingIn(6)->create();

    $this->actingAs($user)
        ->get(route('invoices.create', ['client' => $subscription->client_id, 'subscription' => $subscription->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoices/edit')
            ->where('form.items.0.subscription_id', $subscription->id)
            ->where('form.items.0.period_start', '2026-10-27')
            ->where('form.items.0.period_end', '2027-10-26')
            ->where('form.items.0.unit_price', '48000.00')
            ->where('form.items.0.is_renewal', true)
            ->where('form.due_date', '2026-11-19'));
});

test('a period that is already invoiced is not suggested again', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->renewingIn(6)->create();
    $client = $subscription->client;

    $this->actingAs($user)->post(route('invoices.store'), invoicePayload($client, ['items' => [[
        'subscription_id' => $subscription->id, 'description' => 'Renewal', 'sac_code' => '998314', 'quantity' => 1,
        'unit_price' => '48000', 'tax_rate' => '18', 'period_start' => '2026-10-27', 'period_end' => '2027-10-26',
    ]]]));

    $this->actingAs($user)
        ->get(route('invoices.create', ['client' => $client->id]))
        ->assertInertia(fn (Assert $page) => $page->has('suggestions', 0));
});

test('a draft can be edited and deleted, but an issued invoice cannot', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $this->actingAs($user)->post(route('invoices.store'), invoicePayload($client));
    $draft = Invoice::sole();

    $this->actingAs($user)
        ->put(route('invoices.update', $draft), invoicePayload($client, ['items' => [
            ['description' => 'Customisation', 'sac_code' => '998314', 'quantity' => 2, 'unit_price' => '5000', 'tax_rate' => '18'],
        ]]))
        ->assertRedirect(route('invoices.show', $draft));

    expect($draft->refresh()->taxable_total)->toBe(1_000_000)
        ->and($draft->items)->toHaveCount(1);

    $issued = Invoice::factory()->create();

    $this->actingAs($user)->put(route('invoices.update', $issued), invoicePayload($client))->assertStatus(409);
    $this->actingAs($user)->delete(route('invoices.destroy', $issued))->assertStatus(409);

    $this->actingAs($user)->delete(route('invoices.destroy', $draft))->assertRedirect(route('invoices.index'));
    $this->assertModelMissing($draft);
});

test('the invoice list filters unpaid and overdue invoices and totals what is owed', function () {
    $user = User::factory()->create();
    Invoice::factory()->create(['number' => 'INV/2026-27/0001']);
    Invoice::factory()->overdueBy(10)->create(['number' => 'INV/2026-27/0002']);
    Invoice::factory()->create(['number' => 'INV/2026-27/0003', 'status' => InvoiceStatus::Paid, 'amount_settled' => 6_372_000]);

    $this->actingAs($user)
        ->get(route('invoices.index', ['filter' => 'overdue']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoices/index')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.number', 'INV/2026-27/0002')
            ->where('invoices.data.0.is_overdue', true)
            ->where('summary.outstanding', 2 * 6_372_000)
            ->where('summary.overdue', 6_372_000)
            ->where('summary.overdue_count', 1));
});
