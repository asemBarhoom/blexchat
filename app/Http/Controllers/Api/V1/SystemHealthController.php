<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class SystemHealthController extends Controller
{
    #[OA\Get(
        path: '/api/v1/health',
        summary: 'System health',
        description: 'Database, cache and queue readiness for uptime checks and the status page.',
        tags: ['Health'],
        responses: [new OA\Response(response: 200, description: '`status` is `ok` or `degraded`; `checks` has `database`, `cache` and `queue`')]
    )]
    public function __invoke(): JsonResponse
    {
        $checks = ['database' => $this->check(fn () => DB::select('select 1')), 'cache' => $this->check(fn () => Cache::put('health:check', true, 5) && Cache::get('health:check') === true), 'queue' => config('queue.default')];

        return response()->json(['status' => collect($checks)->every(fn ($value) => $value === true || is_string($value)) ? 'ok' : 'degraded', 'checks' => $checks]);
    }

    private function check(\Closure $check): bool
    {
        try {
            return (bool) $check();
        } catch (\Throwable) {
            return false;
        }
    }
}
