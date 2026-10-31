<?php

namespace App\Models;

use App\Models\Concerns\HomeListItem;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class UseCase extends Model
{
    use HasTranslations, HomeListItem;

    public const SHAPES = ['rounded', 'blob', 'arch', 'circle', 'pill', 'house'];

    public const TONES = ['brand', 'tint', 'ink'];

    /** @var list<string> */
    public array $translatable = ['name', 'line'];

    protected $fillable = ['key', 'slug', 'name', 'line', 'shape', 'tone', 'sort', 'active'];
}
