<?php

use App\Filament\Resources\ConversationResource\Pages\ListConversations;
use App\Filament\Resources\ConversationResource\Pages\ViewConversation;
use App\Filament\Resources\ConversationResource\RelationManagers\MessagesRelationManager;
use App\Models\Conversation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@blexchat.test')->firstOrFail());
});

it('filters conversations by who answers them', function () {
    $handedOver = Conversation::factory()->create(['state' => 'human']);
    $automated = Conversation::factory()->create(['state' => 'automation']);

    Livewire::test(ListConversations::class)
        ->filterTable('state', 'human')
        ->assertCanSeeTableRecords([$handedOver])
        ->assertCanNotSeeTableRecords([$automated]);
});

it('shows the handover reason, the AI note and system messages as the system', function () {
    $conversation = Conversation::factory()->create();
    $conversation->handOver('ai_unsure', 'Asked about bulk wholesale prices.');
    $conversation->messages()->create(['direction' => 'outbound', 'type' => 'text', 'content' => 'Delivery takes 2 days.', 'automated' => true, 'by_ai' => true, 'sent_at' => now()]);

    $this->get('/admin/conversations/'.$conversation->id)
        ->assertOk()
        ->assertSee('محوّلة لموظف')
        ->assertSee('Asked about bulk wholesale prices.');

    Livewire::test(MessagesRelationManager::class, ['ownerRecord' => $conversation, 'pageClass' => ViewConversation::class])
        ->assertSee('النظام')
        ->assertSee('تحويل لموظف')
        ->assertSee('Asked about bulk wholesale prices.')
        ->assertSee('Delivery takes 2 days.');
});
