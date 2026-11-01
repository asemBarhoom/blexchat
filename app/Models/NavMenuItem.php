<?php

namespace App\Models;

use App\Models\Concerns\HomeListItem;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class NavMenuItem extends Model
{
    use HasTranslations, HomeListItem;

    public const MENUS = ['product', 'solutions'];

    /** The frontend's own line icons (navData.ts IconName). */
    public const ICONS = ['spark', 'camera', 'bag', 'cup', 'tooth', 'briefcase', 'cap', 'house', 'headset'];

    /** Channels whose brand mark replaces the icon. */
    public const CHANNELS = ['instagram', 'whatsapp', 'tiktok', 'messenger'];

    /** @var list<string> */
    public array $translatable = ['title', 'description', 'cta'];

    /** `target` is a route (/signup) or a homepage section (#try-it). */
    protected $fillable = ['menu', 'icon', 'channel', 'title', 'description', 'cta', 'target', 'sort', 'active'];
}
