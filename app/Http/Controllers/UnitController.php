<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLogService) {}

    public function index(): JsonResponse
    {
        return response()->json(Unit::query()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:units,name'],
            'abbreviation' => ['required', 'string', 'max:20'],
        ]);

        $unit = Unit::create($data);
        $this->activityLogService->log($request->user(), 'Created', 'Units', "Created unit {$unit->name}");

        return response()->json($unit, 201);
    }

    public function show(Unit $unit): JsonResponse
    {
        return response()->json($unit);
    }

    public function update(Request $request, Unit $unit): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:units,name,'.$unit->id],
            'abbreviation' => ['required', 'string', 'max:20'],
        ]);

        $unit->update($data);
        $this->activityLogService->log($request->user(), 'Updated', 'Units', "Updated unit {$unit->name}");

        return response()->json($unit);
    }

    public function destroy(Request $request, Unit $unit): JsonResponse
    {
        if ($unit->items()->exists()) {
            return response()->json(['message' => 'Cannot delete unit with linked items.'], 422);
        }

        $name = $unit->name;
        $unit->delete();
        $this->activityLogService->log($request->user(), 'Deleted', 'Units', "Deleted unit {$name}");

        return response()->json(['message' => 'Unit moved to deleted data.']);
    }
}
