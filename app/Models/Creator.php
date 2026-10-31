<?php

namespace App\Models;

use App\Models\Concerns\HomeListItem;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Creator extends Model
{
    use HasTranslations, HomeListItem;

    /** @var list<string> */
    public array $translatable = ['name', 'followers', 'quote'];

    protected $fillable = ['key', 'name', 'handle', 'followers', 'quote', 'photo', 'sort', 'active'];
}
