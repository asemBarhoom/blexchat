<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LegalPageResource;
use App\Models\LegalPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'LegalPage', properties: [
    new OA\Property(property: 'slug', type: 'string', enum: ['privacy', 'terms']),
    new OA\Property(property: 'title', type: 'string', example: 'Privacy Policy'),
    new OA\Property(property: 'body', type: 'string', description: 'Markdown: `##` headings, paragraphs, `-` lists, **bold** and [links](https://…). Render it as Markdown, never as HTML.', example: "## What we collect\n\nYour email address and the messages your automations answer."),
    new OA\Property(property: 'format', type: 'string', enum: ['markdown']),
    new OA\Property(property: 'effective_on', type: 'string', format: 'date', nullable: true, description: '"Last updated": the day this version took effect', example: '2026-11-01'),
])]
class LegalPageController extends Controller
{
    #[OA\Get(
        path: '/api/v1/legal/{slug}',
        summary: 'The Privacy Policy or the Terms of Service',
        description: <<<'MD'
            The page the footer’s and the sign-up screen’s Privacy and Terms links open, as edited in the admin panel. No authentication.

            - Send `?lang=ar` for Arabic or `?lang=en` for English; without it, `Accept-Language` is used, and anything else returns English.
            - A page not yet translated is served in English, and `meta.fallback` is then `true`.
            MD,
        tags: ['Home'],
        parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['privacy', 'terms'])),
            new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the page. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The page',
                headers: [new OA\Header(header: 'Content-Language', description: 'Language of the returned page', schema: new OA\Schema(type: 'string'))],
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/LegalPage'),
                    new OA\Property(property: 'meta', type: 'object', properties: [
                        new OA\Property(property: 'locale', type: 'string', enum: ['en', 'ar'], example: 'ar'),
                        new OA\Property(property: 'fallback', type: 'boolean', description: 'True when the page is not translated yet and is served in English'),
                        new OA\Property(property: 'available_locales', type: 'array', items: new OA\Items(type: 'string'), example: ['en', 'ar']),
                    ]),
                ]),
            ),
            new OA\Response(response: 404, description: 'Not privacy or terms, or the page has not been seeded'),
        ],
    )]
    public function __invoke(string $slug): JsonResponse
    {
        $page = LegalPage::forSlug($slug)->firstOrFail();

        return (new LegalPageResource($page))
            ->response()
            ->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Content-Language', App::getLocale());
    }
}
