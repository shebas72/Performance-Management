<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;


/** One place to notify tenant users. A failure is logged and never breaks the action that triggered it. */
class Notifier
{
    /** @param iterable<User>|User|null $users */
   
    public static function to(iterable|User|null $users, string $category, string $title, string $body, ?string $url = null, string $level = 'info', ?string $key = null, array $params = []): void
    {
        try {
            $users = $users instanceof User ? collect([$users]) : collect($users ?? []);

            // A keyed notification is sent once per person (for example one reminder per month).
            if ($key) {
                $users = $users->reject(fn (User $u) => $u->notifications()->where('data->key', $key)->exists());
            }

            if ($users->isNotEmpty()) {
                
                Notification::send($users->values(), new AppNotification($category, $title, $body, $url, $level, $key, $params));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** People who may review proposals / follow KPIs of a department (admins and managers with access to it). */
    public static function reviewers(int $companyId, ?int $departmentId, ?int $exceptUserId = null): Collection
    {
        $access = app(AccessScope::class);

        return User::where('company_id', $companyId)->where('is_super_admin', false)->where('is_active', true)
            ->when($exceptUserId, fn ($q) => $q->where('id', '!=', $exceptUserId))
            ->get()
            ->filter(fn (User $u) => $access->canReview($u, $departmentId))
            ->values();
    }

    /** The organization's admins (people who may change settings and the plan). */
    public static function companyAdmins(int $companyId): Collection
    {
        return User::where('company_id', $companyId)->where('is_super_admin', false)->where('is_active', true)
            ->get()
            ->filter(fn (User $u) => $u->hasPermission('settings.manage'))
            ->values();
    }
}