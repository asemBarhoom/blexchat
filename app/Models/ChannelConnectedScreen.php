<?php

namespace App\Models;

use Database\Factories\ChannelConnectedScreenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * The success copy of the "channel connected" screen (W07) for one platform.
 *
 * @property string $platform
 * @property bool $active
 */
#[Fillable(['platform', 'channel_name', 'title', 'lead', 'account_kind', 'status_label', 'continue_label', 'connect_another_label', 'active'])]
class ChannelConnectedScreen extends Model
{
    /** @use HasFactory<ChannelConnectedScreenFactory> */
    use HasFactory, HasTranslations;

    /** @var list<string> */
    public const PLATFORMS = ['instagram', 'whatsapp', 'tiktok', 'messenger'];

    /** @var list<string> */
    public array $translatable = ['channel_name', 'title', 'lead', 'account_kind', 'status_label', 'continue_label', 'connect_another_label'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * The shown copy for a platform, or null when there is none or it is switched off.
     */
    public static function forPlatform(string $platform): ?self
    {
        return self::query()->shown()->where('platform', $platform)->first();
    }

    #[Scope]
    protected function shown(Builder $query): void
    {
        $query->where('active', true);
    }
}
