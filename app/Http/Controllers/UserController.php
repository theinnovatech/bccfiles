<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    private const ADMIN_EMAIL_DOMAIN = 'obims.admin.com';

    public function __construct(private readonly ActivityLogService $activityLogService) {}

    public function index(): JsonResponse
    {
        return response()->json(
            User::query()
                ->where('role', UserRole::Admin)
                ->with(['department', 'employee'])
                ->orderBy('name')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', Password::defaults()],
            'is_active' => ['boolean'],
        ]);

        $email = $this->normalizeAdminEmail($data['email']);

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This admin email is already taken.'],
            ]);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $email,
            'password' => Hash::make($data['password']),
            'role' => UserRole::Admin,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->activityLogService->log($request->user(), 'Created', 'Users', "Created admin account {$user->name}");

        return response()->json($user->load(['department', 'employee']), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if ($user->role !== UserRole::Admin) {
            return response()->json(['message' => 'Only admin accounts can be managed from this page.'], 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255'],
            'password' => ['nullable', Password::defaults()],
            'is_active' => ['boolean'],
        ]);

        $email = $this->normalizeAdminEmail($data['email']);

        if (User::query()->where('email', $email)->where('id', '!=', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This admin email is already taken.'],
            ]);
        }

        $payload = [
            'name' => $data['name'],
            'email' => $email,
            'role' => UserRole::Admin,
            'is_active' => $data['is_active'] ?? $user->is_active,
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);

        $this->activityLogService->log($request->user(), 'Updated', 'Users', "Updated admin account {$user->name}");

        return response()->json($user->load(['department', 'employee']));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        if ($user->role !== UserRole::Admin) {
            return response()->json(['message' => 'Only admin accounts can be managed from this page.'], 422);
        }

        $name = $user->name;
        $user->delete();
        $this->activityLogService->log($request->user(), 'Deleted', 'Users', "Deleted admin account {$name}");

        return response()->json(['message' => 'Admin account moved to deleted data.']);
    }

    private function normalizeAdminEmail(string $email): string
    {
        $value = strtolower(trim($email));
        $local = strstr($value, '@', true);

        if ($local === false) {
            $local = $value;
        }

        $local = preg_replace('/[^a-z0-9._+-]/', '', $local) ?? '';

        if ($local === '') {
            throw ValidationException::withMessages([
                'email' => ['Please enter a valid email username.'],
            ]);
        }

        return $local.'@'.self::ADMIN_EMAIL_DOMAIN;
    }
}
