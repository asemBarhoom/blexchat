<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * The Privacy Policy or the Terms of Service: a title and a Markdown body per language, and the
 * date the current version took effect ("Last updated"), which a typo fix need not move.
 */
class LegalPage extends Model
{
    use HasFactory, HasTranslations;

    /** @var list<string> */
    public const SLUGS = ['privacy', 'terms'];

    /** @var list<string> */
    public array $translatable = ['title', 'body'];

    protected $fillable = ['slug', 'title', 'body', 'effective_on'];

    protected function casts(): array
    {
        return [
            'effective_on' => 'date',
        ];
    }

    /**
     * @param  Builder<LegalPage>  $query
     */
    public function scopeForSlug(Builder $query, string $slug): void
    {
        $query->where('slug', $slug);
    }

    /**
     * Whether the locale lacks the title or the body, so they are served in the fallback language.
     */
    public function isMissingTextsIn(string $locale): bool
    {
        return collect($this->translatable)->contains(fn (string $attribute): bool => ! $this->hasTranslation($attribute, $locale));
    }
}
