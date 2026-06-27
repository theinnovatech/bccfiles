<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLogService) {}

    public function index(): JsonResponse
    {
        return response()->json(Department::query()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:departments,name'],
            'code' => ['required', 'string', 'max:20', 'unique:departments,code'],
            'is_active' => ['boolean'],
        ]);

        $department = Department::create($data);
        $this->activityLogService->log($request->user(), 'Created', 'Departments', "Created department {$department->name}");

        return response()->json($department, 201);
    }

    public function show(Department $department): JsonResponse
    {
        return response()->json($department->load('employees'));
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:departments,name,'.$department->id],
            'code' => ['required', 'string', 'max:20', 'unique:departments,code,'.$department->id],
            'is_active' => ['boolean'],
        ]);

        $department->update($data);
        $this->activityLogService->log($request->user(), 'Updated', 'Departments', "Updated department {$department->name}");

        return response()->json($department);
    }

    public function destroy(Request $request, Department $department): JsonResponse
    {
        if ($department->employees()->exists() || $department->supplyRequests()->exists()) {
            return response()->json(['message' => 'Cannot delete department with linked records.'], 422);
        }

        $name = $department->name;
        $department->delete();
        $this->activityLogService->log($request->user(), 'Deleted', 'Departments', "Deleted department {$name}");

        return response()->json(['message' => 'Department moved to deleted data.']);
    }
}
