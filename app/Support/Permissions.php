<?php

namespace App\Support;

/**
 * The permission catalogue. A role is a set of these keys; a company chooses the set for each of its roles.
 * The four built-in roles start from DEFAULTS, which match what admin / manager / employee / viewer could do before roles were configurable.
 */
final class Permissions
{
    /** group => permissions. The groups only organise the matrix on screen. */
    public const GROUPS = [
        'company'     => ['settings.manage', 'team.manage', 'access.all_departments'],
        'strategy'    => ['strategy.manage', 'departments.manage'],
        'performance' => ['kpis.manage', 'kpis.enter', 'proposals.review'],
        'execution'   => ['projects.manage', 'work.own'],
        'reports'     => ['team_report.lead', 'reports.export'],
    ];

    /** Built-in roles: key => [English name, Arabic name]. They exist for every company and cannot be deleted. */
    public const SYSTEM = [
        'admin'    => ['Admin', 'مدير النظام'],
        'manager'  => ['Manager', 'مدير'],
        'employee' => ['Employee', 'موظف'],
        'viewer'   => ['Viewer', 'مطّلع'],
    ];

    public const DEFAULTS = [
        'manager'  => ['departments.manage', 'kpis.manage', 'kpis.enter', 'proposals.review', 'projects.manage', 'work.own', 'team_report.lead', 'reports.export'],
        'employee' => ['kpis.enter', 'work.own', 'reports.export'],
        'viewer'   => ['reports.export'],
    ];

    /** @return string[] */
    public static function all(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }

    /** @return string[] permissions of a built-in role before the company changes anything */
    public static function defaultsFor(string $key): array
    {
        return $key === 'admin' ? self::all() : (self::DEFAULTS[$key] ?? []);
    }
}
