<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\StorageLocation;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorageLocationController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLogService) {}

    public function index(): JsonResponse
    {
        return response()->json(StorageLocation::query()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:storage_locations,code'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $location = StorageLocation::create($data);
        $this->activityLogService->log($request->user(), 'Created', 'Locations', "Created location {$location->name}");

        return response()->json($location, 201);
    }

    public function show(StorageLocation $storageLocation): JsonResponse
    {
        return response()->json($storageLocation);
    }

    public function update(Request $request, StorageLocation $storageLocation): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:storage_locations,code,'.$storageLocation->id],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $storageLocation->update($data);
        $this->activityLogService->log($request->user(), 'Updated', 'Locations', "Updated location {$storageLocation->name}");

        return response()->json($storageLocation);
    }

    public function destroy(Request $request, StorageLocation $storageLocation): JsonResponse
    {
        if ($storageLocation->items()->exists()) {
            return response()->json(['message' => 'Cannot delete location with linked items.'], 422);
        }

        $name = $storageLocation->name;
        $storageLocation->delete();
        $this->activityLogService->log($request->user(), 'Deleted', 'Locations', "Deleted location {$name}");

        return response()->json(['message' => 'Location moved to deleted data.']);
    }
}
