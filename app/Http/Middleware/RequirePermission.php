<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Route middleware: RequirePermission::class.':reports.export' */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        abort_unless($request->user()?->hasPermission($permission), 403, 'You do not have permission to do this.');

        return $next($request);
    }
}
