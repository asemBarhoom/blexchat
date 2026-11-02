<?php

namespace App\Models;

use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An owner's plan. A trial is the only state with an end: it takes no card and does not convert,
 * so once trial_ends_at passes the account is treated as Free (FLOW-CLOSURE S6.4).
 *
 * @property string $billing_period
 * @property string $status
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $trial_used_at
 */
#[Fillable(['user_id', 'plan_id', 'billing_period', 'status', 'trial_ends_at', 'trial_used_at'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    public const STATUSES = ['active', 'trialing'];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'trial_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function onTrial(): bool
    {
        return $this->status === 'trialing' && $this->trial_ends_at?->isFuture() === true;
    }

    public function trialEnded(): bool
    {
        return $this->status === 'trialing' && ! $this->onTrial();
    }

    /**
     * Whole days left, rounded up the way the frontend counts them; null when not on a trial.
     */
    public function trialDaysLeft(): ?int
    {
        return $this->onTrial() ? (int) ceil(now()->diffInSeconds($this->trial_ends_at) / 86400) : null;
    }

    /**
     * The tier the account has today: the chosen plan, or Free once its trial has ended.
     */
    public function effectivePlan(): Plan
    {
        return $this->trialEnded() ? Plan::free() : $this->plan;
    }
}
