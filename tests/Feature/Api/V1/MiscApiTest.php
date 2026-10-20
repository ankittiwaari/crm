<?php

use App\Models\DeviceToken;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo('2026-10-20 10:00:00');
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('the dashboard returns today\'s figures and reminders', function () {
    Reminder::factory()->create(['title' => 'Call Sunrise']);

    $this->getJson(route('api.v1.dashboard'))
        ->assertOk()
        ->assertJsonPath('data.today.0.title', 'Call Sunrise')
        ->assertJsonStructure(['data' => ['metrics' => ['annual_recurring', 'outstanding'], 'upcoming', 'overdue']]);
});

test('reports are returned for a financial year', function () {
    $this->getJson(route('api.v1.reports', ['year' => '2026-27']))
        ->assertOk()
        ->assertJsonPath('data.year', '2026-27')
        ->assertJsonCount(12, 'data.months');
});

test('products are listed', function () {
    Product::factory()->create(['name' => 'School ERP']);

    $this->getJson(route('api.v1.products.index'))->assertOk()->assertJsonPath('data.0.name', 'School ERP');
});

test('reminders can be listed, added, ticked off and snoozed', function () {
    $reminder = Reminder::factory()->create();

    $this->getJson(route('api.v1.reminders.index'))->assertOk()->assertJsonPath('meta.due_count', 1);

    $this->postJson(route('api.v1.reminders.store'), ['title' => 'Send brochure', 'due_on' => '2026-10-21'])
        ->assertCreated()
        ->assertJsonPath('data.type', 'general');

    $this->patchJson(route('api.v1.reminders.update', $reminder), ['done' => true])
        ->assertOk()
        ->assertJsonPath('data.completed_at', fn (?string $value) => $value !== null);

    $this->patchJson(route('api.v1.reminders.update', $reminder), ['snooze_days' => 7])
        ->assertOk()
        ->assertJsonPath('data.due_on', '2026-10-27');
});

test('an expense can be added with a receipt photo', function () {
    Storage::fake('local');

    $this->post(route('api.v1.expenses.store'), [
        'spent_on' => '2026-10-19',
        'category' => 'travel',
        'description' => 'Cab to Sunrise demo',
        'amount' => '450',
        'receipt' => UploadedFile::fake()->image('bill.jpg'),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.amount', 45_000)
        ->assertJsonPath('data.has_receipt', true);

    Storage::disk('local')->assertExists(Expense::sole()->receipt_path);

    $this->getJson(route('api.v1.expenses.index', ['month' => '2026-10']))
        ->assertOk()
        ->assertJsonPath('meta.total', 45_000);
});

test('devices are registered once for push notifications and can be removed', function () {
    $this->postJson(route('api.v1.devices.store'), ['token' => 'fcm-token-1', 'platform' => 'android'])->assertCreated();
    $this->postJson(route('api.v1.devices.store'), ['token' => 'fcm-token-1', 'platform' => 'android'])->assertOk();

    expect(DeviceToken::sole()->user_id)->toBe($this->user->id);

    $this->deleteJson(route('api.v1.devices.destroy'), ['token' => 'fcm-token-1'])->assertNoContent();

    expect(DeviceToken::count())->toBe(0);
});
