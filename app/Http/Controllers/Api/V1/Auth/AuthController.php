<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Authentication', description: 'Passwordless sign-up and sign-in: an emailed link or Google. Both return a bearer token; send it as `Authorization: Bearer <token>`.')]
#[OA\Schema(schema: 'AuthSession', properties: [
    new OA\Property(property: 'user', ref: '#/components/schemas/User'),
    new OA\Property(property: 'access_token', type: 'string', example: '1|Xb2…'),
    new OA\Property(property: 'token_type', type: 'string', enum: ['Bearer']),
    new OA\Property(property: 'is_new_user', type: 'boolean', description: 'true: the account was just created, go to onboarding. false: go to the app.'),
])]
#[OA\Schema(schema: 'User', properties: [
    new OA\Property(property: 'id', type: 'integer', example: 1),
    new OA\Property(property: 'name', type: 'string', nullable: true, description: 'From Google; null for an email-link account until it is set', example: 'Nour'),
    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'sara@nourboutique.com'),
    new OA\Property(property: 'business_name', type: 'string', nullable: true),
    new OA\Property(property: 'role', type: 'string', enum: ['owner', 'agent'], description: 'agent: a teammate, who sees only the inbox and contacts of the owner who invited them (`GET /team`)'),
    new OA\Property(property: 'locale', type: 'string', enum: ['ar', 'en'], description: 'The language the frontend last asked for; emails and Blexchat AI use it'),
    new OA\Property(property: 'workspace', description: 'The workspace this request worked in (the `X-Workspace` header, else the first)', properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
    ], type: 'object'),
    new OA\Property(property: 'email_verified_at', type: 'string', format: 'date-time'),
    new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
])]
#[OA\Schema(schema: 'AuthError', required: ['message', 'code'], properties: [
    new OA\Property(property: 'message', type: 'string', description: 'Ready to show, in the requested language'),
    new OA\Property(property: 'code', type: 'string', enum: ['resend_too_soon', 'too_many_attempts', 'link_expired', 'google_failed'], description: 'Stable value to branch on'),
    new OA\Property(property: 'retry_after', type: 'integer', description: 'Seconds to wait; on 429 only'),
])]
#[OA\Schema(schema: 'ValidationError', properties: [
    new OA\Property(property: 'message', type: 'string', example: 'The email field must be a valid email address.'),
    new OA\Property(property: 'errors', type: 'object', example: ['email' => ['The email field must be a valid email address.']], additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string'))),
])]
class AuthController extends Controller
{
    #[OA\Get(
        path: '/api/v1/auth/me',
        summary: 'The signed-in account',
        description: 'For the app greeting ("Welcome back, Nour") and to check a stored token is still valid: 401 means sign in again.',
        security: [['sanctum' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(response: 200, description: 'The account', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/User'),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
        ],
    )]
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    #[OA\Patch(
        path: '/api/v1/auth/me',
        summary: 'Save the account name and business name',
        description: 'For the onboarding step that asks for them. Send either field or both; a field left out keeps its value, and an empty `business_name` clears it.',
        security: [['sanctum' => []]],
        tags: ['Authentication'],
        parameters: [new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the validation messages. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Nour'),
            new OA\Property(property: 'business_name', type: 'string', maxLength: 255, nullable: true, example: 'Nour Boutique'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'The updated account', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/User'),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
            new OA\Response(response: 422, description: 'An empty name, or a value over 255 characters', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function update(UpdateProfileRequest $request): UserResource
    {
        $request->user()->update($request->validated());

        return new UserResource($request->user());
    }

    #[OA\Post(
        path: '/api/v1/auth/logout',
        summary: 'Sign out',
        description: 'Revokes the token this request was made with; other devices stay signed in.',
        security: [['sanctum' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(response: 200, description: 'Signed out', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'message', type: 'string', example: 'You are signed out.'),
            ])),
            new OA\Response(response: 401, description: 'Missing, revoked or unknown token'),
        ],
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('auth.signed_out')]);
    }
}
