<?php

namespace App\Models;

use Database\Factories\AutomationFolderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A folder of the owner's automations ("Create folder"): one per campaign or per channel.
 * An automation sits in at most one; deleting the folder leaves its automations in All.
 */
class AutomationFolder extends Model
{
    /** @use HasFactory<AutomationFolderFactory> */
    use HasFactory;

    public const MAX_PER_USER = 50;

    protected $fillable = ['user_id', 'name'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Automation, $this>
     */
    public function automations(): HasMany
    {
        return $this->hasMany(Automation::class);
    }
}
