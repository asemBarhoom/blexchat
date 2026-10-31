<?php

namespace App\Models;

use App\Models\Concerns\HomeListItem;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class ComparisonPoint extends Model
{
    use HasTranslations, HomeListItem;

    public const SIDES = ['before', 'after'];

    /** @var list<string> */
    public array $translatable = ['text'];

    protected $fillable = ['side', 'text', 'sort', 'active'];
}
