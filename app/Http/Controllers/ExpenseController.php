<?php

namespace App\Http\Controllers;

use App\Actions\Expenses\SaveExpense;
use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Http\Requests\Expenses\ExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Support\FinancialYear;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $this->month($request->string('month')->value());
        $category = ExpenseCategory::tryFrom($request->string('category')->value());
        $year = FinancialYear::for($month);

        $expenses = Expense::query()
            ->whereBetween('spent_on', [$month, $month->endOfMonth()])
            ->when($category, fn (Builder $query) => $query->where('category', $category))
            ->latest('spent_on')
            ->latest('id')
            ->get();

        $yearExpenses = Expense::query()->whereBetween('spent_on', [$year->start(), $year->end()])->get();

        $previousMonth = $month->subMonth();
        $recurringToRepeat = Expense::query()
            ->where('is_recurring', true)
            ->whereBetween('spent_on', [$previousMonth, $previousMonth->endOfMonth()])
            ->get()
            ->reject(fn (Expense $recurring): bool => Expense::query()
                ->where('is_recurring', true)
                ->where('description', $recurring->description)
                ->where('vendor', $recurring->vendor)
                ->whereBetween('spent_on', [$month, $month->endOfMonth()])
                ->exists())
            ->values();

        return Inertia::render('expenses/index', [
            'expenses' => ExpenseResource::collection($expenses)->resolve(),
            'month' => $month->format('Y-m'),
            'category' => $category?->value,
            'summary' => [
                'month_total' => (int) $expenses->sum('amount'),
                'month_gst' => (int) $expenses->sum('gst_amount'),
                'year_total' => (int) $yearExpenses->sum('amount'),
                'year_net' => $yearExpenses->sum(fn (Expense $expense): int => $expense->netCost()),
                'financial_year' => $year->label(),
                'by_category' => $yearExpenses
                    ->groupBy(fn (Expense $expense): string => $expense->category->value)
                    ->map(fn ($items, string $key): array => [
                        'category' => $key,
                        'label' => ExpenseCategory::from($key)->label(),
                        'total' => $items->sum(fn (Expense $expense): int => $expense->netCost()),
                    ])
                    ->sortByDesc('total')
                    ->values(),
            ],
            'recurringToRepeat' => ExpenseResource::collection($recurringToRepeat)->resolve(),
            'categories' => ExpenseCategory::options(),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function store(ExpenseRequest $request, SaveExpense $saveExpense): RedirectResponse
    {
        $expense = $saveExpense->handle($request->expenseAttributes(), $request->file('receipt'), user: $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense added.')]);

        return to_route('expenses.index', ['month' => $expense->spent_on->format('Y-m')]);
    }

    public function update(ExpenseRequest $request, Expense $expense, SaveExpense $saveExpense): RedirectResponse
    {
        $saveExpense->handle($request->expenseAttributes(), $request->file('receipt'), $request->boolean('remove_receipt'), $expense);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense updated.')]);

        return to_route('expenses.index', ['month' => $expense->spent_on->format('Y-m')]);
    }

    public function destroy(Expense $expense, SaveExpense $saveExpense): RedirectResponse
    {
        $saveExpense->delete($expense);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense deleted.')]);

        return back();
    }

    /**
     * Download the stored receipt.
     */
    public function receipt(Expense $expense): StreamedResponse
    {
        abort_if($expense->receipt_path === null || ! Storage::disk('local')->exists($expense->receipt_path), 404);

        return Storage::disk('local')->download($expense->receipt_path);
    }

    private function month(string $value): CarbonImmutable
    {
        return preg_match('/^\d{4}-\d{2}$/', $value) === 1
            ? CarbonImmutable::createFromFormat('!Y-m', $value)->startOfMonth()
            : CarbonImmutable::today()->startOfMonth();
    }
}
