<?php

use App\Enums\InvoiceStatus;
use App\Enums\TaxType;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-10-20 10:00:00');
    $this->user = User::factory()->create();
});

/**
 * @return list<list<string>>
 */
function csvRows(string $type, string $from = '2026-04-01', string $to = '2027-03-31'): array
{
    $response = test()->actingAs(test()->user)
        ->get(route('reports.export', ['type' => $type, 'from' => $from, 'to' => $to]))
        ->assertOk()
        ->assertDownload("{$type}_{$from}_to_{$to}.csv");

    $content = ltrim($response->streamedContent(), "\u{FEFF}");

    return array_map(fn (string $line) => str_getcsv($line, escape: ''), array_values(array_filter(explode("\n", $content))));
}

function invoiceFor(array $client, array $attributes = [], int $taxable = 4_800_000): Invoice
{
    $tax = intdiv($taxable * 18, 100);
    $intra = ($attributes['tax_type'] ?? TaxType::IntraState) === TaxType::IntraState;
    $invoice = Invoice::factory()->create([
        'number' => 'INV/2026-27/'.str_pad((string) (Invoice::count() + 1), 4, '0', STR_PAD_LEFT),
        'issue_date' => '2026-10-10',
        'client_snapshot' => $client,
        'taxable_total' => $taxable,
        'cgst_total' => $intra ? $tax / 2 : 0,
        'sgst_total' => $intra ? $tax / 2 : 0,
        'igst_total' => $intra ? 0 : $tax,
        'total' => $taxable + $tax,
        ...$attributes,
    ]);
    $invoice->items()->create([
        'description' => 'School ERP, Yearly subscription', 'sac_code' => '998314', 'quantity' => 1,
        'unit_price' => $taxable, 'tax_rate' => 18, 'taxable_amount' => $taxable,
        'cgst' => $intra ? $tax / 2 : 0, 'sgst' => $intra ? $tax / 2 : 0, 'igst' => $intra ? 0 : $tax, 'line_total' => $taxable + $tax,
    ]);

    return $invoice;
}

test('the invoice register lists issued and cancelled invoices but not drafts', function () {
    invoiceFor(['name' => 'Sunrise', 'gstin' => null]);
    invoiceFor(['name' => 'Cancelled Co'], ['status' => InvoiceStatus::Cancelled, 'cancellation_reason' => 'Duplicate']);
    invoiceFor(['name' => 'Draft Co'], ['status' => InvoiceStatus::Draft, 'number' => null]);

    $rows = csvRows('invoices');

    $byClient = collect($rows)->slice(1)->keyBy(fn (array $row) => $row[4]);

    expect($rows[0][0])->toBe('Invoice number')
        ->and($rows)->toHaveCount(3)
        ->and($byClient['Sunrise'])->toContain('B2C', '48000.00', '4320.00', '56640.00')
        ->and($byClient['Cancelled Co'])->toContain('Cancelled', 'Duplicate');
});

test('GSTR-1 B2B lists registered clients invoice by invoice with the place of supply', function () {
    invoiceFor(['name' => 'Pune Medicals', 'gstin' => '27AAPFU0939F1ZV'], ['place_of_supply' => '27', 'tax_type' => TaxType::InterState]);
    invoiceFor(['name' => 'Sunrise', 'gstin' => null]);

    $rows = csvRows('gstr1-b2b');

    expect($rows)->toHaveCount(2)
        ->and($rows[1])->toBe([
            '27AAPFU0939F1ZV', 'Pune Medicals', 'INV/2026-27/0001', '10-Oct-2026', '56640.00', '27-Maharashtra',
            'N', '', 'Regular B2B', '', '18', '48000.00', '0.00',
        ]);
});

test('large inter-state B2C invoices go to B2CL and the rest are summed in B2CS by state and rate', function () {
    invoiceFor(['name' => 'Big', 'gstin' => null], ['place_of_supply' => '27', 'tax_type' => TaxType::InterState], 9_000_000);
    invoiceFor(['name' => 'Small inter', 'gstin' => null], ['place_of_supply' => '27', 'tax_type' => TaxType::InterState], 1_000_000);
    invoiceFor(['name' => 'Local A', 'gstin' => null]);
    invoiceFor(['name' => 'Local B', 'gstin' => null]);

    $b2cl = csvRows('gstr1-b2cl');
    $b2cs = csvRows('gstr1-b2cs');

    expect($b2cl)->toHaveCount(2)
        ->and($b2cl[1][0])->toBe('INV/2026-27/0001')
        ->and($b2cs)->toHaveCount(3)
        ->and($b2cs[1])->toBe(['OE', '27-Maharashtra', '', '18', '10000.00', '0.00', ''])
        ->and($b2cs[2])->toBe(['OE', '29-Karnataka', '', '18', '96000.00', '0.00', '']);
});

test('the HSN summary groups lines by supply type, SAC and rate', function () {
    invoiceFor(['name' => 'Sunrise', 'gstin' => null]);
    invoiceFor(['name' => 'Green Valley', 'gstin' => null]);
    invoiceFor(['name' => 'Pune Medicals', 'gstin' => '27AAPFU0939F1ZV'], ['place_of_supply' => '27', 'tax_type' => TaxType::InterState]);

    $rows = csvRows('hsn-summary');

    expect($rows)->toHaveCount(3)
        ->and($rows[1])->toBe(['B2B', '998314', 'School ERP, Yearly subscription', 'NA', '1', '56640.00', '18', '48000.00', '8640.00', '0.00', '0.00', '0.00'])
        ->and($rows[2])->toBe(['B2C', '998314', 'School ERP, Yearly subscription', 'NA', '2', '113280.00', '18', '96000.00', '0.00', '8640.00', '8640.00', '0.00']);
});

test('payments and expenses can be exported for the CA', function () {
    $invoice = invoiceFor(['name' => 'Sunrise', 'gstin' => null]);
    $invoice->payments()->create(['amount' => 5_184_000, 'tds_amount' => 480_000, 'paid_on' => '2026-10-15', 'method' => 'bank_transfer', 'reference' => 'UTR1']);
    Expense::factory()->create(['spent_on' => '2026-10-02']);

    expect(csvRows('payments')[1])->toBe(['15-10-2026', 'INV/2026-27/0001', 'Sunrise', '', '51840.00', '4800.00', '56640.00', 'Bank transfer', 'UTR1'])
        ->and(csvRows('expenses')[1])->toBe(['02-10-2026', 'Hosting & servers', 'DigitalOcean', 'Server', '1180.00', '180.00', '1000.00', '', '', 'No']);
});

test('exports only include the chosen dates and need a valid range', function () {
    invoiceFor(['name' => 'Sunrise', 'gstin' => null]);

    expect(csvRows('invoices', '2026-11-01', '2026-11-30'))->toHaveCount(1);

    $this->actingAs($this->user)
        ->get(route('reports.export', ['type' => 'invoices', 'from' => '2026-11-30', 'to' => '2026-11-01']))
        ->assertSessionHasErrors('to');

    $this->actingAs($this->user)
        ->get(route('reports.export', ['type' => 'everything', 'from' => '2026-11-01', 'to' => '2026-11-30']))
        ->assertSessionHasErrors('type');
});
