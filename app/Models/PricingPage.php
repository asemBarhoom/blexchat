<?php

namespace App\Models;

use Database\Factories\PricingPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Translatable\HasTranslations;

/**
 * The pricing page's own copy (headings, buttons, notes): one row, its texts per locale in `texts`.
 * Prices and limits are never written here: texts name them by {placeholder}, filled from the plans.
 *
 * @property string|null $agency_url
 */
#[Fillable(['texts', 'agency_url'])]
class PricingPage extends Model
{
    /** @use HasFactory<PricingPageFactory> */
    use HasFactory, HasTranslations;

    /**
     * Placeholders the API fills from the plans, in the copy and the FAQ alike. Any other
     * {placeholder} (a card's {price}, {total}, {days}, {plan}, {contacts}) is per plan and left to the frontend.
     *
     * @var list<string>
     */
    public const FIGURES = ['max_saving', 'free_contacts', 'trial_days', 'trial_plan'];

    /** @var list<string> */
    public array $translatable = ['texts'];

    public static function current(): ?self
    {
        return self::query()->oldest('id')->first();
    }

    /**
     * The copy in a locale, every text it lacks taken from the fallback locale.
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
     * Whether a locale lacks any text the fallback locale has.
     */
    public function lacksTextsIn(string $locale): bool
    {
        $texts = $this->getTranslations('texts');

        return self::countTexts(self::withoutBlanks($texts[$locale] ?? [])) < self::countTexts($texts[config('app.fallback_locale')] ?? []);
    }

    /**
     * The page-wide figures, read from the plans on sale.
     *
     * @param  Collection<int, Plan>  $plans
     * @return array{max_saving: int, free_contacts: int, trial_days: int, trial_plan: string}
     */
    public static function figures(Collection $plans): array
    {
        $trialPlan = $plans->first(fn (Plan $plan): bool => $plan->hasTrial());

        return [
            'max_saving' => Plan::annualSavingUpTo($plans),
            'free_contacts' => $plans->first(fn (Plan $plan): bool => $plan->isFree())?->contacts_limit ?? 0,
            'trial_days' => $trialPlan?->trial_days ?? 0,
            'trial_plan' => $trialPlan?->name ?? '',
        ];
    }

    /**
     * A text, or every text of a nested array, with the page-wide figures in place of their placeholders.
     *
     * @param  string|array<array-key, mixed>|null  $text
     * @param  array<string, int|string>  $figures
     * @return string|array<array-key, mixed>|null
     */
    public static function fillFigures(string|array|null $text, array $figures): string|array|null
    {
        if (is_array($text)) {
            return array_map(fn (mixed $value): mixed => is_string($value) || is_array($value) ? self::fillFigures($value, $figures) : $value, $text);
        }

        if ($text === null) {
            return null;
        }

        $replacements = [];

        foreach ($figures as $name => $value) {
            $replacements['{'.$name.'}'] = is_int($value) ? number_format($value) : $value;
        }

        return strtr($text, $replacements);
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

    /**
     * @param  array<array-key, mixed>  $texts
     */
    private static function countTexts(array $texts): int
    {
        $count = 0;

        array_walk_recursive($texts, function (mixed $value) use (&$count): void {
            $count += is_string($value) && $value !== '' ? 1 : 0;
        });

        return $count;
    }
}
