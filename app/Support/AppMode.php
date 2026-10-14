<?php

namespace App\Support;

/**
 * One place that answers "which product is this?".
 *   standalone = the CodeCanyon build: one organisation, no plans, no limits, no billing.
 *   saas       = multi-organisation, plans and subscriptions.
 * An unknown APP_MODE value counts as standalone, the safe choice: nothing is limited and no billing screens appear.
 */
final class AppMode
{
    public static function current(): string
    {
        return config('spms.mode') === 'saas' ? 'saas' : 'standalone';
    }

    public static function saas(): bool { return self::current() === 'saas'; }

    public static function standalone(): bool { return self::current() === 'standalone'; }
}
