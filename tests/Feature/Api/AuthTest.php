<?php

use App\Mail\LoginLinkMail;
use App\Models\LoginLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

/**
 * Sends a link and returns the plain token, read from the email the way the frontend's verify page gets it.
 */
function emailedToken(string $email, string $mode = 'signup'): string
{
    test()->postJson('/api/v1/auth/email-link', ['email' => $email, 'mode' => $mode])->assertAccepted();

    $token = null;
    Mail::assertSent(LoginLinkMail::class, function (LoginLinkMail $mail) use (&$token): bool {
        $token = $mail->token;

        return true;
    });

    return $token;
}

function googleState(int $expires = 0): string
{
    return Crypt::encryptString(json_encode(['purpose' => 'google', 'expires' => $expires ?: now()->addMinutes(15)->timestamp]));
}

describe('POST /api/v1/auth/email-link', function () {
    it('emails a sign-in link that opens the frontend verify page', function () {
        $this->postJson('/api/v1/auth/email-link', ['email' => ' Sara@NourBoutique.com '])
            ->assertAccepted()
            ->assertExactJson([
                'message' => 'Check your email.',
                'data' => ['email' => 'sara@nourboutique.com', 'mode' => 'signup', 'expires_in' => 900, 'resend_after' => 59],
            ]);

        Mail::assertSent(LoginLinkMail::class, function (LoginLinkMail $mail): bool {
            $mail->assertSeeInHtml('Confirm my email')->assertSeeInHtml('This link works for 15 minutes.');

            return $mail->hasTo('sara@nourboutique.com')
                && $mail->envelope()->subject === 'Confirm your email'
                && str_contains($mail->render(), config('app.frontend_url').'/verify/confirm?token='.$mail->token);
        });

        expect(LoginLink::sole()->token_hash)->not->toContain(Mail::sent(LoginLinkMail::class)->first()->token);
    });

    it('writes the sign-in email in the sign-in voice, and in Arabic when asked', function () {
        $this->postJson('/api/v1/auth/email-link?lang=ar', ['email' => 'nour@example.com', 'mode' => 'signin'])->assertAccepted();

        Mail::assertSent(LoginLinkMail::class, fn (LoginLinkMail $mail): bool => $mail->locale === 'ar'
            && str_contains($mail->render(), '&amp;mode=signin'));

        $mail = Mail::sent(LoginLinkMail::class)->first();
        app()->setLocale('ar');
        expect($mail->envelope()->subject)->toBe('رابط تسجيل الدخول');
    });

    it('returns the link itself only when a development server allows it', function () {
        config(['auth.expose_login_links' => true]);

        $response = $this->postJson('/api/v1/auth/email-link', ['email' => 'sara@nourboutique.com', 'mode' => 'signin'])->assertAccepted();
        $token = Mail::sent(LoginLinkMail::class)->first()->token;

        expect($response->json('data.dev_token'))->toBe($token)
            ->and($response->json('data.dev_link'))->toBe(config('app.frontend_url').'/verify/confirm?token='.$token.'&mode=signin');

        $this->postJson('/api/v1/auth/email-link/verify', ['token' => $response->json('data.dev_token')])->assertOk();
    });

    it('refuses an invalid email or mode', function () {
        $this->postJson('/api/v1/auth/email-link', ['email' => 'sara@nourboutique', 'mode' => 'admin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'mode']);

        Mail::assertNothingSent();
    });

    it('makes the visitor wait 59 seconds before resending', function () {
        emailedToken('sara@nourboutique.com');

        $this->travel(30)->seconds();
        $this->postJson('/api/v1/auth/email-link', ['email' => 'sara@nourboutique.com'])
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'resend_too_soon')
            ->assertJsonPath('retry_after', 29);

        $this->travel(30)->seconds();
        $this->postJson('/api/v1/auth/email-link', ['email' => 'sara@nourboutique.com'])->assertAccepted();
    });

    it('stops after five links in fifteen minutes', function () {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/email-link', ['email' => 'sara@nourboutique.com'])->assertAccepted();
            $this->travel(60)->seconds();
        }

        $this->postJson('/api/v1/auth/email-link', ['email' => 'sara@nourboutique.com'])
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'too_many_attempts')
            ->assertJsonPath('message', 'Too many attempts. Try again in a few minutes.');
    });
});

