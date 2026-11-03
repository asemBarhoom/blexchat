<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SolutionCollection;
use App\Http\Resources\Api\V1\SolutionResource;
use App\Models\Solution;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Solutions', description: 'Public /solutions/{slug} pages: one template, one entry per vertical')]
#[OA\Schema(schema: 'SolutionSummary', properties: [
    new OA\Property(property: 'slug', type: 'string', example: 'e-commerce', description: 'The page is /solutions/{slug}; the homepage use-case card with this slug links to it'),
    new OA\Property(property: 'name', type: 'string', example: 'E-commerce'),
    new OA\Property(property: 'shape', type: 'string', enum: ['rounded', 'blob', 'arch', 'circle', 'pill', 'house'], description: 'The use-case card shape drawn behind the hero'),
    new OA\Property(property: 'tone', type: 'string', enum: ['brand', 'tint', 'ink', 'tint-ink', 'brand-ink'], description: 'The hero backdrop; the *-ink tones draw the shape in ink'),
    new OA\Property(property: 'headline', type: 'string', example: 'The price is asked a hundred times. Answer it once.'),
])]
#[OA\Schema(schema: 'SolutionAccount', description: 'An account in the phone preview', properties: [
    new OA\Property(property: 'handle', type: 'string', example: 'nourboutique'),
    new OA\Property(property: 'avatar', type: 'string', description: 'An image URL, or a data:image/svg+xml URI of the initial on a colour'),
])]
#[OA\Schema(schema: 'SolutionAutomation', properties: [
    new OA\Property(property: 'title', type: 'string', example: 'Price in the DMs'),
    new OA\Property(property: 'line', type: 'string'),
    new OA\Property(property: 'trigger', type: 'object', properties: [
        new OA\Property(property: 'source', type: 'string', enum: ['comment', 'dm', 'story']),
        new OA\Property(property: 'words', type: 'array', items: new OA\Items(type: 'string'), example: ['السعر'], description: 'Keywords, in Arabic as typed. Empty: every comment, first message or reply'),
    ]),
    new OA\Property(property: 'customer', type: 'string', description: 'The customer’s comment or message (Gulf Arabic in every language)'),
    new OA\Property(property: 'reply', type: 'string', description: 'The automated DM'),
    new OA\Property(property: 'button', type: 'string', maxLength: 20, description: 'Button label in the DM'),
    new OA\Property(property: 'link', type: 'string', example: 'nourboutique.com/winter-abaya'),
    new OA\Property(property: 'post', type: 'object', description: 'The post the comment sits under, or the story replied to. Absent when there is none. Exactly one of `image` and `tile`.', properties: [
        new OA\Property(property: 'id', type: 'string', example: 'post-12'),
        new OA\Property(property: 'image', type: 'string', format: 'uri'),
        new OA\Property(property: 'tile', type: 'object', properties: [
            new OA\Property(property: 'text', type: 'string'),
            new OA\Property(property: 'tone', type: 'string', enum: ['tint', 'ink', 'yellow', 'pink', 'surface']),
        ]),
        new OA\Property(property: 'caption', type: 'string'),
        new OA\Property(property: 'likes', type: 'string', example: '١٬٢٠٤'),
    ]),
    new OA\Property(property: 'account', ref: '#/components/schemas/SolutionAccount', description: 'Whose account it runs on where it is not the page’s sample (an agency’s clients). Absent otherwise.'),
])]
#[OA\Schema(schema: 'Solution', allOf: [
    new OA\Schema(ref: '#/components/schemas/SolutionSummary'),
    new OA\Schema(properties: [
        new OA\Property(property: 'sample', ref: '#/components/schemas/SolutionAccount', description: 'The sample business the conversations belong to'),
        new OA\Property(property: 'hero', type: 'object', properties: [
            new OA\Property(property: 'headline', type: 'string'),
            new OA\Property(property: 'supporting', type: 'string'),
        ]),
        new OA\Property(property: 'automations', type: 'array', items: new OA\Items(ref: '#/components/schemas/SolutionAutomation'), description: 'Three, in order'),
        new OA\Property(property: 'steps', type: 'array', items: new OA\Items(type: 'string'), description: '"How it works", in order'),
        new OA\Property(property: 'faq', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'q', type: 'string'),
            new OA\Property(property: 'a', type: 'string'),
        ])),
        new OA\Property(property: 'closing', type: 'string', description: 'The closing line above the sign-up button'),
    ]),
])]
class SolutionController extends Controller
{
    #[OA\Get(
        path: '/api/v1/solutions',
        summary: 'Every solution page',
        description: 'The visible pages in the admin order, for the Solutions menu. No authentication. Texts not yet translated are served in English.',
        tags: ['Solutions'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts in the response. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        responses: [new OA\Response(response: 200, description: 'The pages', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/SolutionSummary')),
        ]))],
    )]
    public function index(): SolutionCollection
    {
        return new SolutionCollection(Solution::visible()->with('useCase')->get());
    }

    #[OA\Get(
        path: '/api/v1/solutions/{slug}',
        summary: 'One solution page',
        description: 'Everything /solutions/{slug} shows: the hero, three automations with their conversations, the steps, the FAQ and the closing line. No authentication. Page copy follows `lang`; the conversations are Gulf Arabic in every language.',
        tags: ['Solutions'],
        parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'e-commerce'),
            new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts in the response. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The page', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/Solution'),
            ])),
            new OA\Response(response: 404, description: 'No page has this slug, or it is hidden'),
        ],
    )]
    public function show(string $slug): SolutionResource
    {
        $solution = Solution::visible()
            ->forSlug($slug)
            ->with(['useCase', 'automations', 'steps', 'faqItems'])
            ->firstOrFail();

        return new SolutionResource($solution);
    }
}
