<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Blexchat AI for development (ANTHROPIC_DRIVER=fake, the default on local): no key, no network.
 * It hands over when the customer asks for a person or a price, and otherwise answers with the
 * first line of the business info, so both paths can be tried from the test events.
 */
class FakeClaudeClient extends ClaudeClient
{
    private const HANDOVER_WORDS = ['human', 'person', 'agent', 'price', 'شخص', 'موظف', 'سعر'];

    public function available(): bool
    {
        return true;
    }

    public function answer(string $businessName, string $businessInfo, array $history, string $ownerLanguage): array
    {
        $question = mb_strtolower((string) (collect($history)->last()['text'] ?? ''));
        $answer = Str::contains($question, self::HANDOVER_WORDS)
            ? ['handover' => true, 'reply' => '', 'reason' => $ownerLanguage === 'Arabic' ? 'سأل عن شيء لا تغطيه معلومات النشاط (تجربة).' : 'Asked something the business info doesn’t cover (demo).']
            : ['handover' => false, 'reply' => Str::limit(Str::before(trim($businessInfo), "\n"), 300), 'reason' => ''];

        Log::info('Blexchat AI (fake) answered', ['question' => $question, ...$answer]);

        return $answer;
    }
}
