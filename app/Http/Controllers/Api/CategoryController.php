<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Resources\CategoryResource;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request)
    {
        $validated = $request->validated();

        $category = Category::create($validated);

       return CategoryResource::make($category)
        ->additional([
            'message' => 'Category created successfully.',
        ])
        ->response()
        ->setStatusCode(201);
    }

    public function show(Category $category)
    {
        $category->loadCount('products');

        return CategoryResource::make($category);
    }

   public function update(
        UpdateCategoryRequest $request,
        Category $category
    )
    {
        $validated = $request->validated();

        $category->update($validated);

        return CategoryResource::make($category);
    }

       public function destroy(Category $category)
    {
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