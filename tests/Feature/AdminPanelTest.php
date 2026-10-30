<?php

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@blexchat.test')->firstOrFail());
});

it('renders every admin list page', function (string $path) {
    $this->get($path)->assertOk();
})->with(['/admin', '/admin/channels', '/admin/automations', '/admin/automations/create', '/admin/conversations', '/admin/posts']);

it('renders every admin detail page with seeded data', function () {
    $this->get('/admin/channels/'.Channel::firstOrFail()->id)->assertOk();
    $this->get('/admin/automations/'.Automation::firstOrFail()->id)->assertOk();
    $this->get('/admin/automations/'.Automation::firstOrFail()->id.'/edit')->assertOk();
    $this->get('/admin/conversations/'.Conversation::firstOrFail()->id)->assertOk();
    $this->get('/admin/posts/'.Post::firstOrFail()->id)->assertOk()->assertSee('عباية الشتاء وصلت');
});
