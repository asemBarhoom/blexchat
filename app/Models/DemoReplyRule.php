<?php

namespace App\Models;

use App\Models\Concerns\HomeListItem;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class DemoReplyRule extends Model
{
    use HasTranslations, HomeListItem;

    /** @var list<string> */
    public array $translatable = ['reply', 'action', 'result'];

    protected $fillable = ['key', 'keywords', 'reply', 'action', 'result', 'sort', 'active'];

    protected function casts(): array
    {
        return ['keywords' => 'array'];
    }
}
