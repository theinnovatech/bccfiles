<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Cloudflare Turnstile check shared by the public forms (sign in, first-time setup, forgot
 * password). Does nothing when Turnstile is turned off in .env.
 */
class TurnstileVerifier
{
    public function enabled(): bool
    {
        return (bool) (config('services.turnstile.enabled') && config('services.turnstile.site_key'));
    }

    /**
     * @return array<string, list<string>> validation rule for the token field
     */
    public function rules(): array
    {
        return ['cf_turnstile_response' => [$this->enabled() ? 'required' : 'nullable', 'string']];
    }

    public function verify(Request $request): void
    {
        if (! $this->enabled()) {
            return;
        }

        $result = Http::timeout(10)->asForm()->post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $request->input('cf_turnstile_response'),
                'remoteip' => $request->ip(),
            ],
        )->json();

        if ($result['success'] ?? false) {
            return;
        }

        $codes = $result['error-codes'] ?? [];

        throw ValidationException::withMessages([
            'cf_turnstile_response' => [
                match (true) {
                    in_array('invalid-input-response', $codes, true) => 'Security check expired. Please try again.',
                    in_array('invalid-input-secret', $codes, true) => 'Turnstile secret key is invalid. Check TURNSTILE_SECRET_KEY in .env.',
                    in_array('timeout-or-duplicate', $codes, true) => 'Security check already used. Please complete it again.',
                    default => 'Please complete the security check.',
                },
            ],
        ]);
    }
}