describe('POST /api/v1/auth/email-link/verify', function () {
    it('creates the account on first sign-in and returns a working token', function () {
        $token = emailedToken('sara@nourboutique.com');

        $response = $this->postJson('/api/v1/auth/email-link/verify', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.is_new_user', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'sara@nourboutique.com')
            ->assertJsonPath('data.user.name', null);

        expect(User::sole()->email_verified_at)->not->toBeNull();

        $this->withToken($response->json('data.access_token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'sara@nourboutique.com');
    });

    it('signs a returning user into the same account', function () {
        $user = User::factory()->create(['email' => 'nour@nourboutique.com', 'name' => 'Nour']);

        $this->postJson('/api/v1/auth/email-link/verify', ['token' => emailedToken('nour@nourboutique.com', 'signin')])
            ->assertOk()
            ->assertJsonPath('data.is_new_user', false)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', 'Nour');

        expect(User::count())->toBe(1);
    });

    it('works only once', function () {
        $token = emailedToken('sara@nourboutique.com');

        $this->postJson('/api/v1/auth/email-link/verify', ['token' => $token])->assertOk();
        $this->postJson('/api/v1/auth/email-link/verify', ['token' => $token])
            ->assertGone()
            ->assertJsonPath('code', 'link_expired')
            ->assertJsonPath('data.email', 'sara@nourboutique.com');
    });

    it('expires after fifteen minutes and names the address for a new link', function () {
        $token = emailedToken('sara@nourboutique.com', 'signin');

        $this->travel(16)->minutes();

        $this->postJson('/api/v1/auth/email-link/verify', ['token' => $token])
            ->assertGone()
            ->assertJsonPath('code', 'link_expired')
            ->assertJsonPath('data', ['email' => 'sara@nourboutique.com', 'mode' => 'signin']);

        expect(User::count())->toBe(0);
    });

    it('refuses an unknown token without naming an address', function () {
        $this->postJson('/api/v1/auth/email-link/verify', ['token' => 'not-a-real-token'])
            ->assertGone()
            ->assertJsonPath('data.email', null);

        $this->postJson('/api/v1/auth/email-link/verify', [])->assertUnprocessable()->assertJsonValidationErrors('token');
    });

    it('never grants admin access through sign-up', function () {
        $this->postJson('/api/v1/auth/email-link', ['email' => 'sneaky@example.com', 'is_admin' => true])->assertAccepted();
        $token = Mail::sent(LoginLinkMail::class)->first()->token;

        $this->postJson('/api/v1/auth/email-link/verify', ['token' => $token, 'is_admin' => true])->assertOk();

        expect(User::sole()->is_admin)->toBeFalse();
    });
});

describe('Google sign-in', function () {
    it('gives the Google consent URL, returning to the frontend verify page', function () {
        config(['services.google.client_id' => 'client-123']);

        $url = $this->getJson('/api/v1/auth/google')->assertOk()->json('data.url');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        expect($url)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?')
            ->and($query['client_id'])->toBe('client-123')
            ->and($query['redirect_uri'])->toBe(config('app.frontend_url').'/verify/confirm')
            ->and($query['scope'])->toBe('openid email profile')
            ->and(json_decode(Crypt::decryptString($query['state']), true)['purpose'])->toBe('google');
    });

    it('signs in on the API callback when Google is set to return there', function () {
        $callback = url('/api/v1/auth/google/callback');
        config(['services.google.redirect_uri' => $callback]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-token']),
            'openidconnect.googleapis.com/*' => Http::response(['sub' => 'g-7', 'email' => 'sara@gmail.com', 'email_verified' => true, 'name' => 'Sara']),
        ]);

        $url = $this->getJson('/api/v1/auth/google')->json('data.url');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        expect($query['redirect_uri'])->toBe($callback);

        $this->getJson('/api/v1/auth/google/callback?'.http_build_query(['code' => 'auth-code', 'state' => $query['state'], 'scope' => 'email']))
            ->assertOk()
            ->assertJsonPath('data.user.email', 'sara@gmail.com')
            ->assertJsonPath('data.is_new_user', true);

        // The code must be traded with the same redirect URI Google sent it to.
        Http::assertSent(fn ($request): bool => $request->url() === 'https://oauth2.googleapis.com/token' && $request['redirect_uri'] === $callback);
    });

    it('refuses the callback when Google returns an error instead of a code', function () {
        Http::fake();

        $this->getJson('/api/v1/auth/google/callback?'.http_build_query(['error' => 'access_denied', 'state' => googleState()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        Http::assertNothingSent();
    });

    it('creates the account with the Google name and links it', function () {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-token']),
            'openidconnect.googleapis.com/*' => Http::response(['sub' => 'g-42', 'email' => 'Nour@Gmail.com', 'email_verified' => true, 'name' => 'Nour']),
        ]);

        $this->postJson('/api/v1/auth/google', ['code' => 'auth-code', 'state' => googleState()])
            ->assertOk()
            ->assertJsonPath('data.is_new_user', true)
            ->assertJsonPath('data.user.name', 'Nour')
            ->assertJsonPath('data.user.email', 'nour@gmail.com');

        expect(User::sole()->google_id)->toBe('g-42');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://oauth2.googleapis.com/token' && $request['code'] === 'auth-code');
    });

    it('signs an email-link account in through Google without duplicating it', function () {
        $user = User::factory()->create(['email' => 'nour@gmail.com', 'name' => 'Nour Q.']);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-token']),
            'openidconnect.googleapis.com/*' => Http::response(['sub' => 'g-42', 'email' => 'nour@gmail.com', 'email_verified' => true, 'name' => 'Nour']),
        ]);

        $this->postJson('/api/v1/auth/google', ['code' => 'auth-code', 'state' => googleState()])
            ->assertOk()
            ->assertJsonPath('data.is_new_user', false)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', 'Nour Q.');
    });

    it('reports google_failed when Google refuses the code or the email is unverified', function (array $responses) {
        Http::fake($responses);

        $this->postJson('/api/v1/auth/google', ['code' => 'auth-code', 'state' => googleState()])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'google_failed')
            ->assertJsonPath('message', 'Google sign-in didn’t finish. Try again, or continue with email.');

        expect(User::count())->toBe(0);
    })->with([
        'refused code' => fn (): array => ['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)],
        'unverified email' => fn (): array => [
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-token']),
            'openidconnect.googleapis.com/*' => Http::response(['sub' => 'g-1', 'email' => 'x@gmail.com', 'email_verified' => false]),
        ],
    ]);

    it('refuses a forged or stale state before calling Google', function (string $state) {
        Http::fake();

        $this->postJson('/api/v1/auth/google', ['code' => 'auth-code', 'state' => $state])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('state');

        Http::assertNothingSent();
    })->with([
        'forged' => ['not-encrypted'],
        'stale' => fn () => googleState(now()->subMinute()->timestamp),
    ]);
});

describe('GET /api/v1/auth/me and POST /api/v1/auth/logout', function () {
    it('refuses a request without a token', function () {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    });

    it('revokes only the current token', function () {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;
        $otherDevice = $user->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'You are signed out.');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withToken($otherDevice)->getJson('/api/v1/auth/me')->assertOk();
    });
});

