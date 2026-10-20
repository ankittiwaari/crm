<?php

namespace App\Queries;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Support\FinancialYear;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The invoice list with status filter and search, shared by the web app and the API.
 */
class InvoiceList
{
    public const array FILTERS = ['all', 'draft', 'unpaid', 'overdue', 'paid', 'cancelled'];

    public readonly string $filter;

    public readonly string $search;

    public readonly ?int $clientId;

    public function __construct(Request $request)
    {
        $filter = $request->string('filter')->value();

        $this->filter = in_array($filter, self::FILTERS, true) ? $filter : 'all';
        $this->search = $request->string('search')->trim()->value();
        $this->clientId = $request->integer('client_id') ?: null;
    }

    /**
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $this->search).'%';

        return Invoice::query()
            ->with('client:id,name')
            ->when($this->clientId, fn (Builder $query) => $query->where('client_id', $this->clientId))
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', $like)
                ->orWhereHas('client', fn (Builder $client) => $client->withTrashed()->where('name', 'like', $like))))
            ->when($this->filter === 'draft', fn (Builder $query) => $query->where('status', InvoiceStatus::Draft))
            ->when($this->filter === 'unpaid', fn (Builder $query) => $query->open())
            ->when($this->filter === 'overdue', fn (Builder $query) => $query->overdue())
            ->when($this->filter === 'paid', fn (Builder $query) => $query->where('status', InvoiceStatus::Paid))
            ->when($this->filter === 'cancelled', fn (Builder $query) => $query->where('status', InvoiceStatus::Cancelled))
            ->orderByRaw("case when status = 'draft' then 0 else 1 end")
            ->latest('issue_date')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Money owed overall and invoiced this financial year.
     *
     * @return array{outstanding: int, outstanding_count: int, overdue: int, overdue_count: int, invoiced_this_year: int, financial_year: string}
     */
    public static function summary(): array
    {
        $year = FinancialYear::current();
        $open = Invoice::query()->open()->get(['total', 'amount_settled', 'due_date', 'status']);
        $overdue = $open->filter(fn (Invoice $invoice): bool => $invoice->isOverdue());

        return [
            'outstanding' => $open->sum(fn (Invoice $invoice): int => $invoice->balanceDue()),
            'outstanding_count' => $open->count(),
            'overdue' => $overdue->sum(fn (Invoice $invoice): int => $invoice->balanceDue()),
            'overdue_count' => $overdue->count(),
            'invoiced_this_year' => (int) Invoice::query()
                ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])
                ->whereBetween('issue_date', [$year->start(), $year->end()])
                ->sum('total'),
            'financial_year' => $year->label(),
        ];
    }

    /**
     * @return array{filter: string, search: string}
     */
    public function filters(): array
    {
        return ['filter' => $this->filter, 'search' => $this->search];
    }
}
