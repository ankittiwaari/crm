<?php

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;

test('the first contact becomes the main contact even when not ticked', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->post(route('clients.contacts.store', $client), ['name' => 'Meera Nair', 'is_primary' => false])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('clients.show', $client));

    expect($client->contacts()->sole()->is_primary)->toBeTrue();
});

test('making a contact the main contact unsets the previous one', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $previous = Contact::factory()->primary()->for($client)->create();

    $this->actingAs($user)
        ->post(route('clients.contacts.store', $client), ['name' => 'R. Sharma', 'is_primary' => true])
        ->assertSessionHasNoErrors();

    expect($previous->refresh()->is_primary)->toBeFalse()
        ->and($client->contacts()->where('is_primary', true)->sole()->name)->toBe('R. Sharma');
});

test('WhatsApp consent keeps its original date and is cleared when withdrawn', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->primary()->create(['whatsapp_opted_in_at' => '2026-01-15 10:00:00']);

    $this->actingAs($user)
        ->patch(route('contacts.update', $contact), ['name' => $contact->name, 'whatsapp_opt_in' => true])
        ->assertSessionHasNoErrors();

    expect($contact->refresh()->whatsapp_opted_in_at->toDateString())->toBe('2026-01-15');

    $this->actingAs($user)
        ->patch(route('contacts.update', $contact), ['name' => $contact->name, 'whatsapp_opt_in' => false]);

    expect($contact->refresh()->whatsapp_opted_in_at)->toBeNull();
});

test('contact numbers are normalised and validated', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->post(route('clients.contacts.store', $client), ['name' => 'Meera', 'whatsapp_number' => '0 98450 12345'])
        ->assertSessionHasNoErrors();

    expect($client->contacts()->sole()->whatsapp_number)->toBe('+919845012345');

    $this->actingAs($user)
        ->post(route('clients.contacts.store', $client), ['name' => 'Bad', 'phone' => '123'])
        ->assertSessionHasErrors(['phone' => 'Enter a valid phone number, e.g. 98450 12345.']);
});

test('removing the main contact promotes another contact', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $main = Contact::factory()->primary()->for($client)->create();
    $other = Contact::factory()->for($client)->create();

    $this->actingAs($user)
        ->delete(route('contacts.destroy', $main))
        ->assertRedirect(route('clients.show', $client));

    $this->assertModelMissing($main);
    expect($other->refresh()->is_primary)->toBeTrue();
});
