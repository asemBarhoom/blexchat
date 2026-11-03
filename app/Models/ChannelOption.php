<?php

namespace App\Models;

use App\Models\Concerns\HomeListItem;
use Database\Factories\ChannelOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A channel offered on the onboarding channel picker (W05). The connected accounts themselves are
 * {@see Channel} rows with the same `platform`.
 *
 * @property string $platform
 */
#[Fillable(['platform', 'name', 'line', 'sort', 'active'])]
class ChannelOption extends Model
{
    /** @use HasFactory<ChannelOptionFactory> */
    use HasFactory, HasTranslations, HomeListItem;

    /** The platforms Blexchat can connect, as stored in `channels.platform`. */
    public const PLATFORMS = ['instagram', 'whatsapp', 'tiktok', 'messenger'];

    /** @var list<string> */
    public array $translatable = ['name', 'line'];

    /**
     * Every owner's connected account on this platform.
     */
    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class, 'platform', 'platform');
    }
}
