<?php

namespace App\Models;

use Database\Factories\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * The texts of one email, per language, as edited in the admin panel. The templates are fixed in
 * code (TEMPLATES); every text left empty, for a row or a language, falls back to its lang/ string.
 *
 * @property string $key
 */
#[Fillable(['key', 'subject', 'line', 'button', 'expiry', 'ignore'])]
class EmailTemplate extends Model
{
    /** @use HasFactory<EmailTemplateFactory> */
    use HasFactory, HasTranslations;

    /**
     * Each template's key => its texts' default lang/ keys. The placeholders a default uses
     * (`:minutes`) must stay in the edited text too.
     *
     * @var array<string, array<string, string>>
     */
    public const TEMPLATES = [
        'login_link.signup' => [
            'subject' => 'auth.link.signup.subject',
            'line' => 'auth.link.signup.line',
            'button' => 'auth.link.signup.button',
            'expiry' => 'auth.link.expiry',
            'ignore' => 'auth.link.ignore',
        ],
        'login_link.signin' => [
            'subject' => 'auth.link.signin.subject',
            'line' => 'auth.link.signin.line',
            'button' => 'auth.link.signin.button',
            'expiry' => 'auth.link.expiry',
            'ignore' => 'auth.link.ignore',
        ],
    ];

    /** @var list<string> */
    public array $translatable = ['subject', 'line', 'button', 'expiry', 'ignore'];

    /**
     * The template's texts in the language, with the admin's text where there is one and the
     * placeholders filled in.
     *
     * @param  array<string, string|int>  $replace
     * @return array<string, string>
     */
    public static function compose(string $key, string $locale, array $replace = []): array
    {
        $template = static::query()->firstWhere('key', $key);

        return collect(self::TEMPLATES[$key])
            ->map(fn (string $langKey, string $field): string => self::fillPlaceholders(
                $template?->getTranslation($field, $locale, useFallbackLocale: false) ?: self::defaultText($langKey, $locale),
                $replace,
            ))
            ->all();
    }

    public static function defaultText(string $langKey, string $locale): string
    {
        return __($langKey, [], $locale);
    }

    /**
     * The placeholders the text must keep: those its English default uses.
     *
     * @return list<string>
     */
    public static function requiredPlaceholders(string $key, string $field): array
    {
        return self::placeholdersIn(self::defaultText(self::TEMPLATES[$key][$field], 'en'));
    }

    /**
     * The placeholders any text of the template may use: those its defaults use.
     *
     * @return list<string>
     */
    public static function allowedPlaceholders(string $key): array
    {
        return collect(self::TEMPLATES[$key])
            ->flatMap(fn (string $langKey): array => self::placeholdersIn(self::defaultText($langKey, 'en')))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string> Names without the colon, in order of first use.
     */
    public static function placeholdersIn(string $text): array
    {
        preg_match_all('/:([a-zA-Z_]+)/', $text, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Replaces `:name` with its value. Unlike str_replace, strtr never replaces inside a value it put in.
     *
     * @param  array<string, string|int>  $replace
     */
    public static function fillPlaceholders(string $text, array $replace): string
    {
        return strtr($text, collect($replace)->mapWithKeys(fn (string|int $value, string $name): array => [":{$name}" => (string) $value])->all());
    }
}
