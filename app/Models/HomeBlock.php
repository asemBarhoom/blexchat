<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * One homepage section (or the navigation, footer or demo replies): its copy per locale
 * in `texts`, and everything shared by all locales in `settings`.
 */
class HomeBlock extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['texts'];

    protected $fillable = ['key', 'sort', 'texts', 'settings'];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'settings' => 'array',
        ];
    }
}
