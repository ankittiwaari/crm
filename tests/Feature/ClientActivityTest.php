<?php

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Client;
use App\Models\User;

test('logging an activity adds it to the timeline and updates the last contact time', function () {
    $this->freezeSecond();
    $user = User::factory()->create();
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->post(route('clients.activities.store', $client), [
            'type' => 'call',
            'body' => 'Happy with attendance module.',
            'follow_up_on' => now()->addDays(3)->toDateString(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('clients.show', $client));

    $activity = $client->activities()->sole();

    expect($activity->type)->toBe(ActivityType::Call)
        ->and($activity->user_id)->toBe($user->id)
        ->and($activity->happened_at->equalTo(now()))->toBeTrue()
        ->and($activity->follow_up_on->toDateString())->toBe(now()->addDays(3)->toDateString())
        ->and($client->refresh()->last_activity_at->equalTo(now()))->toBeTrue();
});

test('an activity needs a note and cannot have a follow-up in the past', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->post(route('clients.activities.store', $client), [
            'type' => 'call',
            'body' => '',
            'follow_up_on' => now()->subDay()->toDateString(),
        ])
        ->assertSessionHasErrors([
            'body' => 'Write a short note about what happened.',
            'follow_up_on' => 'The follow-up date cannot be in the past.',
        ]);
});

test('deleting the latest activity moves the last contact time back', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $older = Activity::factory()->for($client)->create(['happened_at' => '2026-10-01 10:00:00']);
    $newer = Activity::factory()->for($client)->create(['happened_at' => '2026-10-15 10:00:00']);
    $client->update(['last_activity_at' => $newer->happened_at]);

    $this->actingAs($user)
        ->delete(route('activities.destroy', $newer))
        ->assertRedirect(route('clients.show', $client));

    $this->assertModelMissing($newer);
    expect($client->refresh()->last_activity_at->equalTo($older->happened_at))->toBeTrue();
});
