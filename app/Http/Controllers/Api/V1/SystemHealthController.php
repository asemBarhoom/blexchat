<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SystemHealthController extends Controller
{
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
