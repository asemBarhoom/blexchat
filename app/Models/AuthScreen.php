<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * The copy of one sign-up / sign-in screen (W01–W04), or of an area they share: a flat map
 * of text key to string per locale in `texts`.
 */
class AuthScreen extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['texts'];

    protected $fillable = ['key', 'texts'];

    /**
     * The texts in a locale, with every text it lacks taken from the fallback locale.
     *
     * @return array<string, string>
     */
    public function textsIn(string $locale): array
    {
        return array_replace($this->fallbackTexts(), $this->ownTexts($locale));
    }

    /**
     * Whether the locale lacks a text that the fallback locale has.
     */
    public function isMissingTextsIn(string $locale): bool
    {
        return array_diff_key($this->fallbackTexts(), $this->ownTexts($locale)) !== [];
    }

    /**
     * @return array<string, string>
     */
    private function fallbackTexts(): array
    {
        return $this->ownTexts(config('app.fallback_locale'));
    }

    /**
     * @return array<string, string>
     */
    private function ownTexts(string $locale): array
    {
        $texts = $this->getTranslations('texts')[$locale] ?? [];

        return array_filter($texts, fn (mixed $text): bool => is_string($text) && $text !== '');
    }
}
