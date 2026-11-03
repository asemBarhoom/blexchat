<?php

use App\Filament\Resources\FirstMoveResource\Pages\ManageFirstMoves;
use App\Models\FirstMove;
use App\Models\User;
use Database\Seeders\FirstMoveSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FirstMoveSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
});

it('creates, edits and deletes a first move in both languages', function () {
    Livewire::test(ManageFirstMoves::class)
        ->callAction(CreateAction::class, [
            'key' => 'story-replies',
            'trigger' => 'story',
            'template' => 'story-thanks',
            'title' => ['en' => 'Thank story replies', 'ar' => 'اشكر من يرد على قصتك'],
            'line' => ['en' => 'Reply to everyone who answers your story.', 'ar' => 'ردّ على كل من يتفاعل مع قصتك.'],
            'incoming_who' => 'sara.m',
            'incoming_kind' => ['en' => 'Story reply', 'ar' => 'رد على قصة'],
            'incoming_text' => ['en' => '🔥🔥', 'ar' => '🔥🔥'],
            'reply' => ['en' => 'Thank you!', 'ar' => 'شكراً لك!'],
            'active' => true,
        ])
        ->assertHasNoFormErrors();

    $move = FirstMove::where('key', 'story-replies')->firstOrFail();
    expect($move->sort)->toBe(2);

    $this->actingAs(User::factory()->create())->getJson('/api/v1/onboarding/first-moves?lang=ar')
        ->assertJsonPath('data.2.title', 'اشكر من يرد على قصتك')
        ->assertJsonPath('data.2.wizard_path', '/onboarding/automation/new?trigger=story&template=story-thanks');

    $this->actingAs(User::factory()->admin()->create());
    Livewire::test(ManageFirstMoves::class)
        ->callAction(TestAction::make('edit')->table($move), ['trigger' => 'dm', 'template' => null])
        ->assertHasNoFormErrors();
    expect($move->fresh())->trigger->toBe('dm')->template->toBeNull();

    Livewire::test(ManageFirstMoves::class)->callAction(TestAction::make('delete')->table($move));
    expect(FirstMove::find($move->id))->toBeNull();
});

it('refuses a trigger the wizard does not start on, and a key already taken', function () {
    Livewire::test(ManageFirstMoves::class)
        ->callAction(CreateAction::class, [
            'key' => 'comment-dm',
            'trigger' => 'reel',
            'title' => ['en' => 'Reels'],
            'line' => ['en' => 'Answer reel comments.'],
            'incoming_who' => 'sara.m',
            'incoming_kind' => ['en' => 'Comment'],
            'incoming_text' => ['en' => 'Price?'],
            'reply' => ['en' => 'Sent!'],
        ])
        ->assertHasFormErrors(['trigger', 'key' => 'unique']);
});
