<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Invoice;
use App\Services\Reports\CsvExports;
use App\Services\Reports\FinancialReport;
use App\Support\FinancialYear;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, FinancialReport $report): Response
    {
        $year = FinancialYear::fromLabel($request->string('year')->value()) ?? FinancialYear::current();

        return Inertia::render('reports/index', [
            'year' => $year->label(),
            'yearStart' => $year->start()->toDateString(),
            'yearEnd' => $year->end()->toDateString(),
            'years' => $this->availableYears(),
            ...$report->forYear($year),
            'ageing' => $report->receivablesAgeing(),
            'pricing' => $report->pricing(),
            'exportTypes' => collect(CsvExports::TYPES)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    /**
     * Download a CSV for a date range.
     */
    public function export(Request $request, CsvExports $exports): StreamedResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(CsvExports::TYPES))],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = CarbonImmutable::parse($validated['from'])->startOfDay();
        $to = CarbonImmutable::parse($validated['to'])->startOfDay();
        [$header, $rows] = $exports->build($validated['type'], $from, $to);

        $fileName = sprintf('%s_%s_to_%s.csv', $validated['type'], $from->toDateString(), $to->toDateString());

        return response()->streamDownload(function () use ($header, $rows): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fwrite($output, "\u{FEFF}");
            fputcsv($output, $header, escape: '');

            foreach ($rows as $row) {
                fputcsv($output, $row, escape: '');
            }

            fclose($output);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Financial years from the first invoice or expense up to the current one, newest first.
     *
     * @return list<string>
     */
    private function availableYears(): array
    {
        $first = collect([
            Invoice::query()->min('issue_date'),
            Expense::query()->min('spent_on'),
        ])->filter()->map(fn (string $date) => CarbonImmutable::parse($date))->min();

        $year = FinancialYear::current();
        $oldest = $first ? FinancialYear::for($first) : $year;
        $years = [];

        while ($year->startYear >= $oldest->startYear) {
            $years[] = $year->label();
            $year = $year->previous();
        }

        return $years;
    }
}
