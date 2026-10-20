<?php

use App\Enums\TaxType;
use App\Services\Invoices\InvoiceCalculator;

test('intra-state GST is split equally into CGST and SGST', function () {
    $result = (new InvoiceCalculator)->calculate([
        ['quantity' => 1, 'unit_price' => 4_800_000, 'tax_rate' => '18.00'],
        ['quantity' => 1, 'unit_price' => 600_000, 'tax_rate' => '18.00'],
    ], TaxType::IntraState);

    expect($result['taxable_total'])->toBe(5_400_000)
        ->and($result['cgst_total'])->toBe(486_000)
        ->and($result['sgst_total'])->toBe(486_000)
        ->and($result['igst_total'])->toBe(0)
        ->and($result['total'])->toBe(6_372_000)
        ->and($result['round_off'])->toBe(0);
});

test('inter-state GST is charged as IGST', function () {
    $result = (new InvoiceCalculator)->calculate([
        ['quantity' => 450, 'unit_price' => 11_000, 'tax_rate' => 18],
    ], TaxType::InterState);

    expect($result['lines'][0]['taxable_amount'])->toBe(4_950_000)
        ->and($result['igst_total'])->toBe(891_000)
        ->and($result['cgst_total'])->toBe(0)
        ->and($result['total'])->toBe(5_841_000);
});

test('discounts reduce the taxable value and never go below zero', function () {
    $result = (new InvoiceCalculator)->calculate([
        ['quantity' => 1, 'unit_price' => 1_000_000, 'discount' => 100_000, 'tax_rate' => 18],
        ['quantity' => 1, 'unit_price' => 50_000, 'discount' => 90_000, 'tax_rate' => 18],
    ], TaxType::IntraState);

    expect($result['discount_total'])->toBe(150_000)
        ->and($result['lines'][0]['taxable_amount'])->toBe(900_000)
        ->and($result['lines'][1]['taxable_amount'])->toBe(0);
});

test('tax is rounded half-up per line and the total to the nearest rupee', function () {
    // ₹999.99 at 9% is 8,999.91 paise, rounded to 9,000; the ₹1,179.99 total rounds up to ₹1,180.
    $result = (new InvoiceCalculator)->calculate([
        ['quantity' => 1, 'unit_price' => 99_999, 'tax_rate' => 18],
    ], TaxType::IntraState);

    expect($result['cgst_total'])->toBe(9_000)
        ->and($result['sgst_total'])->toBe(9_000)
        ->and($result['total'])->toBe(118_000)
        ->and($result['round_off'])->toBe(1);
});

test('rounding the total down gives a negative round-off', function () {
    $result = (new InvoiceCalculator)->calculate([
        ['quantity' => 1, 'unit_price' => 10_040, 'tax_rate' => 0],
    ], TaxType::IntraState);

    expect($result['total'])->toBe(10_000)
        ->and($result['round_off'])->toBe(-40);
});
