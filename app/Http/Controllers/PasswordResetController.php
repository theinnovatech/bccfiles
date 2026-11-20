<?php

namespace App\Http\Controllers;

use App\Services\PasswordResetService;
use App\Services\TurnstileVerifier;
use App\Support\AdminSetup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Forgot password: request a code, check it, then choose a new password. The emailed link opens
 * /reset-password with a one-time token instead of a code.
 */
class PasswordResetController extends Controller
{
    public function __construct(
        private readonly PasswordResetService $resets,
        private readonly TurnstileVerifier $turnstile,
    ) {}

    public function show(Request $request): View
    {
        $token = (string) $request->query('token', '');

        return view('login', ['authProps' => [
            'mode' => 'reset',
            'resetToken' => $token,
            'resetEmail' => (string) $request->query('email', ''),
            'resetLinkValid' => $this->resets->linkIsValid($token),
        ]]);
    }

    public function sendCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            ...$this->turnstile->rules(),
        ]);
        $this->turnstile->verify($request);

        $this->resets->sendCode($data['email'], $request->ip());

        return response()->json([
            'message' => 'If an account uses that email, a 6-digit code and a reset link were sent to it. Check your inbox and spam folder.',
            'expires_minutes' => (int) config('obims.password_reset.expires_minutes', 15),
            'resend_seconds' => (int) config('obims.password_reset.resend_seconds', 60),
        ]);
    }

    public function checkCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'string', 'regex:/^\s*\d{6}\s*$/'],
        ], ['code.regex' => 'Enter the 6-digit code from the email.']);

        $this->resets->checkCode($data['email'], $data['code']);

        return response()->json(['valid' => true]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required_without:code', 'nullable', 'string', 'max:128'],
            'email' => ['required_with:code', 'nullable', 'email', 'max:255'],
            'code' => ['required_without:token', 'nullable', 'string', 'regex:/^\s*\d{6}\s*$/'],
            'password' => ['required', 'confirmed', AdminSetup::passwordRule()],
        ], [
            'code.regex' => 'Enter the 6-digit code from the email.',
            'password.confirmed' => 'The two passwords do not match.',
        ]);

        $record = filled($data['token'] ?? null)
            ? $this->resets->checkToken($data['token'])
            : $this->resets->checkCode($data['email'], $data['code']);

        $user = $this->resets->reset($record, $data['password']);

        return response()->json([
            'message' => 'Your password was changed. Sign in with your new password.',
            'email' => $user->email,
        ]);
    }
}
