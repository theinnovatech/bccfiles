<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\Rules\Password;

/**
 * First-time setup: while the database has no administrator account, every page redirects to
 * /setup so the first administrator can be created.
 *
 * An inactive administrator still counts as an administrator, so setup cannot be reopened by
 * deactivating accounts. Soft-deleted administrators do not count.
 */
final class AdminSetup
{
    public static function required(): bool
    {
        return ! User::query()->where('role', UserRole::Admin)->exists();
    }

    /**
     * Optional extra protection for a public server: when OBIMS_SETUP_KEY is set in .env, the
     * setup form also asks for that key.
     */
    public static function key(): ?string
    {
        $key = trim((string) config('obims.setup_key', ''));

        return $key === '' ? null : $key;
    }

    /**
     * Password rule for the setup and password-reset forms.
     */
    public static function passwordRule(): Password
    {
        return Password::min(8)->letters()->numbers();
    }
}
