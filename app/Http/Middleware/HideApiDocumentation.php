<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides the Swagger documentation in production unless it is explicitly made public.
 */
class HideApiDocumentation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction() && ! config('l5-swagger.public_in_production')) {
            abort(404);
        }

        return $next($request);
    }
}
