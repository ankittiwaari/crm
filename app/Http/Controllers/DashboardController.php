<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardData;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the "Today" dashboard.
     */
    public function __invoke(DashboardData $dashboard): Response
    {
        return Inertia::render('dashboard', $dashboard->build());
    }
}
