<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MarketingPage;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Home', description: 'Public marketing homepage content')]
class HomeController extends Controller
{
    #[OA\Get(
        path: '/api/v1/home',
        summary: 'Get the complete public marketing homepage',
        description: 'Returns navigation, every homepage section in display order, and footer copy. No authentication required.',
        tags: ['Home'],
        responses: [
            new OA\Response(response: 200, description: 'Complete homepage content', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'object', properties: [new OA\Property(property: 'navigation', type: 'object'), new OA\Property(property: 'sections', type: 'array', items: new OA\Items(type: 'object')), new OA\Property(property: 'footer', type: 'object')])])),
            new OA\Response(response: 404, description: 'Homepage content has not been seeded'),
        ],
    )]
    public function __invoke(): JsonResponse
    {
        $page = MarketingPage::query()
            ->where('slug', 'home')
            ->where('locale', 'en')
            ->firstOrFail();

        return response()->json(['data' => $page->content]);
    }
}
