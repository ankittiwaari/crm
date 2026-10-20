<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Expenses\SaveExpense;
use App\Enums\ExpenseCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\ExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    /**
     * Expenses for ?month=YYYY-MM (default this month), optionally ?category=.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $month = preg_match('/^\d{4}-\d{2}$/', $request->string('month')->value()) === 1
            ? CarbonImmutable::createFromFormat('!Y-m', $request->string('month')->value())->startOfMonth()
            : CarbonImmutable::today()->startOfMonth();
        $category = ExpenseCategory::tryFrom($request->string('category')->value());

        $expenses = Expense::query()
            ->whereBetween('spent_on', [$month, $month->endOfMonth()])
            ->when($category, fn (Builder $query) => $query->where('category', $category))
            ->latest('spent_on')
            ->latest('id')
            ->get();

        return ExpenseResource::collection($expenses)->additional(['meta' => [
            'month' => $month->format('Y-m'),
            'total' => (int) $expenses->sum('amount'),
            'gst' => (int) $expenses->sum('gst_amount'),
        ]]);
    }

    /**
     * Send as multipart/form-data to attach a receipt.
     */
    public function store(ExpenseRequest $request, SaveExpense $saveExpense): ExpenseResource
    {
        return ExpenseResource::make($saveExpense->handle($request->expenseAttributes(), $request->file('receipt'), user: $request->user()));
    }

    /**
     * To attach a receipt, POST multipart/form-data with _method=PATCH.
     */
    public function update(ExpenseRequest $request, Expense $expense, SaveExpense $saveExpense): ExpenseResource
    {
        return ExpenseResource::make($saveExpense->handle($request->expenseAttributes(), $request->file('receipt'), $request->boolean('remove_receipt'), $expense));
    }

    public function destroy(Expense $expense, SaveExpense $saveExpense): Response
    {
        $saveExpense->delete($expense);

        return response()->noContent();
    }

    public function receipt(Expense $expense): StreamedResponse
    {
        abort_if($expense->receipt_path === null || ! Storage::disk('local')->exists($expense->receipt_path), 404);

        return Storage::disk('local')->download($expense->receipt_path);
    }
}
