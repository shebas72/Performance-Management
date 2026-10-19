<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $lang = substr((string) $request->header('Accept-Language', 'en'), 0, 2);
        $lang = in_array($lang, ['ar', 'en'], true) ? $lang : 'en';
        app()->setLocale($lang);

        $response = $next($request);

        // Remember the language this person uses, so emails and notifications written later
        // (by someone else's action, or by a scheduled job) reach them in their own language.
        try {
            $user = $request->hasHeader('Accept-Language') ? $request->user() : null;

            if ($user && $user->locale !== $lang) {
                $user->forceFill(['locale' => $lang])->saveQuietly();
            }
        } catch (\Throwable) {
            // never break a response over this
        }

        return $response;
    }
}