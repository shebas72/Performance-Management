<?php

namespace App\Services;

use App\Models\User;

/** What each person wants to receive, per category, in the app and by email. Missing choices use the defaults below. */
class NotificationPrefs
{
    public const CATEGORIES = [
        'proposals'  => ['app' => true, 'email' => true],   // corrective proposals submitted / reviewed
        'kpi_status' => ['app' => true, 'email' => false],  // a KPI turned red
        'reminders'  => ['app' => true, 'email' => true],   // month-end reminder for missing actuals
        'plan'       => ['app' => true, 'email' => true],   // trial ending, payment failed, upgrade request decided
    ];

    public static function for(User $user): array
    {
        $saved = json_decode((string) $user->notification_prefs, true) ?: [];
        $out = [];

        foreach (self::CATEGORIES as $category => $defaults) {
            $out[$category] = [
                'app' => (bool) ($saved[$category]['app'] ?? $defaults['app']),
                'email' => (bool) ($saved[$category]['email'] ?? $defaults['email']),
            ];
        }

        return $out;
    }

    public static function save(User $user, array $input): array
    {
        $prefs = [];

        foreach (array_keys(self::CATEGORIES) as $category) {
            $prefs[$category] = [
                'app' => (bool) ($input[$category]['app'] ?? false),
                'email' => (bool) ($input[$category]['email'] ?? false),
            ];
        }

        $user->forceFill(['notification_prefs' => json_encode($prefs)])->save();

        return $prefs;
    }

    /** @return array<int, string> Laravel channels for this person and category. */
    public static function channels(User $user, string $category): array
    {
        $p = self::for($user)[$category] ?? ['app' => true, 'email' => false];

        return array_values(array_filter([$p['app'] ? 'database' : null, $p['email'] ? 'mail' : null]));
    }
}