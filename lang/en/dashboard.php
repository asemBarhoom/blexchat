<?php

return [
    'checklist' => [
        'title' => 'Your first steps',
        'connect' => 'Connect a channel',
        'automation' => 'Build your first automation',
        'test' => 'Send yourself a test comment',
    ],

    'start_here' => [
        'title' => 'Start here',
        'lead' => 'Three moves that pay for themselves in a week.',
        'comment_to_dm' => ['title' => 'Comment to DM', 'line' => 'Someone comments a keyword, they get the link in their DMs.', 'trigger' => 'Post or reel comment'],
        'story_reply' => ['title' => 'Story reply', 'line' => 'Every reply to a story gets an answer, even at 2am.', 'trigger' => 'Story reply'],
        'welcome_dm' => ['title' => 'Welcome DM', 'line' => 'First-time messages get a greeting and your catalogue.', 'trigger' => 'Direct message'],
    ],

    'last_seven' => [
        'title' => 'Your last 7 days',
        'engagement' => ['label' => 'Engagement winners', 'hint' => 'Which posts pulled the most comments'],
        'time' => ['label' => 'Time saved', 'hint' => 'Hours you did not spend answering'],
        'leads' => ['label' => 'Leads collected', 'hint' => 'Emails and numbers captured'],
        'posts' => '{1} :count post|[2,*] :count posts',
        'lead_count' => '{0} None yet|{1} :count lead|[2,*] :count leads',
        'top_post' => '«:caption» pulled :count of them.',
        'replies' => ':count replies went out without you.',
        'hours_minutes' => ':hoursh :minutesm',
        'hours' => ':hoursh',
        'minutes' => ':minutesm',
    ],
];
