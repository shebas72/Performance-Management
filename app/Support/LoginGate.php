<?php

namespace App\Support;

use App\Models\User;

/**
 * Extra "may this person sign in right now?" checks that products can plug in.
 * The SaaS build registers one (email verified, organisation approved); standalone registers none.
 * A check returns a message to refuse the sign-in, or null to let it through.
 */
final class LoginGate
{
    /** @var array<int, callable(User): ?string> */
    private static array $checks = [];

    public static function register(callable $check): void
    {
        self::$checks[] = $check;
    }

    public static function reason(User $user): ?string
    {
        foreach (self::$checks as $check) {
            if ($message = $check($user)) {
                return $message;
            }
        }

        return null;
    }
}
