<?php

namespace App\Models;

use App\Models\Concerns\HomeListItem;
use Database\Factories\AutomationTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A template of the app's picker (W19), or a starter: the answers the wizard opens with for its
 * trigger when no template is named. A quick template (`match` set) fills the wizard's trigger and
 * message; a flow template has nothing for the wizard to fill.
 *
 * @property string $key
 * @property bool $is_starter
 * @property string $build
 * @property string|null $goal
 * @property string $trigger
 * @property bool $locked
 * @property string|null $match
 * @property string|null $link_url
 */
#[Fillable(['key', 'is_starter', 'name', 'line', 'build', 'goal', 'trigger', 'locked', 'match', 'keywords', 'public_reply', 'dm_text', 'dm_button', 'link_url', 'sort', 'active'])]
class AutomationTemplate extends Model
{
    /** @use HasFactory<AutomationTemplateFactory> */
    use HasFactory, HasTranslations, HomeListItem;

    public const BUILDS = ['quick', 'flow'];

    public const GOALS = ['sell', 'grow', 'support', 'book'];

    /** The picker's shelves; `ad` has no wizard yet, so only flow templates use it. */
    public const TRIGGERS = ['comment', 'story', 'dm', 'ad'];

    public const MATCHES = ['words', 'any'];

    /** @var list<string> */
    public array $translatable = ['name', 'line', 'keywords', 'public_reply', 'dm_text', 'dm_button'];

    protected function casts(): array
    {
        return [
            'is_starter' => 'boolean',
            'locked' => 'boolean',
        ];
    }

    #[Scope]
    protected function shelf(Builder $query): void
    {
        $query->where('is_starter', false);
    }

    #[Scope]
    protected function starters(Builder $query): void
    {
        $query->where('is_starter', true);
    }

    /**
     * Whether it fills the wizard's answers; a flow template only names the trigger.
     */
    public function fillsWizard(): bool
    {
        return $this->match !== null && in_array($this->trigger, Automation::KINDS, true);
    }

    public function automations(): HasMany
    {
        return $this->hasMany(Automation::class);
    }
}
