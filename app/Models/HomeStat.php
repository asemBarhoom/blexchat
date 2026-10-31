<?php

namespace App\Models;

use App\Models\Concerns\HomeListItem;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class HomeStat extends Model
{
    use HasTranslations, HomeListItem;

    /** @var list<string> */
    public array $translatable = ['value', 'label'];

    protected $fillable = ['value', 'label', 'sort', 'active'];
}
