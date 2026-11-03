<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FirstMoveCollection;
use App\Models\FirstMove;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'FirstMove', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'comment-dm', description: 'Stable id of the card'),
    new OA\Property(property: 'trigger', type: 'string', enum: FirstMove::TRIGGERS, example: 'comment', description: 'Wizard trigger: AUTH_LINKS.wizardNew(trigger, template)'),
    new OA\Property(property: 'template', type: 'string', nullable: true, example: null, description: 'Wizard template key, if the move starts from one'),
    new OA\Property(property: 'wizard_path', type: 'string', example: '/onboarding/automation/new?trigger=comment', description: 'The frontend path the card links to'),
    new OA\Property(property: 'title', type: 'string', example: 'Auto-DM links from comments'),
    new OA\Property(property: 'line', type: 'string', example: 'Someone comments a keyword on your post — they get your link in a DM, instantly.'),
    new OA\Property(property: 'incoming', type: 'object', description: 'The sample message the automation answers', properties: [
        new OA\Property(property: 'who', type: 'string', example: 'reem.k'),
        new OA\Property(property: 'text', type: 'string', example: 'LINK 😍 how much?'),
        new OA\Property(property: 'kind', type: 'string', example: 'Comment'),
    ]),
    new OA\Property(property: 'reply', type: 'string', example: 'Here’s the link 👉 nourboutique.com/abaya', description: 'The automated reply'),
])]
class FirstMoveController extends Controller
{
    #[OA\Get(
        path: '/api/v1/onboarding/first-moves',
        summary: 'The "popular first move" cards (W10)',
        description: 'Quick automations to start the wizard with, in the admin order.',
        security: [['sanctum' => []]],
        tags: ['Onboarding'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts in the response. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        responses: [
            new OA\Response(response: 200, description: 'The cards', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/FirstMove')),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
        ],
    )]
    public function __invoke(): FirstMoveCollection
    {
        return new FirstMoveCollection(FirstMove::visible()->get());
    }
}
