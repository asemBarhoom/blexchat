<?php

namespace App\Models;

use Database\Factories\SolutionLabelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * The words every solution page shares, around each page's own content: section titles, buttons and
 * the names of the triggers. One row, its texts per locale in `texts`.
 */
#[Fillable(['texts'])]
class SolutionLabel extends Model
{
    /** @use HasFactory<SolutionLabelFactory> */
    use HasFactory, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['texts'];

    public static function current(): ?self
    {
        return self::query()->oldest('id')->first();
    }

    /**
     * The labels in a locale, every label it lacks taken from the fallback locale.
     *
     * @return array<string, mixed>
     */
    public function copyIn(string $locale): array
    {
        $texts = $this->getTranslations('texts');
        $fallbackLocale = config('app.fallback_locale');
        $translated = $locale === $fallbackLocale ? [] : self::withoutBlanks($texts[$locale] ?? []);

        return array_replace_recursive($texts[$fallbackLocale] ?? [], $translated);
    }

    /**
     * @param  array<array-key, mixed>  $texts
     * @return array<array-key, mixed>
     */
    private static function withoutBlanks(array $texts): array
    {
        return array_filter(
            array_map(fn (mixed $value): mixed => is_array($value) ? self::withoutBlanks($value) : $value, $texts),
            fn (mixed $value): bool => $value !== null && $value !== '' && $value !== [],
        );
    }
}
