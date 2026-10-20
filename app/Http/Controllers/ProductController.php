<?php

namespace App\Http\Controllers;

use App\Enums\BillingCycle;
use App\Http\Requests\Products\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('products/index', [
            'products' => ProductResource::collection(
                Product::query()
                    ->with(['subscriptions' => fn ($query) => $query->live()])
                    ->orderByDesc('is_active')
                    ->orderBy('name')
                    ->get(),
            )->resolve(),
            'billingCycles' => BillingCycle::options(),
            'taxRates' => ProductRequest::TAX_RATES,
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::create($request->productAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name added.', ['name' => $product->name])]);

        return to_route('products.index');
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->productAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name updated.', ['name' => $product->name])]);

        return to_route('products.index');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name deleted.', ['name' => $product->name])]);

        return to_route('products.index');
    }
}
