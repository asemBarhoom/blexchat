<?php

namespace App\Models;

use Database\Factories\SolutionAutomationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * One of a solution page's starter automations and the conversation its phone preview plays. Only
 * the title and line are translated: the conversation is Gulf Arabic in every language.
 *
 * @property string $trigger_source
 * @property list<string>|null $trigger_words
 * @property string $customer
 * @property string $reply
 * @property string $button
 * @property string $link
 * @property string|null $post_image
 * @property string|null $post_tile_text
 * @property string|null $post_tile_tone
 * @property string|null $post_caption
 * @property string|null $post_likes
 * @property string|null $account_handle
 * @property string|null $account_avatar
 * @property string|null $account_color
 * @property string|null $account_text_color
 */
#[Fillable(['solution_id', 'title', 'line', 'trigger_source', 'trigger_words', 'customer', 'reply', 'button', 'link', 'post_image', 'post_tile_text', 'post_tile_tone', 'post_caption', 'post_likes', 'account_handle', 'account_avatar', 'account_color', 'account_text_color', 'sort'])]
class SolutionAutomation extends Model
{
    /** @use HasFactory<SolutionAutomationFactory> */
    use HasFactory, HasTranslations;

    public const SOURCES = ['comment', 'dm', 'story'];

    public const TILE_TONES = ['tint', 'ink', 'yellow', 'pink', 'surface'];

    /** The automation wizard's button limit (BUTTON_MAX). */
    public const BUTTON_MAX = 20;

    /** @var list<string> */
    public array $translatable = ['title', 'line'];

    protected function casts(): array
    {
        return ['trigger_words' => 'array', 'sort' => 'integer'];
    }

    public function hasPost(): bool
    {
        return filled($this->post_image) || filled($this->post_tile_text);
    }

    public function hasOwnAccount(): bool
    {
        return filled($this->account_handle);
    }

    public function solution(): BelongsTo
    {
        return $this->belongsTo(Solution::class);
    }
}
