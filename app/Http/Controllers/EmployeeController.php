<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\ReferenceNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLogService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Employee::query()
            ->with(['department', 'user'])
            ->orderBy('name');

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'position'      => ['nullable', 'string', 'max:255'],
        ]);

        $employee = Employee::create([
            'employee_number' => ReferenceNumberGenerator::forEmployee(),
            'name'            => $data['name'],
            'department_id'   => $data['department_id'],
            'position'        => $data['position'] ?? null,
            'contact_email'   => $data['contact_email'] ?? null,
        ]);

        $this->activityLogService->log($request->user(), 'Created', 'Employees', "Created employee {$employee->name}");

        return response()->json([
            'data'    => $employee->load(['department', 'user']),
            'message' => 'Employee created successfully.',
        ], 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        return response()->json($employee->load(['department', 'user']));
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'position'      => ['nullable', 'string', 'max:255'],
        ]);

        $employee->update([
            'name'          => $data['name'],
            'contact_email' => $data['contact_email'] ?? null,
            'department_id' => $data['department_id'],
            'position'      => $data['position'] ?? null,
        ]);

        $this->activityLogService->log($request->user(), 'Updated', 'Employees', "Updated employee {$employee->name}");

        return response()->json([
            'data'    => $employee->fresh()->load(['department', 'user']),
            'message' => 'Employee updated successfully.',
        ]);
    }

    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        $name = $employee->name;

        $linkedUser = User::query()
            ->where('employee_id', $employee->id)
            ->when($employee->user_id, fn ($query) => $query->orWhere('id', $employee->user_id))
            ->first();

        if ($linkedUser && $linkedUser->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete an employee linked to your own account.'], 422);
        }

        DB::transaction(function () use ($employee, $linkedUser) {
            if ($linkedUser) {
                $linkedUser->delete();
            }

            $employee->delete();
        });

        $this->activityLogService->log($request->user(), 'Deleted', 'Employees', "Deleted employee {$name}");

        return response()->json(['message' => 'Employee moved to deleted data.']);
    }
}
