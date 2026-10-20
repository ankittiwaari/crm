<?php

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-20 10:00:00');
});

test('guests are redirected to the login page', function () {
    $this->get(route('expenses.index'))->assertRedirect(route('login'));
});

test('the expenses page shows the month\'s expenses and the year\'s cost by category', function () {
    $user = User::factory()->create();
    Expense::factory()->create(['description' => 'October server', 'spent_on' => '2026-10-05']);
    Expense::factory()->create(['description' => 'September server', 'spent_on' => '2026-09-05']);
    Expense::factory()->create(['category' => ExpenseCategory::ProfessionalFees, 'amount' => 500_000, 'gst_amount' => 0, 'spent_on' => '2026-06-01']);
    Expense::factory()->create(['description' => 'Last year', 'spent_on' => '2026-03-31']);

    $this->actingAs($user)
        ->get(route('expenses.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/index')
            ->has('expenses', 1)
            ->where('expenses.0.description', 'October server')
            ->where('summary.month_total', 118_000)
            ->where('summary.month_gst', 18_000)
            ->where('summary.year_total', 736_000)
            ->where('summary.year_net', 700_000)
            ->where('summary.by_category.0.category', 'professional_fees'));
});

test('another month and category can be chosen', function () {
    $user = User::factory()->create();
    Expense::factory()->create(['spent_on' => '2026-09-05']);
    Expense::factory()->create(['spent_on' => '2026-09-06', 'category' => ExpenseCategory::Marketing]);

    $this->actingAs($user)
        ->get(route('expenses.index', ['month' => '2026-09', 'category' => 'marketing']))
        ->assertInertia(fn (Assert $page) => $page->has('expenses', 1)->where('month', '2026-09'));
});

test('an expense is saved in paise with its receipt stored privately', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('expenses.store'), [
            'spent_on' => '2026-10-18',
            'category' => 'hosting',
            'vendor' => 'DigitalOcean',
            'description' => 'Server',
            'amount' => '1,180',
            'gst_amount' => '180',
            'receipt' => UploadedFile::fake()->create('bill.pdf', 50, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('expenses.index', ['month' => '2026-10']));

    $expense = Expense::sole();

    expect($expense->amount)->toBe(118_000)
        ->and($expense->netCost())->toBe(100_000)
        ->and($expense->user_id)->toBe($user->id);
    Storage::disk('local')->assertExists($expense->receipt_path);

    $this->actingAs($user)->get(route('expenses.receipt', $expense))->assertOk()->assertDownload();
});

test('expenses are validated with clear messages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('expenses.store'), [
            'spent_on' => '2026-10-21',
            'category' => 'hosting',
            'description' => '',
            'amount' => '100',
            'gst_amount' => '200',
        ])
        ->assertSessionHasErrors([
            'spent_on' => 'The date cannot be in the future.',
            'description' => 'What was this for?',
            'gst_amount' => 'The GST cannot be more than the amount paid.',
        ]);
});

test('updating can replace or remove the receipt', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    Storage::disk('local')->put('receipts/old.pdf', 'old');
    $expense = Expense::factory()->create();
    $expense->forceFill(['receipt_path' => 'receipts/old.pdf'])->save();
    $payload = ['spent_on' => '2026-10-20', 'category' => 'hosting', 'description' => 'Server', 'amount' => '1180'];

    $this->actingAs($user)->patch(route('expenses.update', $expense), [
        ...$payload,
        'receipt' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    Storage::disk('local')->assertMissing('receipts/old.pdf');
    Storage::disk('local')->assertExists($expense->refresh()->receipt_path);

    $this->actingAs($user)->patch(route('expenses.update', $expense), [...$payload, 'remove_receipt' => true]);

    expect($expense->refresh()->receipt_path)->toBeNull();
});

test('deleting an expense deletes its receipt', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    Storage::disk('local')->put('receipts/bill.pdf', 'bill');
    $expense = Expense::factory()->create();
    $expense->forceFill(['receipt_path' => 'receipts/bill.pdf'])->save();

    $this->actingAs($user)->delete(route('expenses.destroy', $expense));

    $this->assertModelMissing($expense);
    Storage::disk('local')->assertMissing('receipts/bill.pdf');
});

test('last month\'s recurring expenses are suggested until added this month', function () {
    $user = User::factory()->create();
    Expense::factory()->create(['description' => 'Server', 'vendor' => 'DigitalOcean', 'is_recurring' => true, 'spent_on' => '2026-09-03']);
    Expense::factory()->create(['description' => 'Email', 'vendor' => 'Zoho', 'is_recurring' => true, 'spent_on' => '2026-09-04']);
    Expense::factory()->create(['description' => 'Email', 'vendor' => 'Zoho', 'is_recurring' => true, 'spent_on' => '2026-10-04']);

    $this->actingAs($user)
        ->get(route('expenses.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recurringToRepeat', 1)
            ->where('recurringToRepeat.0.description', 'Server'));
});