describe('PATCH /api/v1/auth/me', function () {
    it('refuses a request without a token', function () {
        $this->patchJson('/api/v1/auth/me', ['name' => 'Nour'])->assertUnauthorized();
    });

    it('saves the name and business name from onboarding', function () {
        $user = User::factory()->create(['name' => null, 'business_name' => null]);

        $this->actingAs($user)->patchJson('/api/v1/auth/me', ['name' => ' Nour ', 'business_name' => 'Nour Boutique'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nour')
            ->assertJsonPath('data.business_name', 'Nour Boutique');

        $this->getJson('/api/v1/auth/me')->assertJsonPath('data.business_name', 'Nour Boutique');
    });

    it('keeps a field that is left out, and clears an empty business name', function () {
        $user = User::factory()->create(['name' => 'Nour', 'business_name' => 'Nour Boutique']);

        $this->actingAs($user)->patchJson('/api/v1/auth/me', ['business_name' => ''])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nour')
            ->assertJsonPath('data.business_name', null);
    });

    it('refuses an empty or overlong name', function (mixed $name) {
        $user = User::factory()->create(['name' => 'Nour']);

        $this->actingAs($user)->patchJson('/api/v1/auth/me', ['name' => $name])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        expect($user->fresh()->name)->toBe('Nour');
    })->with([
        'blank' => ['   '],
        'too long' => [str_repeat('a', 256)],
    ]);
});

describe('CORS for the frontend', function () {
    it('lets the frontend origin call the API with a bearer token', function (string $method) {
        $origin = config('app.frontend_url');

        $this->call('OPTIONS', '/api/v1/auth/me', server: [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $method,
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
        ])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Access-Control-Allow-Methods', $method)
            ->assertHeader('Access-Control-Allow-Headers', 'authorization,content-type');
    })->with(['GET', 'POST', 'PATCH']);
});
