<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use OpenApi\Attributes as OA;

class HelpController extends Controller
{
    #[OA\Get(path: '/api/v1/help', summary: 'Public help centre content', tags: ['Help'], responses: [new OA\Response(response: 200, description: 'Help articles and support links', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'object', properties: [new OA\Property(property: 'title', type: 'string'), new OA\Property(property: 'articles', type: 'array', items: new OA\Items(type: 'object', properties: [new OA\Property(property: 'category', type: 'string'), new OA\Property(property: 'question', type: 'string'), new OA\Property(property: 'answer', type: 'string')])), new OA\Property(property: 'support', type: 'object', properties: [new OA\Property(property: 'email', type: 'string'), new OA\Property(property: 'response_time_hours', type: 'integer')])])]))])]
    public function __invoke(Request $request): JsonResponse
    {
        $locale = App::getLocale();
        $articles = HelpArticle::visible()->get()->map(fn (HelpArticle $article): array => [
            'category' => $article->category,
            'question' => $article->getTranslation('title', $locale, true),
            'answer' => $article->getTranslation('body', $locale, true),
        ])->values();

        return response()->json(['data' => ['title' => __('help.title'), 'articles' => $articles, 'support' => ['email' => Setting::get(Setting::HELP_EMAIL) ?? config('mail.from.address'), 'response_time_hours' => Setting::get(Setting::HELP_RESPONSE_TIME)]], 'meta' => ['locale' => $locale, 'available_locales' => config('app.supported_locales')]]);
    }
}
