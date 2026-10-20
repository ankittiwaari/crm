<?php

use App\Models\Activity;
use App\Models\Client;
use App\Models\Reminder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-20 10:00:00');
});

test('guests are redirected to the login page', function () {
    $this->get(route('reminders.index'))->assertRedirect(route('login'));
});

test('the due tab lists reminders due today or earlier, oldest first', function () {
    $user = User::factory()->create();
    Reminder::factory()->create(['title' => 'Today', 'due_on' => today()]);
    Reminder::factory()->create(['title' => 'Late', 'due_on' => today()->subDays(2)]);
    Reminder::factory()->create(['title' => 'Later', 'due_on' => today()->addDay()]);
    Reminder::factory()->completed()->create(['title' => 'Done', 'due_on' => today()]);

    $this->actingAs($user)
        ->get(route('reminders.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reminders/index')
            ->has('reminders', 2)
            ->where('reminders.0.title', 'Late')
            ->where('reminders.0.is_overdue', true)
            ->where('counts.due', 2)
            ->where('counts.upcoming', 1)
            ->where('remindersDue', 2));
});

test('a reminder can be added for a client', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->post(route('reminders.store'), ['title' => 'Send the brochure', 'due_on' => '2026-10-22', 'client_id' => $client->id])
        ->assertSessionHasNoErrors();

    expect(Reminder::sole()->client_id)->toBe($client->id);
});

test('a reminder needs a title and a date that is not in the past', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('reminders.store'), ['title' => '', 'due_on' => '2026-10-19'])
        ->assertSessionHasErrors(['title' => 'What do you need to do?', 'due_on' => 'Pick today or a later date.']);
});

test('reminders can be ticked off, reopened and snoozed', function () {
    $user = User::factory()->create();
    $reminder = Reminder::factory()->create();

    $this->actingAs($user)->patch(route('reminders.update', $reminder), ['done' => true]);
    expect($reminder->refresh()->completed_at)->not->toBeNull();

    $this->actingAs($user)->patch(route('reminders.update', $reminder), ['done' => false]);
    expect($reminder->refresh()->completed_at)->toBeNull();

    $this->actingAs($user)->patch(route('reminders.update', $reminder), ['snooze_days' => 3]);
    expect($reminder->refresh()->due_on->toDateString())->toBe('2026-10-23');
});

test('a follow-up date on an activity creates a reminder, removed with the activity', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['name' => 'Sunrise Public School']);

    $this->actingAs($user)->post(route('clients.activities.store', $client), [
        'type' => 'call', 'body' => 'Wants SMS alerts for parents', 'follow_up_on' => '2026-10-23',
    ]);

    $reminder = Reminder::sole();

    expect($reminder->title)->toBe('Follow up with Sunrise Public School: Wants SMS alerts for parents')
        ->and($reminder->due_on->toDateString())->toBe('2026-10-23')
        ->and($reminder->client_id)->toBe($client->id);

    $this->actingAs($user)->delete(route('activities.destroy', Activity::sole()));

    expect(Reminder::count())->toBe(0);
});
