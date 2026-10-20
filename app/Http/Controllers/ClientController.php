<?php

namespace App\Http\Controllers;

use App\Actions\Clients\CreateClient;
use App\Enums\ActivityType;
use App\Enums\BillingCycle;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Enums\IndianState;
use App\Enums\PricingModel;
use App\Enums\SubscriptionStatus;
use App\Http\Requests\Clients\ClientRequest;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\ClientResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    /**
     * List clients with search and filters.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();
        $status = ClientStatus::tryFrom($request->string('status')->value());
        $type = ClientType::tryFrom($request->string('type')->value());
        $sort = $request->string('sort')->value() === 'recent' ? 'recent' : 'name';

        $filtered = fn (): Builder => Client::query()
            ->search($search)
            ->when($type, fn (Builder $query) => $query->where('type', $type));

        $clients = $filtered()
            ->with(['primaryContact', 'subscriptions' => fn ($query) => $query->live()])
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when(
                $sort === 'recent',
                fn (Builder $query) => $query->orderByRaw('last_activity_at is null')->latest('last_activity_at'),
                fn (Builder $query) => $query->orderBy('name'),
            )
            ->paginate(25)
            ->withQueryString();

        $statusCounts = $filtered()
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return Inertia::render('clients/index', [
            'clients' => ClientResource::collection($clients),
            'filters' => [
                'search' => $search,
                'status' => $status?->value,
                'type' => $type?->value,
                'sort' => $sort,
            ],
            'statusCounts' => [
                'all' => (int) $statusCounts->sum(),
                ...collect(ClientStatus::cases())->mapWithKeys(fn (ClientStatus $case) => [
                    $case->value => (int) ($statusCounts[$case->value] ?? 0),
                ]),
            ],
            'options' => $this->options(),
        ]);
    }

    /**
     * Store a new client and its first contact.
     */
    public function store(StoreClientRequest $request, CreateClient $createClient): RedirectResponse
    {
        $client = $createClient->handle($request->clientAttributes(), $request->contactAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name added.', ['name' => $client->name])]);

        return to_route('clients.show', $client);
    }

    /**
     * Show the client with contacts and activity timeline.
     */
    public function show(Client $client): Response
    {
        $client->load([
            'contacts' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('name'),
            'subscriptions' => fn ($query) => $query
                ->with(['product', 'rateRevisions' => fn ($revisions) => $revisions->latest('effective_from')->latest('id')])
                ->orderByRaw("case when status in ('active', 'trial') then 0 else 1 end")
                ->orderBy('current_period_end'),
        ]);

        return Inertia::render('clients/show', [
            'client' => ClientResource::make($client)->resolve(),
            'activities' => ActivityResource::collection(
                $client->activities()->with('user:id,name')->latest('happened_at')->latest('id')->limit(100)->get(),
            )->resolve(),
            'subscriptions' => SubscriptionResource::collection($client->subscriptions)->resolve(),
            'invoices' => InvoiceResource::collection(
                $client->invoices()->latest('issue_date')->latest('id')->limit(10)->get(),
            )->resolve(),
            'outstanding' => $client->invoices()->open()->get()->sum(fn (Invoice $invoice): int => $invoice->balanceDue()),
            'products' => Product::query()->active()->orderBy('name')->get()
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'list_price_rupees' => $product->list_price === null ? null : Money::toRupees($product->list_price),
                    'default_billing_cycle' => $product->default_billing_cycle->value,
                    'unit_label' => $product->unit_label,
                ]),
            'options' => [
                ...$this->options(),
                'activityTypes' => ActivityType::options(),
                'subscriptionStatuses' => SubscriptionStatus::options(),
                'billingCycles' => BillingCycle::options(),
                'pricingModels' => PricingModel::options(),
            ],
        ]);
    }

    /**
     * Update the client's details.
     */
    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->clientAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client updated.')]);

        return to_route('clients.show', $client);
    }

    /**
     * Archive the client (soft delete).
     */
    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name archived.', ['name' => $client->name])]);

        return to_route('clients.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'types' => ClientType::options(),
            'statuses' => ClientStatus::options(),
            'states' => IndianState::options(),
        ];
    }
}
