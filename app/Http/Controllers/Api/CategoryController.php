<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::query()
            ->where('business_id', $request->user()->business_id)
            ->withCount('products')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request)
    {
        $validated = $request->validated();

        $category = Category::create([
            'business_id' => $request->user()->business_id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
        ]);

        return CategoryResource::make($category)
            ->additional([
                'message' => 'Category created successfully.',
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Category $category)
    {
        abort_unless(
            $category->business_id === $request->user()->business_id,
            404
        );

        $category->loadCount('products');

        return CategoryResource::make($category);
    }

    public function update(
        UpdateCategoryRequest $request,
        Category $category
    ) {
        abort_unless(
            $category->business_id === $request->user()->business_id,
            404
        );

        $validated = $request->validated();

        $category->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
        ]);

        return CategoryResource::make($category);
    }

    public function destroy(Request $request, Category $category)
    {
        abort_unless(
            $category->business_id === $request->user()->business_id,
            404
        );

        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a category that has products.',
            ], 409);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }
}