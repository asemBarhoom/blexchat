<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A teammate works in the owner's inbox and contacts only: everything else (automations,
 * channels, billing, settings, the team) is the owner's.
 */
class EnsureAccountOwner
{
    /** The routes a teammate may use, by name. */
    private const TEAMMATE_ROUTES = [
        'api.v1.auth.me',
        'api.v1.auth.me.update',
        'api.v1.auth.logout',
        'api.v1.conversations.*',
        'api.v1.contacts.*',
        'api.v1.channels.index',
        'api.v1.inbox.settings.show',
        'api.v1.team.show',
        'api.v1.team.leave',
        'api.v1.team.invitations.accept',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isTeammate() && ! $request->routeIs(...self::TEAMMATE_ROUTES)) {
            return response()->json(['message' => __('team.owner_only'), 'code' => 'owner_only'], 403);
        }

        return $next($request);
    }
}
