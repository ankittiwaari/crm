<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

test('a full payment with TDS marks the invoice paid', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create();

    $this->actingAs($user)
        ->post(route('invoices.payments.store', $invoice), [
            'amount' => '58,320',
            'tds_amount' => '5400',
            'paid_on' => today()->toDateString(),
            'method' => 'bank_transfer',
            'reference' => 'UTR123',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('invoices.show', $invoice));

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->amount_settled)->toBe(6_372_000)
        ->and($invoice->balanceDue())->toBe(0)
        ->and($invoice->paid_at->toDateString())->toBe(today()->toDateString())
        ->and($invoice->payments()->sole()->tds_amount)->toBe(540_000);
});

test('a part payment leaves the invoice partly paid', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create();

    $this->actingAs($user)->post(route('invoices.payments.store', $invoice), [
        'amount' => '30000', 'paid_on' => today()->toDateString(), 'method' => 'upi',
    ]);

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::PartiallyPaid)
        ->and($invoice->balanceDue())->toBe(3_372_000);
});

test('a payment cannot be more than the balance due', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create();

    $this->actingAs($user)
        ->post(route('invoices.payments.store', $invoice), [
            'amount' => '70000', 'paid_on' => today()->toDateString(), 'method' => 'upi',
        ])
        ->assertSessionHasErrors(['amount' => 'Amount plus TDS is more than the balance due of ₹63,720.00.']);
});

test('payments cannot be recorded against drafts or future dates', function () {
    $user = User::factory()->create();
    $draft = Invoice::factory()->create(['status' => InvoiceStatus::Draft, 'number' => null]);

    $this->actingAs($user)
        ->post(route('invoices.payments.store', $draft), ['amount' => '100', 'paid_on' => today()->toDateString(), 'method' => 'upi'])
        ->assertSessionHasErrors(['amount' => 'Payments can only be recorded against unpaid invoices.']);

    $this->actingAs($user)
        ->post(route('invoices.payments.store', Invoice::factory()->create()), ['amount' => '100', 'paid_on' => today()->addDay()->toDateString(), 'method' => 'upi'])
        ->assertSessionHasErrors(['paid_on' => 'The payment date cannot be in the future.']);
});

test('removing a payment reopens the invoice', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create();
    $this->actingAs($user)->post(route('invoices.payments.store', $invoice), [
        'amount' => '63720', 'paid_on' => today()->toDateString(), 'method' => 'upi',
    ]);

    $this->actingAs($user)
        ->delete(route('payments.destroy', $invoice->payments()->sole()))
        ->assertRedirect(route('invoices.show', $invoice));

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->paid_at)->toBeNull()
        ->and($invoice->amount_settled)->toBe(0);
});

test('an invoice with payments cannot be cancelled', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create();
    $this->actingAs($user)->post(route('invoices.payments.store', $invoice), [
        'amount' => '1000', 'paid_on' => today()->toDateString(), 'method' => 'upi',
    ]);

    $this->actingAs($user)
        ->post(route('invoices.cancel', $invoice), ['reason' => 'Oops'])
        ->assertSessionHasErrors('reason');

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::PartiallyPaid);
});
