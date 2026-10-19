<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SelfSignup
{
    public static function isOpen(): bool
    {
        // Counts self-signups ever made (the ledger), not current accounts, so deleting an account can't reopen a place.
        return DB::table('self_signups')->count() < (int) config('nailpolish.self_signup_limit');
    }

    public static function isAdminEmail(string $email): bool
    {
        $configured = Str::lower(trim((string) config('nailpolish.admin_email')));

        return $configured !== '' && Str::lower($email) === $configured;
    }

    /** Whether this address may be sent a sign-in link right now. */
    public static function mayRequestLink(string $email): bool
    {
        return self::isOpen()
            || self::isAdminEmail($email)
            || User::where('email', Str::lower($email))->exists();
    }
}
