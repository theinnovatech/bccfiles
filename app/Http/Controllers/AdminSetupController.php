<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\TurnstileVerifier;
use App\Support\AdminSetup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * First-time setup: create the first administrator account. Only available while the database
 * has no administrator; afterwards the page redirects to sign in.
 */
class AdminSetupController extends Controller
{
    public function __construct(
        private readonly TurnstileVerifier $turnstile,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function show(): View|RedirectResponse
    {
        if (! AdminSetup::required()) {
            return redirect('/');
        }

        return view('login', ['authProps' => [
            'mode' => 'setup',
            'setupKeyRequired' => AdminSetup::key() !== null,
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! AdminSetup::required()) {
            return response()->json(['message' => 'An administrator account already exists. Sign in instead.'], 409);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', AdminSetup::passwordRule()],
            'setup_key' => [AdminSetup::key() !== null ? 'required' : 'nullable', 'string', 'max:255'],
            ...$this->turnstile->rules(),
        ], [
            'email.unique' => 'That email is already used by another account.',
            'password.confirmed' => 'The two passwords do not match.',
            'setup_key.required' => 'Enter the setup key (OBIMS_SETUP_KEY in the server settings).',
        ]);

        if (($key = AdminSetup::key()) !== null && ! hash_equals($key, (string) $data['setup_key'])) {
            throw ValidationException::withMessages(['setup_key' => 'The setup key is incorrect.']);
        }

        $this->turnstile->verify($request);

        // Two people submitting at the same moment cannot both create the first administrator.
        $admin = Cache::lock('obims:first-admin-setup', 15)->block(10, function () use ($data) {
            if (! AdminSetup::required()) {
                return null;
            }

            return User::create([
                'name' => trim($data['name']),
                'email' => trim($data['email']),
                'password' => $data['password'],
                'role' => UserRole::Admin,
                'is_active' => true,
            ]);
        });

        if (! $admin) {
            return response()->json(['message' => 'An administrator account was just created. Sign in instead.'], 409);
        }

        Auth::login($admin);
        $request->session()->regenerate();

        $this->activityLog->log($admin, 'Created', 'Users', "Created the first administrator account ({$admin->email}) during first-time setup");

        return response()->json([
            'message' => 'Administrator account created.',
            'redirect' => '/',
        ], 201);
    }
}
