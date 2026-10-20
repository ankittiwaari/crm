<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReminderType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReminderResource;
use App\Models\Client;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ReminderController extends Controller
{
    /**
     * Reminders: ?view=due (default, today or earlier), upcoming or done.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $view = $request->string('view')->value();

        $reminders = Reminder::query()
            ->with('client:id,name')
            ->when($view === 'upcoming', fn (Builder $query) => $query->pending()->whereDate('due_on', '>', today())->orderBy('due_on'))
            ->when($view === 'done', fn (Builder $query) => $query->whereNotNull('completed_at')->latest('completed_at'))
            ->when(! in_array($view, ['upcoming', 'done'], true), fn (Builder $query) => $query->due()->orderBy('due_on'))
            ->paginate(50);

        return ReminderResource::collection($reminders)->additional(['meta' => [
            'due_count' => Reminder::query()->due()->count(),
        ]]);
    }

    public function store(Request $request): ReminderResource
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_on' => ['required', 'date', 'after_or_equal:today'],
            'client_id' => ['nullable', Rule::exists(Client::class, 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $reminder = Reminder::query()->create([...$validated, 'type' => ReminderType::General, 'user_id' => $request->user()?->id]);

        return ReminderResource::make($reminder->load('client:id,name'));
    }

    /**
     * Mark done (done=true/false) or snooze (snooze_days).
     */
    public function update(Request $request, Reminder $reminder): ReminderResource
    {
        $validated = $request->validate([
            'done' => ['sometimes', 'boolean'],
            'snooze_days' => ['sometimes', 'integer', 'min:1', 'max:90'],
        ]);

        if (array_key_exists('done', $validated)) {
            $reminder->completed_at = $validated['done'] ? now() : null;
        }

        if (array_key_exists('snooze_days', $validated)) {
            $reminder->due_on = today()->addDays((int) $validated['snooze_days']);
            $reminder->completed_at = null;
        }

        $reminder->save();

        return ReminderResource::make($reminder->load('client:id,name'));
    }

    public function destroy(Reminder $reminder): Response
    {
        $reminder->delete();

        return response()->noContent();
    }
}
