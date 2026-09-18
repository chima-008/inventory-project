<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\StockMovementResource;
use App\Http\Requests\StoreStockMovementRequest;

class StockMovementController extends Controller
{
    public function index(Product $product)
    {
       $movements = $product->stockMovements()
        ->with(['product', 'user'])
        ->latest()
        ->get();

       return StockMovementResource::collection($movements);
    }

   public function store(
        StoreStockMovementRequest $request,
        Product $product
    ): JsonResponse
    {
        $validated = $request->validated();

        $movement = DB::transaction(function () use ($validated, $product, $request) {
            $product = Product::query()
                ->lockForUpdate()
                ->findOrFail($product->id);

            $newStock = match ($validated['type']) {
                'in' => $product->stock_quantity + $validated['quantity'],

                'out' => $product->stock_quantity - $validated['quantity'],
            };

            if ($newStock < 0) {
                abort(422, 'Insufficient stock for this operation.');
            }

            $product->stock_quantity = $newStock;
            $product->save();

            return StockMovement::create([
                'product_id' => $product->id,
                'user_id' => $request->user()->id,
                'type' => $validated['type'],
                'quantity' => $validated['quantity'],
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        $movement->load(['product', 'user']);

       return StockMovementResource::make($movement)
        ->additional([
            'message' => 'Stock movement recorded successfully.',
        ])
        ->response()
        ->setStatusCode(201);
        }
}