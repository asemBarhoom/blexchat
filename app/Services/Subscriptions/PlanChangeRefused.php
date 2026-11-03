<?php

namespace App\Services\Subscriptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A plan the owner cannot move to today: `payment_required` (402) or `trial_used` (409).
 */
class PlanChangeRefused extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $status)
    {
        parent::__construct($reason);
    }

    /**
     * The PlanError body, with the message from the given translation group.
     */
    public function toResponse(string $messages): JsonResponse
    {
        return response()->json(['message' => __("{$messages}.{$this->reason}"), 'code' => $this->reason], $this->status);
    }
}
