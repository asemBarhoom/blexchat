<?php

namespace App\Models;

use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One business an owner runs (BE-11): its channels, automations, folders, contacts and team.
 * The plan and its limits are the owner's and count every workspace together.
 */
#[Fillable(['name'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    /**
     * The owner's first workspace, made on first use, where anything created without one lands.
     */
    public static function defaultFor(User $owner): self
    {
        return self::where('owner_id', $owner->id)->oldest('id')->first()
            ?? self::forceCreate(['owner_id' => $owner->id, 'name' => $owner->business_name ?: ($owner->name ?: $owner->email)]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<Channel, $this>
     */
    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    /**
     * @return HasMany<Automation, $this>
     */
    public function automations(): HasMany
    {
        return $this->hasMany(Automation::class);
    }

    /**
     * @return HasMany<AutomationFolder, $this>
     */
    public function automationFolders(): HasMany
    {
        return $this->hasMany(AutomationFolder::class);
    }

    /**
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function teammates(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<TeamInvitation, $this>
     */
    public function teamInvitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * Whether a channel, automation, folder or contact is this workspace's.
     */
    public function holds(?Model $record): bool
    {
        return $record !== null && (int) $record->getAttribute('workspace_id') === $this->id;
    }
}
