<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The workspace a request works in (BE-11), from the X-Workspace header. Without it the owner's
 * first workspace is used, so a frontend that never sends it keeps working. A teammate always
 * works in the workspace they were invited to.
 */
class ResolveWorkspace
{
    public const HEADER = 'X-Workspace';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->useWorkspace($request->header(self::HEADER))) {
            return response()->json(['message' => __('workspaces.not_found'), 'code' => 'workspace_not_found'], 404);
        }

        return $next($request);
    }
}
