<?php

namespace App\Models;

use App\Models\Concerns\HomeListItem;
use Database\Factories\FirstMoveFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * A "popular first move" card of onboarding W10: a quick automation that opens the wizard
 * on its trigger, shown with a sample incoming message and the automated reply.
 *
 * @property string $key
 * @property string $trigger
 * @property string|null $template
 * @property string $incoming_who
 */
#[Fillable(['key', 'trigger', 'template', 'title', 'line', 'incoming_who', 'incoming_text', 'incoming_kind', 'reply', 'sort', 'active'])]
class FirstMove extends Model
{
    /** @use HasFactory<FirstMoveFactory> */
    use HasFactory, HasTranslations, HomeListItem;

    /** The triggers the automation wizard starts on (WizardTrigger in the frontend's authLinks.ts). */
    public const TRIGGERS = ['comment', 'story', 'dm'];

    /** @var list<string> */
    public array $translatable = ['title', 'line', 'incoming_text', 'incoming_kind', 'reply'];

    /**
     * The frontend path that opens the wizard on this move, as AUTH_LINKS.wizardNew() builds it.
     */
    public function wizardPath(): string
    {
        $query = ['trigger' => $this->trigger];

        if (filled($this->template)) {
            $query['template'] = $this->template;
        }

        return '/onboarding/automation/new?'.http_build_query($query, encoding_type: PHP_QUERY_RFC3986);
    }
}
