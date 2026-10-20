<?php

namespace App\Http\Controllers;

use App\Enums\ReminderType;
use App\Http\Resources\ReminderResource;
use App\Models\Client;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReminderController extends Controller
{
    private const array VIEWS = ['due', 'upcoming', 'done'];

    public function index(Request $request): Response
    {
        $view = in_array($request->string('view')->value(), self::VIEWS, true) ? $request->string('view')->value() : 'due';

        $reminders = Reminder::query()
            ->with('client:id,name')
            ->when($view === 'due', fn (Builder $query) => $query->due()->orderBy('due_on'))
            ->when($view === 'upcoming', fn (Builder $query) => $query->pending()->whereDate('due_on', '>', today())->orderBy('due_on'))
            ->when($view === 'done', fn (Builder $query) => $query->whereNotNull('completed_at')->latest('completed_at'))
            ->limit(200)
            ->get();

        return Inertia::render('reminders/index', [
            'reminders' => ReminderResource::collection($reminders)->resolve(),
            'view' => $view,
            'counts' => [
                'due' => Reminder::query()->due()->count(),
                'upcoming' => Reminder::query()->pending()->whereDate('due_on', '>', today())->count(),
            ],
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_on' => ['required', 'date', 'after_or_equal:today'],
            'client_id' => ['nullable', Rule::exists(Client::class, 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'title.required' => 'What do you need to do?',
            'due_on.after_or_equal' => 'Pick today or a later date.',
        ]);

        Reminder::query()->create([...$validated, 'type' => ReminderType::General, 'user_id' => $request->user()?->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reminder added.')]);

        return back();
    }

    /**
     * Mark done or not done, or snooze by a number of days.
     */
    public function update(Request $request, Reminder $reminder): RedirectResponse
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

        return back();
    }

    public function destroy(Reminder $reminder): RedirectResponse
    {
        $reminder->delete();

        return back();
    }
}
