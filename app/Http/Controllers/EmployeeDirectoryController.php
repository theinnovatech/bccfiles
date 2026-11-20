<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\ActivityLogService;
use App\Support\ReferenceNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Revision 5: unit employee directory (people from other units who request or receive stock).
 * Directory entries do not need a login account.
 */
class EmployeeDirectoryController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLogService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Employee::query()
            ->with('department:id,name,code')
            ->orderBy('name');

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->get()->map(fn (Employee $employee) => $this->present($employee))->values()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $employee = Employee::create([
            'employee_number' => $data['employee_number'] ?: ReferenceNumberGenerator::forEmployee(),
            'name' => $data['name'],
            'department_id' => $data['department_id'],
            'position' => $data['position'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->activityLogService->log(
            $request->user(),
            'Created',
            'Employee Directory',
            "Added {$employee->name} ({$employee->employee_number}) to the employee directory"
        );

        return response()->json($this->present($employee->load('department')), 201);
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $data = $this->validated($request, $employee);

        $employee->update([
            'employee_number' => $data['employee_number'] ?: $employee->employee_number,
            'name' => $data['name'],
            'department_id' => $data['department_id'],
            'position' => $data['position'] ?? null,
            'is_active' => $data['is_active'] ?? $employee->is_active,
        ]);

        // Keep a linked login account's display name/unit in step with the directory.
        $employee->user?->update([
            'name' => $employee->name,
            'department_id' => $employee->department_id,
        ]);

        $this->activityLogService->log(
            $request->user(),
            'Updated',
            'Employee Directory',
            "Updated directory entry {$employee->name} ({$employee->employee_number})"
        );

        return response()->json($this->present($employee->fresh()->load('department')));
    }

    public function setActive(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $employee->update(['is_active' => $data['is_active']]);

        $this->activityLogService->log(
            $request->user(),
            $data['is_active'] ? 'Activated' : 'Deactivated',
            'Employee Directory',
            ($data['is_active'] ? 'Activated' : 'Deactivated')." directory entry {$employee->name} ({$employee->employee_number})"
        );

        return response()->json($this->present($employee->fresh()->load('department')));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Employee $employee = null): array
    {
        $data = $request->validate([
            'employee_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_number')->ignore($employee?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['required', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'position' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'department_id.required' => 'Select the employee\'s unit.',
        ]);

        $data['employee_number'] = trim((string) ($data['employee_number'] ?? ''));
        $data['name'] = trim($data['name']);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'name' => $employee->name,
            'department_id' => $employee->department_id,
            'unit' => $employee->department?->name,
            'unit_code' => $employee->department?->code,
            'position' => $employee->position,
            'is_active' => (bool) $employee->is_active,
            'has_account' => (bool) $employee->user_id,
            'updated_at' => optional($employee->updated_at)->toIso8601String(),
        ];
    }
}
