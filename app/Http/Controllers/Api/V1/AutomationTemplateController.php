<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AutomationTemplateCollection;
use App\Models\AutomationTemplate;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Automation wizard', description: 'The template picker (W19) and the automation wizard (W11–W15, Review)')]
#[OA\Schema(schema: 'AutomationTemplate', properties: [
    new OA\Property(property: 'id', type: 'string', example: 't1', description: 'Stable; send it as `template` when the wizard saves'),
    new OA\Property(property: 'name', type: 'string', example: 'Comment a word → send the catalogue', description: 'Also the new automation’s name in the list'),
    new OA\Property(property: 'line', type: 'string', nullable: true),
    new OA\Property(property: 'build', type: 'string', enum: ['quick', 'flow']),
    new OA\Property(property: 'goal', type: 'string', nullable: true, enum: ['sell', 'grow', 'support', 'book']),
    new OA\Property(property: 'trigger', type: 'string', enum: ['comment', 'story', 'dm', 'ad']),
    new OA\Property(property: 'locked', type: 'boolean', description: 'Needs a paid plan: the card carries an UPGRADE tag'),
    new OA\Property(property: 'draft', type: 'object', nullable: true, description: 'The wizard answers it fills; null for a flow template', properties: [
        new OA\Property(property: 'trigger', type: 'string', enum: ['words', 'any']),
        new OA\Property(property: 'words', type: 'array', items: new OA\Items(type: 'string'), example: ['كتالوج']),
        new OA\Property(property: 'public_reply', type: 'boolean'),
        new OA\Property(property: 'public_reply_text', type: 'string', nullable: true, example: 'أرسلنا لك التفاصيل بالخاص 💌'),
        new OA\Property(property: 'dm_text', type: 'string'),
        new OA\Property(property: 'dm_button', type: 'string', nullable: true),
        new OA\Property(property: 'link_url', type: 'string', nullable: true, description: 'A sample link to begin with'),
    ]),
])]
class AutomationTemplateController extends Controller
{
    #[OA\Get(
        path: '/api/v1/automation-templates',
        summary: 'Automation templates and the wizard’s starters',
        description: '`data` is the picker’s shelf in the admin order. `starters` holds, per trigger (comment, story, dm), what the wizard opens with when no template is named.',
        security: [['sanctum' => []]],
        tags: ['Automation wizard'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts in the response. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        responses: [
            new OA\Response(response: 200, description: 'The templates', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AutomationTemplate')),
                new OA\Property(property: 'starters', type: 'object', properties: [
                    new OA\Property(property: 'comment', ref: '#/components/schemas/AutomationTemplate'),
                    new OA\Property(property: 'story', ref: '#/components/schemas/AutomationTemplate'),
                    new OA\Property(property: 'dm', ref: '#/components/schemas/AutomationTemplate'),
                ]),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
        ],
    )]
    public function __invoke(): AutomationTemplateCollection
    {
        return new AutomationTemplateCollection(
            AutomationTemplate::visible()->shelf()->get(),
            AutomationTemplate::visible()->starters()->get(),
        );
    }
}
