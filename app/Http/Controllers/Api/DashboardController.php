<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $businessId = $request->user()->business_id;

        $activeProducts = Product::query()
            ->where('business_id', $businessId)
            ->where('is_active', true);

        $allProducts = Product::query()
            ->where('business_id', $businessId);

        $totalProducts = (clone $activeProducts)->count();

        $totalStockUnits = (clone $activeProducts)
            ->sum('stock_quantity');

        $lowStockCount = (clone $allProducts)
            ->where('stock_quantity', '>', 0)
            ->whereColumn(
                'stock_quantity',
                '<=',
                'low_stock_threshold'
            )
            ->count();

        $outOfStockCount = (clone $allProducts)
            ->where('stock_quantity', 0)
            ->count();

        $inventoryValue = (clone $activeProducts)
            ->selectRaw(
                'COALESCE(SUM(stock_quantity * price), 0) as value'
            )
            ->value('value');

        return response()->json([
            'data' => [
                'total_products' => $totalProducts,
                'total_categories' => Category::query()
                    ->where('business_id', $businessId)
                    ->count(),
                'total_stock_units' => $totalStockUnits,
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
                'inventory_value' => number_format(
                    (float) $inventoryValue,
                    2,
                    '.',
                    ''
                ),
            ],
        ]);
    }
}