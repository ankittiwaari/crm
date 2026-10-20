<?php

namespace App\Queries;

use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The client list with search, status, type and sort, shared by the web app and the API.
 */
class ClientList
{
    public readonly string $search;

    public readonly ?ClientStatus $status;

    public readonly ?ClientType $type;

    public readonly string $sort;

    public function __construct(Request $request)
    {
        $this->search = $request->string('search')->trim()->value();
        $this->status = ClientStatus::tryFrom($request->string('status')->value());
        $this->type = ClientType::tryFrom($request->string('type')->value());
        $this->sort = $request->string('sort')->value() === 'recent' ? 'recent' : 'name';
    }

    /**
     * @return LengthAwarePaginator<int, Client>
     */
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return $this->filtered()
            ->with(['primaryContact', 'subscriptions' => fn ($query) => $query->live()])
            ->when($this->status, fn (Builder $query) => $query->where('status', $this->status))
            ->when(
                $this->sort === 'recent',
                fn (Builder $query) => $query->orderByRaw('last_activity_at is null')->latest('last_activity_at'),
                fn (Builder $query) => $query->orderBy('name'),
            )
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * How many clients match the search and type, per status.
     *
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = $this->filtered()
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $result = ['all' => (int) $counts->sum()];

        foreach (ClientStatus::cases() as $case) {
            $result[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }

    /**
     * @return array{search: string, status: string|null, type: string|null, sort: string}
     */
    public function filters(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status?->value,
            'type' => $this->type?->value,
            'sort' => $this->sort,
        ];
    }

    /**
     * @return Builder<Client>
     */
    private function filtered(): Builder
    {
        return Client::query()
            ->search($this->search)
            ->when($this->type, fn (Builder $query) => $query->where('type', $this->type));
    }
}
