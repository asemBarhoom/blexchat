<?php

namespace App\Models;

use Database\Factories\ChannelConnectScreenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * The connect-a-channel screen (W06) for one channel: what changes between channels. `steps` holds a
 * list of lines per language and `options` a list of {title, note, url} per language.
 *
 * @property string $platform
 * @property string $method
 * @property bool $active
 */
#[Fillable(['platform', 'method', 'name', 'cta', 'lead', 'steps', 'options', 'account_kind', 'can', 'reconnect_cta', 'waiting_in', 'ineligible_title', 'ineligible_text', 'ineligible_kind', 'ineligible_how', 'ineligible_help_url', 'active'])]
class ChannelConnectScreen extends Model
{
    /** @use HasFactory<ChannelConnectScreenFactory> */
    use HasFactory, HasTranslations;

    public const PLATFORMS = ['instagram', 'whatsapp', 'tiktok', 'messenger'];

    public const METHODS = ['meta', 'tiktok'];

    /** @var list<string> */
    public array $translatable = ['name', 'cta', 'lead', 'steps', 'options', 'account_kind', 'can', 'reconnect_cta', 'waiting_in', 'ineligible_title', 'ineligible_text', 'ineligible_kind', 'ineligible_how'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /**
     * Where the owner stands with this channel, which picks the screen's state:
     * connected (an active channel), reconnect (a channel that dropped, its automations paused),
     * requested (waiting on another workspace for access) or default.
     *
     * @return 'connected'|'reconnect'|'requested'|'default'
     */
    public function stateFor(?Channel $channel, ?ChannelAccessRequest $pendingRequest): string
    {
        return match (true) {
            $channel?->status === 'active' => 'connected',
            $channel !== null => 'reconnect',
            $pendingRequest !== null => 'requested',
            default => 'default',
        };
    }
}
