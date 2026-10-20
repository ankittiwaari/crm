<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardData;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(DashboardData $dashboard): JsonResponse
    {
        return response()->json(['data' => $dashboard->build()]);
    }
}
