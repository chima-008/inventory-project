<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'stock_status' => [
                'nullable',
                'string',
                'in:in_stock,low_stock,out_of_stock',
            ],
        ]);

        $products = Product::query()
            ->where('business_id', $request->user()->business_id)
            ->with('category')
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('sku', 'ilike', "%{$search}%");
                });
            })
            ->when(
                $validated['category_id'] ?? null,
                function ($query, $categoryId) use ($request) {
                    $query
                        ->where('category_id', $categoryId)
                        ->whereHas('category', function ($categoryQuery) use ($request) {
                            $categoryQuery->where(
                                'business_id',
                                $request->user()->business_id
                            );
                        });
                }
            )
            ->when($validated['stock_status'] ?? null, function ($query, $status) {
                match ($status) {
                    'out_of_stock' => $query->where('stock_quantity', 0),

                    'low_stock' => $query
                        ->where('stock_quantity', '>', 0)
                        ->whereColumn(
                            'stock_quantity',
                            '<=',
                            'low_stock_threshold'
                        ),

                    'in_stock' => $query
                        ->whereColumn(
                            'stock_quantity',
                            '>',
                            'low_stock_threshold'
                        ),

                    default => null,
                };
            })
            ->orderBy('name')
            ->get();

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request)
    {
        $validated = $request->validated();

        $product = Product::create([
            'business_id' => $request->user()->business_id,
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'sku' => $validated['sku'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'stock_quantity' => $validated['stock_quantity'],
            'low_stock_threshold' => $validated['low_stock_threshold'],
            'is_active' => $validated['is_active'],
        ]);

        $product->load('category');

        return ProductResource::make($product)
            ->additional([
                'message' => 'Product created successfully.',
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Product $product)
    {
        abort_unless(
            $product->business_id === $request->user()->business_id,
            404
        );

        $product->load('category');

        return ProductResource::make($product);
    }

    public function update(
        UpdateProductRequest $request,
        Product $product
    ) {
        abort_unless(
            $product->business_id === $request->user()->business_id,
            404
        );

        $validated = $request->validated();

        $product->update([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'sku' => $validated['sku'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'low_stock_threshold' => $validated['low_stock_threshold'],
            'is_active' => $validated['is_active'],
        ]);

        $product->load('category');

        return ProductResource::make($product);
    }

    public function destroy(Request $request, Product $product)
    {
        abort_unless(
            $product->business_id === $request->user()->business_id,
            404
        );

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }

    public function lowStock(Request $request): JsonResponse
    {
        $products = Product::query()
            ->where('business_id', $request->user()->business_id)
            ->with('category')
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity')
            ->get();

        return ProductResource::collection($products);
    }
}