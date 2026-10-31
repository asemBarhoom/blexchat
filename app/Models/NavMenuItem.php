<?php

namespace App\Models;

use App\Models\Concerns\HomeListItem;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class NavMenuItem extends Model
{
    use HasTranslations, HomeListItem;

    public const MENUS = ['product', 'solutions'];

    /** @var list<string> */
    public array $translatable = ['title', 'description', 'cta'];

    protected $fillable = ['menu', 'title', 'description', 'cta', 'target', 'sort', 'active'];
}
