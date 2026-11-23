<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Stripe\StripeClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class SystemHealthController extends Controller
{
    #[OA\Get(
        path: '/api/v1/health',
        summary: 'System health',
        description: 'Database, cache, queue, outbound email and payment readiness for uptime checks and the status page.',
        tags: ['Health'],
        responses: [new OA\Response(response: 200, description: '`status` is `ok` or `degraded`; `checks` has `database`, `cache`, `queue`, `mail` and `payments`')]
    )]
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'cache' => $this->check(fn () => Cache::put('health:check', true, 5) && Cache::get('health:check') === true),
            'queue' => config('queue.default'),
            'mail' => $this->mailReady() ? config('mail.default') : false,
            'payments' => $this->paymentsReady() ? config('services.payments.driver') : false,
        ];

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

    private function mailReady(): bool
    {
        if (! app()->isProduction()) {
            return true;
        }

        return match (config('mail.default')) {
            'log', 'array' => false,
            'smtp' => $this->smtpReady() && $this->fromAddressReady(),
            'resend' => filled(config('services.resend.key')) && $this->fromAddressReady(),
            'postmark' => filled(config('services.postmark.key')) && $this->fromAddressReady(),
            'ses', 'ses-v2' => filled(config('services.ses.key')) && filled(config('services.ses.secret')) && $this->fromAddressReady(),
            default => false,
        };
    }

    private function smtpReady(): bool
    {
        $host = strtolower((string) config('mail.mailers.smtp.host'));

        return filled($host) && ! in_array($host, ['127.0.0.1', 'localhost'], true);
    }

    private function fromAddressReady(): bool
    {
        $address = (string) config('mail.from.address');

        return filter_var($address, FILTER_VALIDATE_EMAIL) !== false
            && ! str_ends_with(strtolower($address), '@example.com');
    }

    private function paymentsReady(): bool
    {
        return ! app()->isProduction() || StripeClient::isReadyForProduction();
    }
}
