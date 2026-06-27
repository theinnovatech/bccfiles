<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\EmployeeCredentialsMail;
use App\Models\Employee;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\ReferenceNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

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
            'contact_email' => ['required', 'email', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'position'      => ['nullable', 'string', 'max:255'],
            'role'          => ['required', Rule::enum(UserRole::class)],
        ]);

        $credentialsToSend = null;

        $employee = DB::transaction(function () use ($data, &$credentialsToSend) {
            $employee = Employee::create([
                'employee_number' => ReferenceNumberGenerator::forEmployee(),
                'name'            => $data['name'],
                'department_id'   => $data['department_id'],
                'position'        => $data['position'] ?? null,
                'contact_email'   => $data['contact_email'],
                'role'            => $data['role'],
            ]);

            if ($data['role'] === UserRole::DepartmentUser->value) {
                $plainPassword = Str::password(10, symbols: false);

                $user = User::create([
                    'name'          => $data['name'],
                    'email'         => $data['contact_email'],
                    'password'      => Hash::make($plainPassword),
                    'role'          => UserRole::DepartmentUser,
                    'department_id' => $data['department_id'],
                    'employee_id'   => $employee->id,
                    'is_active'     => true,
                ]);

                $employee->update(['user_id' => $user->id]);

                $credentialsToSend = [
                    'name'          => $data['name'],
                    'contact_email' => $data['contact_email'],
                    'plainPassword' => $plainPassword,
                ];
            }

            return $employee;
        });

        $mailSent = $credentialsToSend
            ? $this->sendCredentialsMail($credentialsToSend)
            : true;

        $this->activityLogService->log($request->user(), 'Created', 'Employees', "Created employee {$employee->name}");

        $message = $mailSent
            ? ($credentialsToSend ? 'Employee created. Login credentials sent to contact email.' : 'Employee created successfully.')
            : 'Employee created, but credentials email could not be sent. Check mail settings or regenerate your Gmail App Password.';

        return response()->json([
            'data'      => $employee->load(['department', 'user']),
            'mail_sent' => $mailSent,
            'message'   => $message,
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
            'contact_email' => ['required', 'email', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'position'      => ['nullable', 'string', 'max:255'],
            'role'          => ['required', Rule::enum(UserRole::class)],
        ]);

        $credentialsToSend = null;

        DB::transaction(function () use ($data, $employee, &$credentialsToSend) {
            $employee->update([
                'name'          => $data['name'],
                'contact_email' => $data['contact_email'],
                'department_id' => $data['department_id'],
                'position'      => $data['position'] ?? null,
                'role'          => $data['role'],
            ]);

            if ($employee->user) {
                $employee->user->update([
                    'name'          => $data['name'],
                    'email'         => $data['contact_email'],
                    'department_id' => $data['department_id'],
                ]);
            }

            if ($data['role'] === UserRole::DepartmentUser->value && ! $employee->user_id) {
                $plainPassword = Str::password(10, symbols: false);

                $user = User::create([
                    'name'          => $data['name'],
                    'email'         => $data['contact_email'],
                    'password'      => Hash::make($plainPassword),
                    'role'          => UserRole::DepartmentUser,
                    'department_id' => $data['department_id'],
                    'employee_id'   => $employee->id,
                    'is_active'     => true,
                ]);

                $employee->update(['user_id' => $user->id]);

                $credentialsToSend = [
                    'name'          => $data['name'],
                    'contact_email' => $data['contact_email'],
                    'plainPassword' => $plainPassword,
                ];
            }
        });

        $mailSent = $credentialsToSend
            ? $this->sendCredentialsMail($credentialsToSend)
            : true;

        $this->activityLogService->log($request->user(), 'Updated', 'Employees', "Updated employee {$employee->name}");

        $message = $mailSent
            ? ($credentialsToSend ? 'Employee updated. Login credentials sent to contact email.' : 'Employee updated successfully.')
            : 'Employee updated, but credentials email could not be sent. Check mail settings or regenerate your Gmail App Password.';

        return response()->json([
            'data'      => $employee->fresh()->load(['department', 'user']),
            'mail_sent' => $mailSent,
            'message'   => $message,
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

        return response()->json(['message' => 'Employee and linked user account moved to deleted data.']);
    }

    private function sendCredentialsMail(array $credentials): bool
    {
        try {
            Mail::to($credentials['contact_email'])->send(new EmployeeCredentialsMail(
                employeeName:  $credentials['name'],
                loginEmail:    $credentials['contact_email'],
                plainPassword: $credentials['plainPassword'],
                appName:       config('app.name'),
                appUrl:        config('app.url'),
            ));

            return true;
        } catch (Throwable $e) {
            Log::error('Failed to send employee credentials email.', [
                'contact_email' => $credentials['contact_email'],
                'error'         => $e->getMessage(),
            ]);

            return false;
        }
    }
}
