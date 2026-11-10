<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record that belongs to one of its owner's workspaces. One created without a workspace lands in
 * the owner's first, so webhooks, imports and older callers keep working.
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::creating(function (Model $record): void {
            $ownerId = $record->getAttribute(static::workspaceOwnerKey());

            if ($record->getAttribute('workspace_id') === null && $ownerId !== null && ($owner = User::find($ownerId)) !== null) {
                $record->setAttribute('workspace_id', Workspace::defaultFor($owner)->id);
            }
        });
    }

    /**
     * The column naming the owner the workspace is looked up for.
     */
    protected static function workspaceOwnerKey(): string
    {
        return 'user_id';
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
