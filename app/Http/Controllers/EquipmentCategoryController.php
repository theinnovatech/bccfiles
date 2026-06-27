<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EquipmentCategory;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentCategoryController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLogService) {}

    public function index(): JsonResponse
    {
        return response()->json(
            EquipmentCategory::query()->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:equipment_categories,name'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $category = EquipmentCategory::create($data);

        $this->activityLogService->log(
            $request->user(),
            'Created',
            'Equipment Categories',
            "Created equipment category {$category->name}"
        );

        return response()->json($category, 201);
    }

    public function show(EquipmentCategory $equipmentCategory): JsonResponse
    {
        return response()->json($equipmentCategory);
    }

    public function update(Request $request, EquipmentCategory $equipmentCategory): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:equipment_categories,name,'.$equipmentCategory->id],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $equipmentCategory->update($data);

        $this->activityLogService->log(
            $request->user(),
            'Updated',
            'Equipment Categories',
            "Updated equipment category {$equipmentCategory->name}"
        );

        return response()->json($equipmentCategory);
    }

    public function destroy(Request $request, EquipmentCategory $equipmentCategory): JsonResponse
    {
        if ($equipmentCategory->equipments()->exists()) {
            return response()->json(['message' => 'Cannot delete category with linked equipments.'], 422);
        }

        $name = $equipmentCategory->name;
        $equipmentCategory->delete();

        $this->activityLogService->log(
            $request->user(),
            'Deleted',
            'Equipment Categories',
            "Deleted equipment category {$name}"
        );

        return response()->json(['message' => 'Equipment category moved to deleted data.']);
    }
}
