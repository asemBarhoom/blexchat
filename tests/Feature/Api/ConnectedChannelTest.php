<?php

use App\Models\Channel;
use App\Models\ChannelConnectedScreen;
use App\Models\User;
use Database\Seeders\ChannelConnectedScreenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ChannelConnectedScreenSeeder::class);
    $this->user = User::factory()->create();
});

describe('GET /api/v1/channels/{channel}', function () {
    it('returns 401 when unauthenticated', function () {
        $this->getJson('/api/v1/channels/'.Channel::factory()->create()->id)->assertUnauthorized();
    });

    it('returns the connected account with its platform’s success copy', function () {
        $channel = Channel::factory()->for($this->user)->create([
            'platform' => 'instagram',
            'username' => '@nourboutique',
            'profile_picture' => 'avatars/sara.webp',
            'followers' => 12400,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/channels/{$channel->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $channel->id)
            ->assertJsonPath('data.username', '@nourboutique')
            ->assertJsonPath('data.profile_picture', url('/storage/avatars/sara.webp'))
            ->assertJsonPath('data.followers', 12400)
            ->assertJsonPath('data.next_step', 'survey')
            ->assertJsonPath('data.screen', [
                'platform' => 'instagram',
                'channel_name' => 'Instagram',
                'title' => 'Your Instagram is connected!',
                'lead' => 'Blexchat can now answer comments and DMs on your account. Next, a few quick questions so we can suggest your first automation.',
                'account_kind' => 'Instagram · Professional account',
                'status_label' => 'Connected',
                'continue_label' => 'Continue',
                'connect_another_label' => 'Connect another channel',
            ])
            ->assertJsonMissingPath('data.access_token');
    });

    it('answers in Arabic with ?lang=ar', function () {
        $channel = Channel::factory()->for($this->user)->create(['platform' => 'tiktok']);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/channels/{$channel->id}?lang=ar")
            ->assertOk()
            ->assertJsonPath('data.screen.title', 'تم ربط TikTok بنجاح!')
            ->assertJsonPath('data.screen.continue_label', 'متابعة');
    });

    it('returns no copy when the admin switched the platform off', function () {
        ChannelConnectedScreen::where('platform', 'whatsapp')->update(['active' => false]);
        $channel = Channel::factory()->for($this->user)->create(['platform' => 'whatsapp']);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/channels/{$channel->id}")
            ->assertOk()
            ->assertJsonPath('data.screen', null);
    });

    it('returns 403 for another owner’s channel', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/channels/'.Channel::factory()->create()->id)
            ->assertForbidden();
    });

    it('returns 404 for an unknown channel', function () {
        $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/channels/999999')->assertNotFound();
    });
});
