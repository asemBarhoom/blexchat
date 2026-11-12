<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Solution;
use App\Models\SolutionAutomation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * One automation of a solution page and the conversation its phone preview plays. `post` and
 * `account` are left out where there is none: the page's sample business is then the account.
 *
 * @mixin SolutionAutomation
 */
class SolutionAutomationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'line' => $this->line,
            'trigger' => ['source' => $this->trigger_source, 'words' => $this->trigger_words ?? []],
            'customer' => $this->customer,
            'reply' => $this->reply,
            'button' => $this->button,
            'link' => $this->link,
            'post' => $this->when($this->hasPost(), fn (): array => array_filter([
                'id' => "post-{$this->id}",
                'image' => $this->post_image ? url(Storage::disk('public')->url($this->post_image)) : null,
                'tile' => $this->post_tile_text ? ['text' => $this->post_tile_text, 'tone' => $this->post_tile_tone] : null,
                'caption' => $this->post_caption ?? '',
                'likes' => $this->post_likes ?? '',
            ], fn (mixed $value): bool => $value !== null)),
            'account' => $this->when($this->hasOwnAccount(), fn (): array => [
                'handle' => $this->account_handle,
                'avatar' => Solution::avatarUrl($this->account_avatar, $this->account_handle, $this->account_color ?? '#0b0b0f', $this->account_text_color ?? '#ffffff'),
            ]),
        ];
    }
}
