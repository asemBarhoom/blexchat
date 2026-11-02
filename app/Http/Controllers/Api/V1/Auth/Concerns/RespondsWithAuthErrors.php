<?php

namespace App\Http\Controllers\Api\V1\Auth\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Sign-in failures the frontend shows a specific screen or notice for carry a stable `code`.
 */
trait RespondsWithAuthErrors
{
    /**
     * @param  array<string, mixed>  $extra
     */
    private function authError(int $status, string $code, string $message, array $extra = []): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code, ...$extra], $status);
    }
}
