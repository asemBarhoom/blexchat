<?php

namespace App\Models;

use App\Models\Concerns\HomeListItem;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class FooterLink extends Model
{
    use HasTranslations, HomeListItem;

    public const GROUPS = ['product', 'solutions', 'account', 'legal'];

    /** @var list<string> */
    public array $translatable = ['label'];

    protected $fillable = ['group', 'label', 'url', 'sort', 'active'];
}
