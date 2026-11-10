<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

/**
 * Form inputs for content stored per language (spatie/laravel-translatable): one input per
 * supported language side by side. Only the fallback language is required, because every
 * other language falls back to it field by field.
 */
final class Translations
{
    public static function input(string $name, string $label, bool $required = true, bool $long = false): Grid
    {
        $locales = config('app.supported_locales');

        return Grid::make(count($locales))->schema(array_map(
            fn (string $locale): TextInput|Textarea => self::field("{$name}.{$locale}", "{$label} — ".self::localeName($locale), $locale, $required && self::isFallback($locale), $long),
            $locales,
        ))->columnSpanFull();
    }

    /**
     * A single input for one language, for forms that are already split by language.
     */
    public static function field(string $name, string $label, string $locale, bool $required, bool $long = false): TextInput|Textarea
    {
        $field = $long ? Textarea::make($name)->rows(2) : TextInput::make($name);

        return $field
            ->label($label)
            ->required($required)
            ->extraInputAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr']);
    }

    public static function localeName(string $locale): string
    {
        return ['en' => __('admin/common.locale_en'), 'ar' => __('admin/common.locale_ar')][$locale] ?? $locale;
    }

    public static function isFallback(string $locale): bool
    {
        return $locale === config('app.fallback_locale');
    }
}
