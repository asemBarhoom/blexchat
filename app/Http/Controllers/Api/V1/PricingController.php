<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PricingCompareGroupCollection;
use App\Http\Resources\Api\V1\PricingFaqItemCollection;
use App\Models\Plan;
use App\Models\PricingCompareGroup;
use App\Models\PricingCompareRow;
use App\Models\PricingFaqItem;
use App\Models\PricingPage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Pricing', description: 'Public marketing pricing page content; the prices themselves come from GET /api/v1/plans')]
#[OA\Schema(schema: 'PricingCopy', description: <<<'MD'
    The page's own words, grouped by part of the page. `{max_saving}`, `{free_contacts}`, `{trial_days}` and `{trial_plan}` are already filled in.
    The card texts keep per-plan placeholders for the frontend to fill: `{total}` (annual_total), `{days}` (trial_days), `{plan}` (name) and `{contacts}` (contacts_limit).
    MD, properties: [
    new OA\Property(property: 'hero', type: 'object', example: ['chip' => 'Pricing', 'title' => 'Start free. Pay when your audience grows.', 'lead' => 'Every plan answers comments and DMs for you.', 'billing_label' => 'Billing period', 'monthly' => 'Monthly', 'annual' => 'Annually', 'save' => 'Save up to 30%', 'note' => 'Prices in US dollars. Change or cancel any time.']),
    new OA\Property(property: 'card', type: 'object', example: ['ribbon' => 'Most popular', 'forever' => 'forever', 'per_month' => '/ month', 'no_card' => 'No card needed', 'billed_annually' => 'Billed ${total} a year', 'billed_monthly' => 'Billed monthly', 'start_free' => 'Start free', 'try_free' => 'Try {days} days free', 'choose' => 'Choose {plan}', 'contacts' => 'Up to {contacts} contacts', 'ai' => '+ Blexchat AI']),
    new OA\Property(property: 'compare', type: 'object', example: ['title' => 'Compare the plans', 'feature' => 'Feature', 'included' => 'Included', 'not_included' => 'Not included']),
    new OA\Property(property: 'agency', type: 'object', example: ['title' => 'Running accounts for clients?', 'line' => 'Many accounts from one seat.', 'link_label' => 'Blexchat for agencies', 'link_url' => '/solutions/agencies']),
    new OA\Property(property: 'faq', type: 'object', description: '`marked` is the word of `title` drawn with the marker band', example: ['title' => 'Before you pay.', 'marked' => 'pay']),
])]
#[OA\Schema(schema: 'PricingCompareGroup', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'automation'),
    new OA\Property(property: 'title', type: 'string', example: 'Automation'),
    new OA\Property(property: 'rows', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'string', example: 'automations'),
        new OA\Property(property: 'label', type: 'string', example: 'Automations'),
        new OA\Property(
            property: 'cells',
            description: 'One cell per plan on sale, keyed by plan id: true a tick, false a dash, or text',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(oneOf: [new OA\Schema(type: 'boolean'), new OA\Schema(type: 'string')]),
            example: ['free' => '3', 'essential' => 'Unlimited', 'pro' => true],
        ),
    ])),
])]
#[OA\Schema(schema: 'PricingFaqItem', properties: [
    new OA\Property(property: 'question', type: 'string', example: 'Is the free plan really free?'),
    new OA\Property(property: 'answer', type: 'string'),
])]
class PricingController extends Controller
{
    #[OA\Get(
        path: '/api/v1/pricing',
        summary: 'Get the public pricing page',
        description: <<<'MD'
            The page copy, the plan comparison table and the pricing FAQ. No authentication.
            Prices, limits and the plan cards come from `GET /api/v1/plans`; the table's cells are keyed by the same plan ids.

            **Language**: `?lang=ar|en`, else `Accept-Language`, else English. Any text not yet translated is served in English, field by field, and `meta.fallback` is then `true`.
            MD,
        tags: ['Pricing'],
        parameters: [
            new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts in the response. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar'),
            new OA\Parameter(name: 'Accept-Language', in: 'header', required: false, description: 'Used only when `lang` is absent; the first two letters are read (ar-SA → ar).', schema: new OA\Schema(type: 'string'), example: 'ar'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pricing page content',
                headers: [new OA\Header(header: 'Content-Language', description: 'Language of the returned content', schema: new OA\Schema(type: 'string'))],
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'object', properties: [
                        new OA\Property(property: 'copy', ref: '#/components/schemas/PricingCopy'),
                        new OA\Property(property: 'max_saving', type: 'integer', example: 30, description: 'The most a year up front saves over monthly, in percent'),
                        new OA\Property(property: 'compare', type: 'array', items: new OA\Items(ref: '#/components/schemas/PricingCompareGroup')),
                        new OA\Property(property: 'faq', type: 'array', items: new OA\Items(ref: '#/components/schemas/PricingFaqItem')),
                    ]),
                    new OA\Property(property: 'meta', type: 'object', properties: [
                        new OA\Property(property: 'locale', type: 'string', enum: ['en', 'ar'], example: 'ar'),
                        new OA\Property(property: 'fallback', type: 'boolean', description: 'True when at least one text is not translated yet and is served in English'),
                        new OA\Property(property: 'available_locales', type: 'array', items: new OA\Items(type: 'string'), example: ['en', 'ar']),
                    ]),
                ]),
            ),
            new OA\Response(response: 404, description: 'Pricing page content has not been seeded'),
        ],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $page = PricingPage::current();

        abort_if($page === null, 404);

        $locale = App::getLocale();
        $plans = Plan::visible()->get();
        $figures = PricingPage::figures($plans);
        $groups = PricingCompareGroup::visible()
            ->with(['rows' => fn (HasMany $query): HasMany => $query->where('active', true), 'rows.cells'])
            ->get();
        $faq = PricingFaqItem::visible()->get();

        $copy = PricingPage::fillFigures($page->copyIn($locale), $figures);
        $copy['agency']['link_url'] = $page->agency_url;

        return response()
            ->json([
                'data' => [
                    'copy' => $copy,
                    'max_saving' => $figures['max_saving'],
                    'compare' => (new PricingCompareGroupCollection($groups, $plans))->resolve($request),
                    'faq' => (new PricingFaqItemCollection($faq, $figures))->resolve($request),
                ],
                'meta' => [
                    'locale' => $locale,
                    'fallback' => $page->lacksTextsIn($locale) || $this->lacksTranslations($locale, $groups, $faq),
                    'available_locales' => config('app.supported_locales'),
                ],
            ], options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->header('Content-Language', $locale);
    }

    /**
     * Whether any text shown is served in the fallback language because it has no translation in `$locale`.
     *
     * @param  Collection<int, PricingCompareGroup>  $groups
     * @param  Collection<int, PricingFaqItem>  $faq
     */
    private function lacksTranslations(string $locale, Collection $groups, Collection $faq): bool
    {
        $fallbackLocale = config('app.fallback_locale');

        if ($locale === $fallbackLocale) {
            return false;
        }

        $models = $groups
            ->flatMap(fn (PricingCompareGroup $group): array => [$group, ...$group->rows->flatMap(fn (PricingCompareRow $row): array => [$row, ...$row->cells->all()])])
            ->concat($faq);

        return $models->contains(fn (Model $model): bool => collect($model->getTranslatableAttributes())->contains(
            fn (string $attribute): bool => $model->hasTranslation($attribute, $fallbackLocale) && ! $model->hasTranslation($attribute, $locale),
        ));
    }
}
