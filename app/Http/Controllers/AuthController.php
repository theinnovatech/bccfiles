<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $turnstileEnabled = config('services.turnstile.enabled') && config('services.turnstile.site_key');

        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'cf_turnstile_response' => [$turnstileEnabled ? 'required' : 'nullable', 'string'],
        ]);

        if ($turnstileEnabled) {
            $turnstileResponse = Http::timeout(10)->asForm()->post(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                [
                    'secret'   => config('services.turnstile.secret_key'),
                    'response' => $request->input('cf_turnstile_response'),
                    'remoteip' => $request->ip(),
                ],
            )->json();

            if (! ($turnstileResponse['success'] ?? false)) {
                $errorCodes = $turnstileResponse['error-codes'] ?? [];

                throw ValidationException::withMessages([
                    'cf_turnstile_response' => [
                        match (true) {
                            in_array('invalid-input-response', $errorCodes, true) => 'Security check expired. Please try again.',
                            in_array('invalid-input-secret', $errorCodes, true) => 'Turnstile secret key is invalid. Check TURNSTILE_SECRET_KEY in .env.',
                            in_array('timeout-or-duplicate', $errorCodes, true) => 'Security check already used. Please complete it again.',
                            default => 'Please complete the security check.',
                        },
                    ],
                ]);
            }
        }

        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = User::query()->with(['department', 'employee'])->findOrFail(Auth::id());

        if (! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Logged out successfully.']);
        }

        return redirect('/');
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user()->load(['department', 'employee']);

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'department_id' => $user->department_id,
            'department' => $user->department,
            'employee_id' => $user->employee_id,
            'employee' => $user->employee,
            'is_active' => $user->is_active,
        ];
    }
}
