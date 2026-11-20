<?php

namespace App\Services;

use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Forgot password: a 6-digit code and a one-time link are emailed to the account's registered
 * address. Either one lets the user choose a new password.
 *
 * - The response never says whether an account exists for an email.
 * - Only the newest code of an account works; it expires after a few minutes, works once, and
 *   is cancelled after too many wrong entries.
 * - A new code is sent at most once per cooldown period per account.
 * - Codes and link tokens are stored as hashes only.
 * - Resetting signs the account out of other devices.
 */
class PasswordResetService
{
    public function __construct(private readonly ActivityLogService $activityLog) {}

    public function sendCode(string $email, ?string $ip = null): void
    {
        $user = $this->findUser($email);
        if (! $user) {
            return;
        }

        $cooldown = (int) config('obims.password_reset.resend_seconds', 60);
        $justSent = PasswordResetCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('created_at', '>', now()->subSeconds($cooldown))
            ->exists();
        if ($justSent) {
            return;
        }

        $minutes = max(1, (int) config('obims.password_reset.expires_minutes', 15));
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $token = Str::random(64);

        DB::transaction(function () use ($user, $code, $token, $minutes, $ip) {
            // Only the newest code works.
            PasswordResetCode::query()->where('user_id', $user->id)->whereNull('used_at')->delete();

            PasswordResetCode::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'code_hash' => Hash::make($code),
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addMinutes($minutes),
                'requested_ip' => $ip,
            ]);
        });

        $link = url('/reset-password').'?'.http_build_query(['token' => $token, 'email' => $user->email]);

        try {
            Mail::to($user->email, $user->name)->send(new PasswordResetCodeMail(
                $user,
                $code,
                $link,
                $minutes,
                (string) Setting::getValue('organization_name', config('app.name')),
            ));
        } catch (Throwable $e) {
            // Not shown to the visitor (it would reveal that the account exists); logged for the administrator.
            Log::error('Password reset email could not be sent.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Check a typed code. A wrong code counts as an attempt.
     */
    public function checkCode(string $email, string $code): PasswordResetCode
    {
        // The wrong-attempt count must be saved, so the transaction returns the outcome and the
        // error is thrown only after it has committed (throwing inside would roll it back).
        $outcome = DB::transaction(function () use ($email, $code) {
            $user = $this->findUser($email);
            $record = $user
                ? PasswordResetCode::query()
                    ->where('user_id', $user->id)
                    ->whereNull('used_at')
                    ->latest('id')
                    ->lockForUpdate()
                    ->first()
                : null;

            if (! $record || ! $record->isUsable()) {
                return ['error' => 'This code has expired or is no longer valid. Request a new code.'];
            }

            if (! Hash::check(trim($code), $record->code_hash)) {
                $record->increment('attempts');
                $left = (int) config('obims.password_reset.max_attempts', 5) - $record->attempts;

                return ['error' => $left > 0
                    ? "The code is incorrect. {$left} attempt(s) left."
                    : 'Too many incorrect codes. Request a new code.'];
            }

            return ['record' => $record];
        });

        if (isset($outcome['error'])) {
            throw ValidationException::withMessages(['code' => $outcome['error']]);
        }

        return $outcome['record'];
    }

    public function checkToken(string $token): PasswordResetCode
    {
        $record = PasswordResetCode::query()->where('token_hash', hash('sha256', trim($token)))->first();

        if (! $record || ! $record->isUsable()) {
            throw ValidationException::withMessages([
                'token' => 'This reset link has expired or was already used. Request a new code.',
            ]);
        }

        return $record;
    }

    public function linkIsValid(?string $token): bool
    {
        if (! filled($token)) {
            return false;
        }

        $record = PasswordResetCode::query()->where('token_hash', hash('sha256', trim($token)))->first();

        return (bool) $record?->isUsable();
    }

    public function reset(PasswordResetCode $checked, string $password): User
    {
        return DB::transaction(function () use ($checked, $password) {
            $record = PasswordResetCode::query()->lockForUpdate()->find($checked->id);
            $user = $record?->user;

            if (! $record || ! $record->isUsable() || ! $user || ! $user->is_active) {
                throw ValidationException::withMessages([
                    'code' => 'This code has expired or is no longer valid. Request a new code.',
                ]);
            }

            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            $record->update(['used_at' => now()]);
            PasswordResetCode::query()->where('user_id', $user->id)->whereNull('used_at')->delete();

            // Sign the account out everywhere.
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }

            $this->activityLog->log($user, 'Updated', 'Users', 'Reset the account password with an emailed verification code');

            return $user;
        });
    }

    private function findUser(string $email): ?User
    {
        $email = trim($email);

        return $email === ''
            ? null
            : User::query()->where('email', $email)->where('is_active', true)->first();
    }
}
