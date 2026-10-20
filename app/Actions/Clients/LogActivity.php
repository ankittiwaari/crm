<?php

namespace App\Actions\Clients;

use App\Enums\ReminderType;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LogActivity
{
    /**
     * Add an entry to the client's timeline, keep "last contact" up to date and turn a
     * follow-up date into a reminder.
     *
     * @param  array<string, mixed>  $data  Validated input.
     */
    public function handle(Client $client, array $data, ?User $user = null): Activity
    {
        return DB::transaction(function () use ($client, $data, $user): Activity {
            $activity = $client->activities()->create([
                ...$data,
                'happened_at' => $data['happened_at'] ?? now(),
                'user_id' => $user?->id,
            ]);

            $this->refreshLastActivity($client);

            if ($activity->follow_up_on !== null) {
                Reminder::query()->create([
                    'client_id' => $client->id,
                    'user_id' => $user?->id,
                    'type' => ReminderType::FollowUp,
                    'title' => 'Follow up with '.$client->name.': '.Str::limit($activity->body, 80),
                    'due_on' => $activity->follow_up_on,
                    'dedupe_key' => 'activity:'.$activity->id,
                ])->remindable()->associate($activity)->save();
            }

            return $activity;
        });
    }

    public function delete(Activity $activity): void
    {
        DB::transaction(function () use ($activity): void {
            Reminder::query()->whereMorphedTo('remindable', $activity)->pending()->delete();
            $activity->delete();

            $this->refreshLastActivity($activity->client);
        });
    }

    private function refreshLastActivity(Client $client): void
    {
        $client->forceFill([
            'last_activity_at' => $client->activities()->max('happened_at'),
        ])->save();
    }
}
