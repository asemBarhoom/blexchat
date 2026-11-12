<?php

namespace App\Models;

use Database\Factories\HelpArticleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class HelpArticle extends Model
{
    /** @use HasFactory<HelpArticleFactory> */
    use HasFactory, HasTranslations;

    protected $fillable = ['category', 'title', 'body', 'sort', 'active'];

    public array $translatable = ['title', 'body'];

    protected function casts(): array
    {
        return ['sort' => 'integer', 'active' => 'boolean'];
    }

    public function scopeVisible($query)
    {
        return $query->where('active', true)->orderBy('category')->orderBy('sort')->orderBy('id');
    }
}
