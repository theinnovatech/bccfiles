<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLogService) {}

    public function index(): JsonResponse
    {
        return response()->json(Category::query()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $category = Category::create($data);
        $this->activityLogService->log($request->user(), 'Created', 'Categories', "Created category {$category->name}");

        return response()->json($category, 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json($category);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name,'.$category->id],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $category->update($data);
        $this->activityLogService->log($request->user(), 'Updated', 'Categories', "Updated category {$category->name}");

        return response()->json($category);
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        if ($category->items()->exists()) {
            return response()->json(['message' => 'Cannot delete category with linked items.'], 422);
        }

        $name = $category->name;
        $category->delete();
        $this->activityLogService->log($request->user(), 'Deleted', 'Categories', "Deleted category {$name}");

        return response()->json(['message' => 'Category moved to deleted data.']);
    }
}
