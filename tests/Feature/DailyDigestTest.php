<?php

use App\Models\Invoice;
use App\Models\Reminder;
use App\Models\User;
use App\Notifications\DailyDigest;
use Illuminate\Support\Facades\Notification;

test('the morning summary is emailed when something needs attention', function () {
    Notification::fake();
    $user = User::factory()->create();
    Reminder::factory()->create(['title' => 'Call Green Valley']);
    Invoice::factory()->overdueBy(5)->create(['number' => 'INV/2026-27/0007']);

    $this->artisan('crm:daily-digest')->assertSuccessful();

    Notification::assertSentTo($user, DailyDigest::class, function (DailyDigest $digest) use ($user) {
        $mail = $digest->toMail($user);

        return $digest->reminders->count() === 1
            && $digest->overdue->count() === 1
            && str_contains(implode("\n", $mail->introLines), 'Call Green Valley')
            && str_contains(implode("\n", $mail->introLines), 'INV/2026-27/0007');
    });
});

test('no email is sent on a quiet day', function () {
    Notification::fake();
    User::factory()->create();

    $this->artisan('crm:daily-digest')->expectsOutputToContain('Nothing to report today')->assertSuccessful();

    Notification::assertNothingSent();
});
