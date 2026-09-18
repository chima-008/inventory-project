<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Resources\ProductResource;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;

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
            ->with('category')
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('sku', 'ilike', "%{$search}%");
                });
            })
            ->when($validated['category_id'] ?? null, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
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
        $product = Product::create($validated);

        $product->load('category');

        return ProductResource::make($product)
        ->additional([
            'message' => 'Product created successfully.',
        ])
        ->response()
        ->setStatusCode(201);
    }

    public function show(Product $product)
    {
        $product->load('category');

        return ProductResource::make($product);
    }

  public function update(
    UpdateProductRequest $request,
    Product $product
)
    {

        $validated = $request->validated();

        $product->update($validated);

        $product->load('category');
        
        return ProductResource::make($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }

    public function lowStock(): JsonResponse
    {
        $products = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity')
            ->get();

        return ProductResource::collection($products);
    }
}