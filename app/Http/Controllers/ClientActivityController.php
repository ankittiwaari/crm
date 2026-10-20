<?php

namespace App\Http\Controllers;

use App\Actions\Clients\LogActivity;
use App\Http\Requests\Clients\ActivityRequest;
use App\Models\Activity;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ClientActivityController extends Controller
{
    public function store(ActivityRequest $request, Client $client, LogActivity $logActivity): RedirectResponse
    {
        $logActivity->handle($client, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logged.')]);

        return to_route('clients.show', $client);
    }

    public function destroy(Activity $activity, LogActivity $logActivity): RedirectResponse
    {
        $logActivity->delete($activity);

        return to_route('clients.show', $activity->client_id);
    }
}
