<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
   public function summary(): JsonResponse
{
    $activeProducts = Product::query()
        ->where('is_active', true);

    $allProducts = Product::query();

    $totalProducts = (clone $activeProducts)->count();

    $totalStockUnits = (clone $activeProducts)
        ->sum('stock_quantity');

    $lowStockCount = (clone $allProducts)
        ->where('stock_quantity', '>', 0)
        ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
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
            'total_categories' => Category::count(),
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