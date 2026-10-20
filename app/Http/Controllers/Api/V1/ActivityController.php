<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Clients\LogActivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\Client;
use Illuminate\Http\Response;

class ActivityController extends Controller
{
    public function store(ActivityRequest $request, Client $client, LogActivity $logActivity): ActivityResource
    {
        return ActivityResource::make($logActivity->handle($client, $request->validated(), $request->user()));
    }

    public function destroy(Activity $activity, LogActivity $logActivity): Response
    {
        $logActivity->delete($activity);

        return response()->noContent();
    }
}
