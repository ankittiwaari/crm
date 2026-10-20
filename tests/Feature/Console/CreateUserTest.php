<?php

use App\Models\User;

test('the command creates a verified user', function () {
    $this->artisan('app:create-user')
        ->expectsQuestion('Name', 'Ankit')
        ->expectsQuestion('Email address', 'owner@example.com')
        ->expectsQuestion('Password', 'secret-password')
        ->expectsQuestion('Confirm password', 'secret-password')
        ->assertSuccessful();

    $user = User::firstWhere('email', 'owner@example.com');

    expect($user->name)->toBe('Ankit')
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

test('the command fails and creates nothing when the input is invalid', function () {
    User::factory()->create(['email' => 'owner@example.com']);

    $this->artisan('app:create-user')
        ->expectsQuestion('Name', 'Ankit')
        ->expectsQuestion('Email address', 'owner@example.com')
        ->expectsQuestion('Password', 'secret-password')
        ->expectsQuestion('Confirm password', 'different-password')
        ->expectsOutputToContain('The email has already been taken.')
        ->assertFailed();

    expect(User::count())->toBe(1);
});
