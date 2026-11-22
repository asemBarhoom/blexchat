<?php

namespace App\Models;

use Database\Factories\AiUsageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable(['user_id', 'workspace_id', 'conversation_id', 'automation_id', 'provider', 'model', 'status', 'input_tokens', 'output_tokens', 'estimated_cost_micros'])]
class AiUsage extends Model
{
    /** @use HasFactory<AiUsageFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'estimated_cost_micros' => 'integer',
        ];
    }

    public static function reserve(User $user, Conversation $conversation, Automation $automation): ?self
    {
        return DB::transaction(function () use ($user, $conversation, $automation): ?self {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $limit = (int) $lockedUser->currentPlan()->ai_replies_limit;
            $monthStart = now()->startOfMonth();
            $activePendingSince = now()->subMinutes(10);

            $used = self::query()
                ->where('user_id', $lockedUser->id)
                ->where('created_at', '>=', $monthStart)
                ->where(function ($query) use ($activePendingSince): void {
                    $query->where('status', self::STATUS_COMPLETED)
                        ->orWhere(function ($query) use ($activePendingSince): void {
                            $query->where('status', self::STATUS_PENDING)
                                ->where('created_at', '>=', $activePendingSince);
                        });
                })
                ->count();

            if ($limit <= 0 || $used >= $limit) {
                return null;
            }

            return self::create([
                'user_id' => $lockedUser->id,
                'workspace_id' => $conversation->channel->workspace_id,
                'conversation_id' => $conversation->id,
                'automation_id' => $automation->id,
                'provider' => 'anthropic',
                'model' => config('services.anthropic.model'),
                'status' => self::STATUS_PENDING,
            ]);
        });
    }

    public function complete(string $model, int $inputTokens, int $outputTokens): void
    {
        $inputCost = (float) config('services.anthropic.input_cost_per_million');
        $outputCost = (float) config('services.anthropic.output_cost_per_million');

        $this->update([
            'model' => $model,
            'status' => self::STATUS_COMPLETED,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'estimated_cost_micros' => (int) round($inputTokens * $inputCost + $outputTokens * $outputCost),
        ]);
    }

    public function fail(): void
    {
        $this->update(['status' => self::STATUS_FAILED]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }
}
