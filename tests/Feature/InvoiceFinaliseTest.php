<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-10-20 10:00:00');
});

function draftInvoice(?Client $client = null, string $issueDate = '2026-10-20'): Invoice
{
    $client ??= Client::factory()->create();

    test()->actingAs(User::factory()->create())->post(route('invoices.store'), [
        'client_id' => $client->id,
        'issue_date' => $issueDate,
        'due_date' => $issueDate,
        'items' => [['description' => 'School ERP', 'sac_code' => '998314', 'quantity' => 1, 'unit_price' => '48000', 'tax_rate' => '18']],
    ]);

    return Invoice::query()->latest('id')->firstOrFail();
}

test('issuing a draft gives it the next number for its financial year and freezes the details', function () {
    $user = User::factory()->create();
    CompanyProfile::factory()->create(['legal_name' => 'Acme Software', 'invoice_prefix' => 'INV']);
    $client = Client::factory()->create(['name' => 'Sunrise Public School']);
    $first = draftInvoice($client);
    $second = draftInvoice($client);

    $this->actingAs($user)->post(route('invoices.finalise', $first))->assertRedirect(route('invoices.show', $first));
    $this->actingAs($user)->post(route('invoices.finalise', $second));

    $first->refresh();

    expect($first->number)->toBe('INV/2026-27/0001')
        ->and($second->refresh()->number)->toBe('INV/2026-27/0002')
        ->and($first->status)->toBe(InvoiceStatus::Issued)
        ->and($first->seller_snapshot['legal_name'])->toBe('Acme Software')
        ->and($first->client_snapshot['name'])->toBe('Sunrise Public School');

    CompanyProfile::current()->update(['legal_name' => 'Renamed Pvt Ltd']);
    $client->update(['name' => 'Renamed School']);

    expect($first->refresh()->seller_snapshot['legal_name'])->toBe('Acme Software')
        ->and($first->client_snapshot['name'])->toBe('Sunrise Public School');
});

test('numbering starts again at 0001 in a new financial year', function () {
    $user = User::factory()->create();
    CompanyProfile::factory()->create();
    $march = draftInvoice(issueDate: '2027-03-31');
    $april = draftInvoice(issueDate: '2027-04-01');

    $this->actingAs($user)->post(route('invoices.finalise', $march));
    $this->actingAs($user)->post(route('invoices.finalise', $april));

    expect($march->refresh()->number)->toBe('INV/2026-27/0001')
        ->and($april->refresh()->number)->toBe('INV/2027-28/0001');
});

test('an invoice cannot be issued until the business profile is complete', function () {
    $user = User::factory()->create();
    CompanyProfile::factory()->create(['gstin' => null]);
    $draft = draftInvoice();

    $this->actingAs($user)
        ->post(route('invoices.finalise', $draft))
        ->assertSessionHasErrors(['invoice' => 'Complete your Business Profile before issuing invoices. Missing: GSTIN.']);

    expect($draft->refresh()->number)->toBeNull();
});

test('an invoice cannot be issued twice', function () {
    $user = User::factory()->create();
    CompanyProfile::factory()->create();
    $draft = draftInvoice();

    $this->actingAs($user)->post(route('invoices.finalise', $draft));
    $this->actingAs($user)
        ->post(route('invoices.finalise', $draft))
        ->assertSessionHasErrors(['invoice' => 'This invoice has already been issued.']);

    expect($draft->refresh()->sequence)->toBe(1);
});

test('cancelling keeps the number used so there are no gaps', function () {
    $user = User::factory()->create();
    CompanyProfile::factory()->create();
    $first = draftInvoice();
    $this->actingAs($user)->post(route('invoices.finalise', $first));

    $this->actingAs($user)
        ->post(route('invoices.cancel', $first), ['reason' => 'Wrong client'])
        ->assertRedirect(route('invoices.show', $first));

    $second = draftInvoice();
    $this->actingAs($user)->post(route('invoices.finalise', $second));

    expect($first->refresh()->status)->toBe(InvoiceStatus::Cancelled)
        ->and($first->cancellation_reason)->toBe('Wrong client')
        ->and($second->refresh()->number)->toBe('INV/2026-27/0002');
});

test('a cancellation needs a reason', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create();

    $this->actingAs($user)
        ->post(route('invoices.cancel', $invoice), ['reason' => ''])
        ->assertSessionHasErrors(['reason' => 'Say why the invoice is being cancelled.']);
});
