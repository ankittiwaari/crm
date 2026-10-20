<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Reports\FinancialReport;
use App\Support\FinancialYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Figures for ?year=2026-27 (default the current financial year).
     */
    public function __invoke(Request $request, FinancialReport $report): JsonResponse
    {
        $year = FinancialYear::fromLabel($request->string('year')->value()) ?? FinancialYear::current();

        return response()->json(['data' => [
            'year' => $year->label(),
            ...$report->forYear($year),
            'ageing' => $report->receivablesAgeing(),
            'pricing' => $report->pricing(),
        ]]);
    }
}
