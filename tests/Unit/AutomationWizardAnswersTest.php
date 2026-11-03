<?php

use App\Models\Automation;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function storyAnswers(array $overrides = []): array
{
    return [
        'name' => 'Reply to story replies',
        'kind' => 'story',
        'trigger' => 'any',
        'dm_text' => 'يسعدنا ردّك 💜',
        'dm_button' => 'شوفي القطعة',
        'link_url' => 'https://nourboutique.com/abaya-winter',
        ...$overrides,
    ];
}

it('reads back the answers it was saved from', function () {
    $answers = storyAnswers(['ask_follow' => true, 'follow_text' => 'تابعينا', 'follow_button' => 'تابعت ✅']);
    $automation = new Automation(Automation::fromWizard($answers));

    expect($automation->trigger_type)->toBe('story_mention')
        ->and($automation->wizardAnswers())->toMatchArray([
            'kind' => 'story',
            'post_mode' => null,
            'trigger' => 'any',
            'words' => [],
            'public_reply' => false,
            'dm_text' => 'يسعدنا ردّك 💜',
            'dm_button' => 'شوفي القطعة',
            'link_url' => 'https://nourboutique.com/abaya-winter',
            'ask_follow' => true,
            'follow_text' => 'تابعينا',
            'follow_button' => 'تابعت ✅',
            'ask_email' => false,
            'email_ask' => null,
        ]);
});

it('keeps the button label without a link but sends no button', function () {
    $automation = new Automation(Automation::fromWizard(storyAnswers(['link_url' => null])));

    expect($automation->actions)->toBe([['type' => 'send_dm', 'message' => 'يسعدنا ردّك 💜', 'button_label' => 'شوفي القطعة']])
        ->and($automation->wizardAnswers()['dm_button'])->toBe('شوفي القطعة');
});

it('reads a rule made before the wizard as watching every post', function () {
    $automation = new Automation([
        'trigger_type' => 'comment_keyword',
        'keywords' => ['السعر'],
        'actions' => [['type' => 'send_dm', 'message' => 'هلا!']],
    ]);

    expect($automation->wizardAnswers())->toMatchArray(['kind' => 'comment', 'post_mode' => 'any', 'trigger' => 'words', 'words' => ['السعر'], 'dm_text' => 'هلا!']);
});
