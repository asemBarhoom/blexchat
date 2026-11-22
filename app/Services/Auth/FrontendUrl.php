<?php

namespace App\Services\Auth;

class FrontendUrl
{
    /**
     * Use the requesting frontend only when it is already trusted by CORS.
     */
    public static function fromOrigin(?string $origin): string
    {
        return self::isAllowedOrigin($origin)
            ? rtrim($origin, '/')
            : rtrim((string) config('app.frontend_url'), '/');
    }

    public static function isAllowedOrigin(?string $origin): bool
    {
        if (blank($origin) || ! preg_match('#^https://[a-z0-9.-]+$#i', $origin)) {
            return false;
        }

        $origin = rtrim($origin, '/');

        if (in_array($origin, array_map(fn (string $allowed): string => rtrim($allowed, '/'), config('cors.allowed_origins', [])), true)) {
            return true;
        }

        foreach (config('cors.allowed_origins_patterns', []) as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }

        return false;
    }
}
