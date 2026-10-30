<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Explicit query param: ?lang=ar
        $locale = $request->query('lang');

        // 2. Accept-Language header: Accept-Language: ar
        if (! $locale) {
            $header = $request->header('Accept-Language', '');
            $primary = strtolower(substr($header, 0, 2));
            $locale = $primary ?: null;
        }

        // Validate and fall back to app default
        if (! in_array($locale, ['ar', 'en'], true)) {
            $locale = config('app.locale', 'ar');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
