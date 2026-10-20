<?php

use App\Enums\InvoiceStatus;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-20 10:00:00');
});

function issuedWithLine(array $invoice, ?Product $product, int $taxable): Invoice
{
    $created = Invoice::factory()->create([
        'taxable_total' => $taxable,
        'cgst_total' => intdiv($taxable * 9, 100),
        'sgst_total' => intdiv($taxable * 9, 100),
        'total' => $taxable + 2 * intdiv($taxable * 9, 100),
        ...$invoice,
    ]);
    $created->items()->create([
        'product_id' => $product?->id, 'description' => $product?->name ?? 'Customisation', 'sac_code' => '998314',
        'quantity' => 1, 'unit_price' => $taxable, 'tax_rate' => 18, 'taxable_amount' => $taxable,
        'cgst' => intdiv($taxable * 9, 100), 'sgst' => intdiv($taxable * 9, 100), 'line_total' => $taxable + 2 * intdiv($taxable * 9, 100),
    ]);

    return $created;
}

test('guests are redirected to the login page', function () {
    $this->get(route('reports.index'))->assertRedirect(route('login'));
});

test('the report totals revenue, GST, collections, expenses and profit for the financial year', function () {
    $user = User::factory()->create();
    $erp = Product::factory()->create(['name' => 'School ERP']);
    $paid = issuedWithLine(['issue_date' => '2026-05-10'], $erp, 4_800_000);
    issuedWithLine(['issue_date' => '2026-10-01'], null, 1_000_000);
    issuedWithLine(['issue_date' => '2026-03-31'], $erp, 9_900_000);
    issuedWithLine(['issue_date' => '2026-06-01', 'status' => InvoiceStatus::Cancelled], $erp, 9_900_000);
    issuedWithLine(['issue_date' => '2026-06-01', 'status' => InvoiceStatus::Draft, 'number' => null], $erp, 9_900_000);
    $paid->payments()->create(['amount' => 5_184_000, 'tds_amount' => 480_000, 'paid_on' => '2026-05-20', 'method' => 'upi']);
    Expense::factory()->create(['spent_on' => '2026-05-02', 'amount' => 118_000, 'gst_amount' => 18_000]);

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/index')
            ->where('year', '2026-27')
            ->where('totals.revenue', 5_800_000)
            ->where('totals.gst', 1_044_000)
            ->where('totals.collected', 5_664_000)
            ->where('totals.tds', 480_000)
            ->where('totals.expenses', 100_000)
            ->where('totals.input_gst', 18_000)
            ->where('totals.profit', 5_700_000)
            ->where('months.1.label', 'May')
            ->where('months.1.revenue', 4_800_000)
            ->where('months.1.expenses', 100_000)
            ->where('byProduct.0.product', 'School ERP')
            ->where('byProduct.1.product', 'Custom lines')
            ->where('tdsByClient.0.tds', 480_000)
            ->where('years', ['2026-27', '2025-26']));
});

test('an earlier financial year can be chosen', function () {
    $user = User::factory()->create();
    issuedWithLine(['issue_date' => '2026-03-31'], null, 9_900_000);

    $this->actingAs($user)
        ->get(route('reports.index', ['year' => '2025-26']))
        ->assertInertia(fn (Assert $page) => $page->where('year', '2025-26')->where('totals.revenue', 9_900_000));
});

test('unpaid invoices are grouped by age and those past 45 days are listed', function () {
    $user = User::factory()->create();
    Invoice::factory()->create(['issue_date' => '2026-10-10', 'due_date' => '2026-11-09']);
    Invoice::factory()->create(['issue_date' => '2026-09-01', 'due_date' => '2026-10-01', 'number' => 'INV/2026-27/0003']);
    Invoice::factory()->create(['issue_date' => '2026-05-01', 'due_date' => '2026-05-31', 'status' => InvoiceStatus::Paid, 'amount_settled' => 6_372_000]);

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ageing.total', 2 * 6_372_000)
            ->where('ageing.buckets.0.amount', 6_372_000)
            ->where('ageing.buckets.2.amount', 6_372_000)
            ->has('ageing.over_msme_limit', 1)
            ->where('ageing.over_msme_limit.0.number', 'INV/2026-27/0003')
            ->where('ageing.over_msme_limit.0.days', 49));
});

test('pricing insight works out an effective per-student price from recorded student counts', function () {
    $user = User::factory()->create();
    $erp = Product::factory()->create(['name' => 'School ERP', 'unit_label' => 'per student']);
    Subscription::factory()->for($erp)->create(['rate' => 4_800_000, 'unit_count' => 400, 'is_promotional' => true]);
    Subscription::factory()->for($erp)->perUnit(12_000, 600)->create();

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pricing.0.product', 'School ERP')
            ->where('pricing.0.subscriptions', 2)
            ->where('pricing.0.promotional', 1)
            ->where('pricing.0.units_recorded', 1000)
            ->where('pricing.0.per_unit_yearly', 12_000));
});
