<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('POST /api/v1/auth/register', function () {
    it('registers a new user and returns access token', function () {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password1!',
            'business_name' => 'Test Store',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['user', 'access_token', 'token_type'],
            ])
            ->assertJsonPath('data.user.email', 'test@example.com');
    });

    it('returns 422 when email is already taken', function () {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test',
            'email' => 'taken@example.com',
            'password' => 'Password1!',
            'business_name' => 'Store',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    });

    it('returns 422 when required fields are missing', function () {
        $this->postJson('/api/v1/auth/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'business_name']);
    });
});

describe('POST /api/v1/auth/login', function () {
    it('returns access token with valid credentials', function () {
        $user = User::factory()->create(['password' => bcrypt('Password1!')]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password1!',
        ])->assertStatus(200)
            ->assertJsonStructure(['data' => ['access_token', 'token_type']]);
    });

    it('returns 422 with invalid credentials', function () {
        User::factory()->create(['email' => 'user@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'WrongPassword',
        ])->assertStatus(422);
    });
});

describe('POST /api/v1/auth/logout', function () {
    it('revokes token and returns 200', function () {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Successfully logged out.');

        // Token should no longer work — reset guard cache between requests
        $this->app['auth']->forgetGuards();
        $this->withToken($token)
            ->getJson('/api/v1/dashboard')
            ->assertStatus(401);
    });
});
