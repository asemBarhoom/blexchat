<?php

namespace App\Http\Requests\Api\Channel;

use App\Models\Channel;
use Illuminate\Foundation\Http\FormRequest;

class ShowConnectedChannelRequest extends FormRequest
{
    /**
     * Only the owner reads a connected channel.
     */
    public function authorize(): bool
    {
        $channel = $this->route('channel');

        return $channel instanceof Channel && $channel->user_id === $this->user()->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
