<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $lang = substr((string) $request->header('Accept-Language', 'en'), 0, 2);
        app()->setLocale(in_array($lang, ['ar', 'en'], true) ? $lang : 'en');

        return $next($request);
    }
}
