<?php

use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo('2026-10-20 10:00:00');
    CompanyProfile::factory()->create();
    Sanctum::actingAs(User::factory()->create());
});

test('a renewal can be invoiced, issued, sent and paid from the app', function () {
    $subscription = Subscription::factory()->renewingIn(6)->create();
    $contact = Contact::factory()->for($subscription->client)->create(['phone' => '+919845012345']);

    $suggestion = $this->getJson(route('api.v1.clients.invoice-suggestions', $subscription->client_id))
        ->assertOk()
        ->json('data.0');

    expect($suggestion['period_start'])->toBe('2026-10-27')
        ->and($suggestion['unit_price'])->toBe(4_800_000);

    $invoiceId = $this->postJson(route('api.v1.invoices.store'), [
        'client_id' => $subscription->client_id,
        'issue_date' => '2026-10-20',
        'due_date' => '2026-11-19',
        'items' => [[
            ...collect($suggestion)->only(['subscription_id', 'product_id', 'description', 'sac_code', 'quantity', 'period_start', 'period_end'])->all(),
            'unit_price' => '48000',
            'tax_rate' => '18',
        ]],
    ])->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.total', 5_664_000)->json('data.id');

    $this->postJson(route('api.v1.invoices.finalise', $invoiceId))
        ->assertOk()
        ->assertJsonPath('data.number', 'INV/2026-27/0001')
        ->assertJsonPath('renews.0.status', 'pending_payment');

    $this->postJson(route('api.v1.invoices.whatsapp', $invoiceId), ['contact_id' => $contact->id])
        ->assertOk()
        ->assertJsonPath('data.url', fn (string $url) => str_starts_with($url, 'https://wa.me/919845012345?text='));

    $this->postJson(route('api.v1.invoices.payments.store', $invoiceId), [
        'amount' => '56640', 'paid_on' => '2026-10-20', 'method' => 'upi',
    ])->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('renews.0.status', 'paid');

    expect($subscription->refresh()->current_period_end->toDateString())->toBe('2027-10-26');
});

test('invoices are listed with filters and the amount owed', function () {
    Invoice::factory()->overdueBy(5)->create(['number' => 'INV/2026-27/0002']);
    Invoice::factory()->create(['number' => 'INV/2026-27/0003']);

    $this->getJson(route('api.v1.invoices.index', ['filter' => 'overdue']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.number', 'INV/2026-27/0002')
        ->assertJsonPath('meta.summary.overdue_count', 1);
});

test('the invoice PDF can be downloaded', function () {
    $invoice = Invoice::factory()->create(['number' => 'INV/2026-27/0001']);

    $this->get(route('api.v1.invoices.pdf', $invoice))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('issued invoices cannot be edited or deleted through the API', function () {
    $invoice = Invoice::factory()->create();

    $this->deleteJson(route('api.v1.invoices.destroy', $invoice))->assertStatus(409);
});

test('a subscription can be renewed manually and undone from the app', function () {
    $subscription = Subscription::factory()->renewingIn(6)->create();

    $this->postJson(route('api.v1.subscriptions.renewals', $subscription), ['reason' => 'Cheque received'])
        ->assertOk()
        ->assertJsonPath('data.current_period_end', '2027-10-26')
        ->assertJsonPath('data.periods.0.status', 'overridden');

    $this->deleteJson(route('api.v1.renewals.destroy', $subscription->periods()->sole()))
        ->assertOk()
        ->assertJsonPath('data.current_period_end', '2026-10-26');
});

test('rates can be changed from the app', function () {
    $subscription = Subscription::factory()->create();

    $this->postJson(route('api.v1.subscriptions.rate-changes', $subscription), [
        'pricing_model' => 'flat', 'rate' => '54000', 'effective_from' => '2026-10-27', 'reason' => 'Renewal increase',
    ])->assertOk()->assertJsonPath('data.rate', 5_400_000)->assertJsonPath('data.rate_revisions.0.reason', 'Renewal increase');
});
