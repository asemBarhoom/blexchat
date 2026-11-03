<?php

namespace App\Models;

use App\Models\Concerns\HomeListItem;
use Database\Factories\SolutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A /solutions/{slug} page. Where the vertical has a homepage use-case card, the page is linked to it
 * and reads its slug, name, shape and tone from the card; otherwise it keeps its own (see identity()).
 *
 * @property int|null $use_case_id
 * @property string|null $slug
 * @property string|null $shape
 * @property string|null $tone
 * @property string $sample_handle
 * @property string|null $sample_avatar
 * @property string $avatar_color
 * @property string $avatar_text_color
 * @property-read UseCase|null $useCase
 */
#[Fillable(['use_case_id', 'slug', 'name', 'shape', 'tone', 'sample_handle', 'sample_avatar', 'avatar_color', 'avatar_text_color', 'headline', 'supporting', 'closing', 'sort', 'active'])]
class Solution extends Model
{
    /** @use HasFactory<SolutionFactory> */
    use HasFactory, HasTranslations, HomeListItem;

    /** The use-case card tones, and two more for pages with no card: the shape drawn in ink. */
    public const TONES = ['brand', 'tint', 'ink', 'tint-ink', 'brand-ink'];

    /** Where uploaded avatars and post images go on the public disk. */
    public const UPLOADS = 'marketing/solutions/uploads';

    /** @var list<string> */
    public array $translatable = ['name', 'headline', 'supporting', 'closing'];

    protected static function booted(): void
    {
        // A linked page reads its identity from the card, so no stale copy is kept beside it.
        static::saving(function (self $solution): void {
            if ($solution->use_case_id !== null) {
                // Raw: a translatable attribute set to null would keep a {"en": null} instead of clearing.
                $solution->setRawAttributes([...$solution->getAttributes(), 'slug' => null, 'name' => null, 'shape' => null, 'tone' => null]);
            }
        });
    }

    /**
     * Where the slug, name, shape and tone are read from: the linked use case, or the page itself.
     */
    public function identity(): UseCase|self
    {
        return $this->useCase ?? $this;
    }

    /**
     * The page at /solutions/{slug}, whether the slug is its own or its use case's.
     */
    #[Scope]
    protected function forSlug(Builder $query, string $slug): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('slug', $slug)
            ->orWhereHas('useCase', fn (Builder $useCase) => $useCase->where('slug', $slug)));
    }

    /**
     * An account's avatar: the uploaded image, or else its initial drawn on a colour, as the frontend
     * draws the sample businesses (none of them is a person, so none has a photo).
     */
    public static function avatarUrl(?string $upload, string $handle, string $background, string $foreground): string
    {
        if ($upload) {
            return url('storage/'.$upload);
        }

        $letter = htmlspecialchars(mb_strtoupper(mb_substr(ltrim($handle, '@'), 0, 1)), ENT_XML1);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="'.$background.'"/>'
            .'<text x="32" y="43" text-anchor="middle" font-family="Arial, sans-serif" font-size="30" font-weight="700" fill="'.$foreground.'">'.$letter.'</text></svg>';

        return 'data:image/svg+xml,'.rawurlencode($svg);
    }

    public function sampleAvatarUrl(): string
    {
        return self::avatarUrl($this->sample_avatar, $this->sample_handle, $this->avatar_color, $this->avatar_text_color);
    }

    public function useCase(): BelongsTo
    {
        return $this->belongsTo(UseCase::class);
    }

    public function automations(): HasMany
    {
        return $this->hasMany(SolutionAutomation::class)->orderBy('sort')->orderBy('id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(SolutionStep::class)->orderBy('sort')->orderBy('id');
    }

    public function faqItems(): HasMany
    {
        return $this->hasMany(SolutionFaqItem::class)->orderBy('sort')->orderBy('id');
    }
}
