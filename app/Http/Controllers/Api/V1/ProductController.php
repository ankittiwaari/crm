<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ProductResource::collection(
            Product::query()
                ->with(['subscriptions' => fn ($query) => $query->live()])
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
        );
    }
}
