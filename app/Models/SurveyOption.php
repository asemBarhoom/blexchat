<?php

namespace App\Models;

use Database\Factories\SurveyOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $key
 * @property ?string $icon
 * @property ?string $icon_image
 * @property ?array{viewBox: string, width: int, height: int} $icon_box
 */
#[Fillable(['survey_question_id', 'key', 'label', 'note', 'icon', 'icon_image', 'icon_box', 'sort'])]
class SurveyOption extends Model
{
    /** @use HasFactory<SurveyOptionFactory> */
    use HasFactory, HasTranslations;

    /** A simple-icons slug: lowercase letters and digits, as in googlesheets or 1password. */
    public const ICON_SLUG_PATTERN = '/^[a-z0-9]+(_[a-z0-9]+)*$/';

    /** An SVG viewBox: four numbers, as in "0 9.58 24 4.84". */
    public const VIEW_BOX_PATTERN = '/^-?\d+(\.\d+)?( -?\d+(\.\d+)?){3}$/';

    /** @var list<string> */
    public array $translatable = ['label', 'note'];

    protected function casts(): array
    {
        return ['sort' => 'integer', 'icon_box' => 'array'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }

    /**
     * The absolute URL of the uploaded logo, or null when the option uses a slug or has no icon.
     */
    public function iconImageUrl(): ?string
    {
        return $this->icon_image === null ? null : Storage::disk('public')->url($this->icon_image);
    }
}
