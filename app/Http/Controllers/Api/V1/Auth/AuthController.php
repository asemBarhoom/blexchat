<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Authentication', description: 'Register, login, and token management')]
class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/v1/auth/register',
        summary: 'Register a new business account',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'business_name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Omar Al-Rashid'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'omar@store.com'),
                    new OA\Property(property: 'password', type: 'string', minLength: 8, example: 'S3cur3P@ss!'),
                    new OA\Property(property: 'business_name', type: 'string', example: "Omar's Store"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Account created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name'          => $request->name,
            'email'         => $request->email,
            'password'      => Hash::make($request->password),
            'business_name' => $request->business_name,
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'data' => [
                'user'         => new UserResource($user),
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ],
        ], 201);
    }

    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'Login and receive access token',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login successful'),
            new OA\Response(response: 422, description: 'Invalid credentials'),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'data' => [
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/auth/refresh',
        summary: 'Issue a new token and revoke the current one',
        security: [['sanctum' => []]],
        tags: ['Authentication'],
        responses: [new OA\Response(response: 200, description: 'New token issued')]
    )]
    public function refresh(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        $token = $request->user()->createToken('api')->plainTextToken;

        return response()->json([
            'data' => [
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/auth/logout',
        summary: 'Revoke the current access token',
        security: [['sanctum' => []]],
        tags: ['Authentication'],
        responses: [new OA\Response(response: 200, description: 'Logged out')]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Successfully logged out.']);
    }

    #[OA\Put(
        path: '/api/v1/auth/locale',
        summary: 'Update the authenticated user\'s preferred language',
        security: [['sanctum' => []]],
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['locale'],
                properties: [
                    new OA\Property(property: 'locale', type: 'string', enum: ['ar', 'en'], example: 'ar'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Locale updated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function setLocale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:ar,en'],
        ]);

        $request->user()->update(['locale' => $validated['locale']]);

        return response()->json(['locale' => $validated['locale']]);
    }
}
