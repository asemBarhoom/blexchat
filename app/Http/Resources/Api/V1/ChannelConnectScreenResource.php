<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Channel;
use App\Models\ChannelAccessRequest;
use App\Models\ChannelConnectScreen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The W06 copy for one channel, with where the signed-in owner stands with it.
 *
 * @mixin ChannelConnectScreen
 */
class ChannelConnectScreenResource extends JsonResource
{
    public function __construct(ChannelConnectScreen $resource, private ?Channel $channel = null, private ?ChannelAccessRequest $pendingRequest = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'platform' => $this->platform,
            'method' => $this->method,
            'name' => $this->name,
            'cta' => $this->cta,
            'lead' => $this->lead,
            'steps' => $this->steps ?? [],
            'options' => array_values(array_map(fn (array $option): array => [
                'title' => $option['title'] ?? '',
                'note' => $option['note'] ?? null,
                'url' => $option['url'] ?? null,
            ], $this->options ?? [])),
            'account_kind' => $this->account_kind,
            'can' => $this->can,
            'reconnect_cta' => $this->reconnect_cta,
            'waiting_in' => $this->waiting_in,
            'ineligible' => [
                'title' => $this->ineligible_title,
                'text' => $this->ineligible_text,
                'kind' => $this->ineligible_kind,
                'how' => $this->ineligible_how,
                'help_url' => $this->ineligible_help_url,
            ],
            'state' => $this->resource->stateFor($this->channel, $this->pendingRequest),
            'channel' => $this->channel ? new ChannelResource($this->channel) : null,
            'access_request' => $this->pendingRequest ? new ChannelAccessRequestResource($this->pendingRequest) : null,
        ];
    }
}
