<?php

namespace App\Http\Middleware;

use App\Models\User;
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

        $asked = in_array($locale, config('app.supported_locales'), true);

        if (! $asked) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);

        $response = $next($request);

        if ($asked) {
            $this->rememberFor($request, $locale);
        }

        return $response;
    }

    /**
     * Keeps the language a signed-in user's frontend asks for, so emails and Blexchat AI reach them in it.
     * The user is only known once the route's auth middleware has run, so this runs after the request.
     */
    private function rememberFor(Request $request, string $locale): void
    {
        $user = $request->user();

        if ($user instanceof User && $user->locale !== $locale) {
            $user->forceFill(['locale' => $locale])->saveQuietly();
        }
    }
}
