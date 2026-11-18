<?php

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('deletes the account and everything it owns once the email is confirmed', function () {
    $user = User::factory()->create();
    $channel = Channel::factory()->for($user)->create();
    Contact::factory()->for($user)->create();
    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/auth/me', ['confirm_email' => $user->email])
        ->assertOk()
        ->assertJsonPath('message', __('auth.account_deleted'));

    expect(User::find($user->id))->toBeNull()
        ->and(Channel::find($channel->id))->toBeNull()
        ->and(Contact::where('user_id', $user->id)->exists())->toBeFalse();
});

it('keeps the account when the confirmation email does not match', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/auth/me', ['confirm_email' => 'someone@else.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('confirm_email');

    expect(User::find($user->id))->not->toBeNull();
});

it('lets teammates keep their own sign-in when the owner deletes the account', function () {
    $owner = User::factory()->create();
    $teammate = User::factory()->create(['team_owner_id' => $owner->id]);
    Sanctum::actingAs($owner);

    $this->deleteJson('/api/v1/auth/me', ['confirm_email' => $owner->email])->assertOk();

    expect($teammate->fresh())->not->toBeNull()
        ->and($teammate->fresh()->team_owner_id)->toBeNull();
});

it('stops a card plan before deleting the account', function () {
    $user = User::factory()->create();
    Subscription::factory()->for($user)->create(['stripe_subscription_id' => 'sub_123']);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('cancelSubscription')->once()->with('sub_123');
    app()->instance(PaymentGateway::class, $gateway);
    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/auth/me', ['confirm_email' => $user->email])->assertOk();

    expect(User::find($user->id))->toBeNull();
});
