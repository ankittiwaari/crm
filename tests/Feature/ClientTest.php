<?php

use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Subscription;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('clients.index'))->assertRedirect(route('login'));
    $this->post(route('clients.store'), ['name' => 'X'])->assertRedirect(route('login'));
});

test('the client list is sorted by name and shows the main contact', function () {
    $user = User::factory()->create();
    $zeta = Client::factory()->create(['name' => 'Zeta School']);
    Contact::factory()->primary()->for($zeta)->create(['name' => 'Meera Nair']);
    Client::factory()->create(['name' => 'Alpha Pharmacy']);

    $this->actingAs($user)
        ->get(route('clients.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/index')
            ->where('clients.data.0.name', 'Alpha Pharmacy')
            ->where('clients.data.1.name', 'Zeta School')
            ->where('clients.data.1.primary_contact.name', 'Meera Nair')
            ->where('statusCounts.all', 2));
});

test('clients can be searched by name, city, GSTIN or contact phone', function (string $search) {
    $user = User::factory()->create();
    $sunrise = Client::factory()->create([
        'name' => 'Sunrise Public School',
        'city' => 'Mysuru',
        'gstin' => '29AAGCB7383J1Z4',
    ]);
    Contact::factory()->for($sunrise)->create(['phone' => '+919845012345']);
    Client::factory()->create(['name' => 'Green Valley Academy', 'city' => 'Hubballi']);

    $this->actingAs($user)
        ->get(route('clients.index', ['search' => $search]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('clients.data', 1)
            ->where('clients.data.0.name', 'Sunrise Public School'));
})->with([
    'name' => ['sunrise'],
    'city' => ['Mysuru'],
    'GSTIN' => ['7383J'],
    'contact phone with spaces' => ['98450 12345'],
]);

test('clients can be filtered by status and type, with counts for each status', function () {
    $user = User::factory()->create();
    Client::factory()->create(['name' => 'Active School']);
    Client::factory()->lead()->create(['name' => 'Lead School']);
    Client::factory()->lead()->pharmacy()->create(['name' => 'Lead Pharmacy']);

    $this->actingAs($user)
        ->get(route('clients.index', ['status' => 'lead', 'type' => 'school']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('clients.data', 1)
            ->where('clients.data.0.name', 'Lead School')
            ->where('statusCounts.all', 2)
            ->where('statusCounts.lead', 1)
            ->where('statusCounts.active', 1));
});

test('a client is created with a main contact whose number is normalised', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('clients.store'), [
        'name' => 'Sunrise Public School',
        'type' => 'school',
        'status' => 'active',
        'phone' => '080 2345 6789',
        'contact' => [
            'name' => 'R. Sharma',
            'designation' => 'Accountant',
            'phone' => '98450 12345',
            'whatsapp_opt_in' => true,
        ],
    ]);

    $client = Client::firstWhere('name', 'Sunrise Public School');
    $response->assertSessionHasNoErrors()->assertRedirect(route('clients.show', $client));

    $contact = $client->contacts()->sole();

    expect($client->type)->toBe(ClientType::School)
        ->and($client->phone)->toBe('+918023456789')
        ->and($contact->phone)->toBe('+919845012345')
        ->and($contact->is_primary)->toBeTrue()
        ->and($contact->receives_invoices)->toBeTrue()
        ->and($contact->whatsapp_opted_in_at)->not->toBeNull();
});

test('a client can be created without a contact', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('clients.store'), [
        'name' => 'Green Valley Academy',
        'type' => 'school',
        'status' => 'lead',
        'contact' => ['name' => '', 'designation' => '', 'phone' => '', 'whatsapp_opt_in' => false],
    ])->assertSessionHasNoErrors();

    expect(Client::firstWhere('name', 'Green Valley Academy')->contacts()->count())->toBe(0);
});

test('the client state is taken from the GSTIN', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('clients.store'), [
        'name' => 'Pune Medicals',
        'type' => 'pharmacy',
        'status' => 'active',
        'state_code' => '29',
        'gstin' => '27aapfu0939f1zv',
    ])->assertSessionHasNoErrors();

    $client = Client::firstWhere('name', 'Pune Medicals');

    expect($client->gstin)->toBe('27AAPFU0939F1ZV')
        ->and($client->state_code)->toBe('27');
});

test('creating a client validates the name, GSTIN and phone', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('clients.store'), [
        'name' => '',
        'type' => 'school',
        'status' => 'active',
        'gstin' => '27AAPFU0939F1ZW',
        'phone' => '12',
    ])->assertSessionHasErrors([
        'name',
        'gstin' => 'The GSTIN is not a valid GSTIN. Check the format and the last (check) character.',
        'phone' => 'Enter a valid phone number, e.g. 98450 12345.',
    ]);
});

test('a contact phone requires a contact name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('clients.store'), [
        'name' => 'Sunrise',
        'type' => 'school',
        'status' => 'active',
        'contact' => ['phone' => '9845012345'],
    ])->assertSessionHasErrors('contact.name');
});

test('the client page shows contacts with the main contact first and the timeline newest first', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    Contact::factory()->for($client)->create(['name' => 'Aarav']);
    Contact::factory()->primary()->for($client)->create(['name' => 'Zoya']);
    $client->activities()->create(['type' => 'call', 'body' => 'Older', 'happened_at' => now()->subDay()]);
    $client->activities()->create(['type' => 'note', 'body' => 'Newer', 'happened_at' => now()]);

    $this->actingAs($user)
        ->get(route('clients.show', $client))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/show')
            ->where('client.contacts.0.name', 'Zoya')
            ->where('client.contacts.1.name', 'Aarav')
            ->where('activities.0.body', 'Newer')
            ->has('options.activityTypes', 6));
});

test('a client can be updated', function () {
    $user = User::factory()->create();
    $client = Client::factory()->lead()->create();

    $this->actingAs($user)
        ->patch(route('clients.update', $client), [
            'name' => 'Renamed School',
            'type' => 'school',
            'status' => 'active',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('clients.show', $client));

    expect($client->refresh()->name)->toBe('Renamed School')
        ->and($client->status)->toBe(ClientStatus::Active);
});

test('archiving a client hides it from the list but keeps the record', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->delete(route('clients.destroy', $client))
        ->assertRedirect(route('clients.index'));

    $this->assertSoftDeleted($client);
    $this->actingAs($user)
        ->get(route('clients.index'))
        ->assertInertia(fn (Assert $page) => $page->has('clients.data', 0));
});

test('the client list shows each client\'s yearly value and next renewal', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    Subscription::factory()->for($client)->renewingIn(20)->create();
    Subscription::factory()->for($client)->renewingIn(5)->create(['billing_cycle' => 'monthly', 'rate' => 500_000]);
    Subscription::factory()->for($client)->renewingIn(1)->create(['status' => 'cancelled']);

    $this->actingAs($user)
        ->get(route('clients.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('clients.data.0.annual_value', 4_800_000 + 12 * 500_000)
            ->where('clients.data.0.next_renewal_on', today()->addDays(5)->toDateString()));
});
