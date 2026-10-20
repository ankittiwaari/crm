<?php

use App\Models\Client;
use App\Models\Contact;
use App\Models\Subscription;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
});

test('clients are listed with paging, search and status counts', function () {
    Client::factory()->create(['name' => 'Sunrise Public School']);
    Client::factory()->lead()->create(['name' => 'Green Valley Academy']);

    $this->getJson(route('api.v1.clients.index', ['search' => 'sunrise']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Sunrise Public School')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.status_counts.active', 1);
});

test('a client is created with a main contact', function () {
    $this->postJson(route('api.v1.clients.store'), [
        'name' => 'Sunrise Public School',
        'type' => 'school',
        'status' => 'active',
        'contact' => ['name' => 'R. Sharma', 'phone' => '98450 12345', 'whatsapp_opt_in' => true],
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Sunrise Public School')
        ->assertJsonPath('data.contacts.0.phone', '+919845012345')
        ->assertJsonPath('data.contacts.0.whatsapp_opted_in', true);
});

test('validation errors are returned as JSON', function () {
    $this->postJson(route('api.v1.clients.store'), ['name' => '', 'type' => 'school', 'status' => 'active', 'gstin' => 'BAD'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'gstin']);
});

test('a client is returned with subscriptions, activity and invoices', function () {
    $client = Client::factory()->create();
    Contact::factory()->primary()->for($client)->create();
    Subscription::factory()->for($client)->create();
    $client->activities()->create(['type' => 'call', 'body' => 'Called', 'happened_at' => now()]);

    $this->getJson(route('api.v1.clients.show', $client))
        ->assertOk()
        ->assertJsonCount(1, 'data.contacts')
        ->assertJsonCount(1, 'subscriptions')
        ->assertJsonPath('subscriptions.0.amount_per_cycle', 4_800_000)
        ->assertJsonPath('activities.0.body', 'Called')
        ->assertJsonPath('outstanding', 0);
});

test('contacts and activities can be added and a client archived', function () {
    $client = Client::factory()->create();

    $this->postJson(route('api.v1.clients.contacts.store', $client), ['name' => 'Meera'])
        ->assertCreated()
        ->assertJsonPath('data.is_primary', true);

    $this->postJson(route('api.v1.clients.activities.store', $client), ['type' => 'call', 'body' => 'Demo booked', 'follow_up_on' => today()->addDay()->toDateString()])
        ->assertCreated()
        ->assertJsonPath('data.type', 'call');

    $this->deleteJson(route('api.v1.clients.destroy', $client))->assertNoContent();
    $this->assertSoftDeleted($client);
});

test('records that do not exist return 404', function () {
    $this->getJson(route('api.v1.clients.show', 999))->assertNotFound();
});
