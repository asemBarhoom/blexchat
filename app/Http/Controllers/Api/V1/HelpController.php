<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PricingFaqItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use OpenApi\Attributes as OA;

class HelpController extends Controller
{
    #[OA\Get(path: '/api/v1/help', summary: 'Public help centre content', tags: ['Help'], responses: [new OA\Response(response: 200, description: 'Help articles and support links')])]
    public function __invoke(Request $request): JsonResponse
    {
        $locale = App::getLocale();
        $faq = PricingFaqItem::visible()->get()->map(fn (PricingFaqItem $item): array => ['question' => $item->getTranslation('question', $locale, true), 'answer' => $item->getTranslation('answer', $locale, true)])->values();

        return response()->json(['data' => ['title' => $locale === 'ar' ? 'مركز المساعدة' : 'Help centre', 'articles' => $faq, 'support' => ['email' => config('mail.from.address'), 'response_time_hours' => 24]], 'meta' => ['locale' => $locale, 'available_locales' => config('app.supported_locales')]]);
    }
}
