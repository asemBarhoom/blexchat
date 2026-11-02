<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Onboarding\SaveSurveyAnswersRequest;
use App\Http\Resources\Api\V1\SurveyQuestionCollection;
use App\Models\SurveyQuestion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'SurveyQuestion', properties: [
    new OA\Property(property: 'id', type: 'string', example: 'heard', description: 'Send it back as `question`'),
    new OA\Property(property: 'question', type: 'string', example: 'How did you hear about us?'),
    new OA\Property(property: 'hint', type: 'string', nullable: true, example: 'Pick all that apply.'),
    new OA\Property(property: 'kind', type: 'string', enum: ['radio', 'multi', 'grid'], description: 'radio: one answer. multi and grid: any number (grid is the tool-logo layout)'),
    new OA\Property(property: 'other_option', type: 'string', nullable: true, example: 'other', description: 'The option that reveals a free-text field'),
    new OA\Property(property: 'options', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'string', example: 'instagram', description: 'Stable; pick the icon by it'),
        new OA\Property(property: 'label', type: 'string', example: 'Instagram'),
        new OA\Property(property: 'note', type: 'string', nullable: true),
    ])),
    new OA\Property(property: 'answer', type: 'object', nullable: true, description: 'What this owner picked; null when skipped or not answered yet', properties: [
        new OA\Property(property: 'options', type: 'array', items: new OA\Items(type: 'string'), example: ['instagram', 'other']),
        new OA\Property(property: 'other', type: 'string', nullable: true, example: 'A podcast about Gulf e-commerce'),
    ]),
])]
class SurveyController extends Controller
{
    #[OA\Get(
        path: '/api/v1/onboarding/survey',
        summary: 'The onboarding survey (W08)',
        description: 'The steps in order, each with this owner’s saved answer.',
        security: [['sanctum' => []]],
        tags: ['Onboarding'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts in the response. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        responses: [
            new OA\Response(response: 200, description: 'The survey', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/SurveyQuestion')),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
        ],
    )]
    public function show(Request $request): SurveyQuestionCollection
    {
        return new SurveyQuestionCollection($this->survey($request->user()->id));
    }

    #[OA\Post(
        path: '/api/v1/onboarding/survey',
        summary: 'Save the survey answers (Finish on the last step)',
        description: 'Send every answered step at once and leave a skipped step out, so Skip on every step sends `"answers": []`. Replaces what was saved before and marks the survey done.',
        security: [['sanctum' => []]],
        tags: ['Onboarding'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts and error messages in the response. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['answers'],
            properties: [
                new OA\Property(property: 'answers', type: 'array', items: new OA\Items(required: ['question', 'options'], properties: [
                    new OA\Property(property: 'question', type: 'string', example: 'heard'),
                    new OA\Property(property: 'options', type: 'array', items: new OA\Items(type: 'string'), example: ['instagram', 'other'], description: 'Option ids; exactly one for a radio question'),
                    new OA\Property(property: 'other', type: 'string', nullable: true, maxLength: 255, example: 'A podcast about Gulf e-commerce', description: 'Only with the question’s `other_option` picked'),
                ])),
            ],
            example: ['answers' => [
                ['question' => 'for', 'options' => ['myself']],
                ['question' => 'money', 'options' => ['products', 'deals']],
                ['question' => 'heard', 'options' => ['instagram', 'other'], 'other' => 'A podcast about Gulf e-commerce'],
            ]],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Saved; the survey with the answers', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/SurveyQuestion')),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 422, description: 'An unknown question or option, two answers to a radio question, or free text without its option', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function store(SaveSurveyAnswersRequest $request): SurveyQuestionCollection
    {
        $user = $request->user();
        $questions = $request->questions();

        DB::transaction(function () use ($request, $user, $questions): void {
            $user->surveyAnswers()->delete();

            foreach ($request->input('answers') as $answer) {
                $question = $questions->get($answer['question']);

                $user->surveyAnswers()->create([
                    'survey_question_id' => $question->id,
                    'options' => array_values($answer['options']),
                    'other_text' => in_array($question->other_option, $answer['options'], true) ? ($answer['other'] ?? null) : null,
                ]);
            }

            $user->forceFill(['survey_completed_at' => now()])->save();
        });

        return new SurveyQuestionCollection($this->survey($user->id));
    }

    /**
     * The visible steps with only this owner's answers loaded.
     *
     * @return Collection<int, SurveyQuestion>
     */
    private function survey(int $userId): Collection
    {
        return SurveyQuestion::visible()
            ->with(['options', 'answers' => fn (HasMany $query) => $query->where('user_id', $userId)])
            ->get();
    }
}
