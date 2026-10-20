<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Registers phones for push notifications (Firebase Cloud Messaging tokens).
 */
class DeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'in:android,ios'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $device = DeviceToken::query()->updateOrCreate(
            ['token' => $validated['token']],
            [...$validated, 'user_id' => $request->user()?->id, 'last_seen_at' => now()],
        );

        return response()->json(['data' => ['id' => $device->id, 'platform' => $device->platform]], $device->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request): Response
    {
        $request->validate(['token' => ['required', 'string']]);

        DeviceToken::query()
            ->where('token', $request->string('token')->value())
            ->where('user_id', $request->user()?->id)
            ->delete();

        return response()->noContent();
    }
}
