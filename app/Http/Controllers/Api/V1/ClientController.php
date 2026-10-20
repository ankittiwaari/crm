<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Clients\CreateClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ClientRequest;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\ClientResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Client;
use App\Models\Invoice;
use App\Queries\ClientList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ClientController extends Controller
{
    /**
     * Clients with ?search=, ?status=, ?type= and ?sort=recent; status counts are in meta.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $list = new ClientList($request);

        return ClientResource::collection($list->paginate())
            ->additional(['meta' => ['status_counts' => $list->statusCounts()]]);
    }

    public function store(StoreClientRequest $request, CreateClient $createClient): ClientResource
    {
        $client = $createClient->handle($request->clientAttributes(), $request->contactAttributes());

        return ClientResource::make($client->load('contacts'));
    }

    /**
     * The client with contacts, plus subscriptions, recent activity and invoices.
     */
    public function show(Client $client): ClientResource
    {
        $client->load([
            'contacts' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('name'),
            'subscriptions' => fn ($query) => $query
                ->with([
                    'product',
                    'rateRevisions' => fn ($revisions) => $revisions->latest('effective_from')->latest('id'),
                    'periods' => fn ($periods) => $periods->latest('period_start')->limit(6),
                ])
                ->orderBy('current_period_end'),
        ]);

        return ClientResource::make($client)->additional([
            'subscriptions' => SubscriptionResource::collection($client->subscriptions)->resolve(),
            'activities' => ActivityResource::collection(
                $client->activities()->with('user:id,name')->latest('happened_at')->latest('id')->limit(50)->get(),
            )->resolve(),
            'invoices' => InvoiceResource::collection(
                $client->invoices()->latest('issue_date')->latest('id')->limit(10)->get(),
            )->resolve(),
            'outstanding' => $client->invoices()->open()->get()->sum(fn (Invoice $invoice): int => $invoice->balanceDue()),
        ]);
    }

    public function update(ClientRequest $request, Client $client): ClientResource
    {
        $client->update($request->clientAttributes());

        return ClientResource::make($client->load('contacts'));
    }

    /**
     * Archive the client (soft delete).
     */
    public function destroy(Client $client): Response
    {
        $client->delete();

        return response()->noContent();
    }
}
