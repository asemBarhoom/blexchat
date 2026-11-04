<?php

namespace Database\Seeders;

use App\Models\Automation;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The contacts list's sample people (W23): one per seeded conversation, linked to it. Run after
 * ConversationSeeder.
 */
class ContactSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'nour@nourboutique.com')->firstOrFail();
        $automation = Automation::where('user_id', $user->id)->where('name', 'Comment "PRICE" → send the catalogue')->firstOrFail();

        $contacts = [
            'reem.k' => ['handle' => 'reem.k', 'tags' => ['Winter abaya', 'Asked price', 'Jeddah'], 'city' => 'Jeddah', 'phone' => '+966 55 123 4821', 'from_automation' => true, 'first_seen_at' => now()->subHours(1)->subMinutes(5)],
            'sara.m' => ['handle' => 'sara.m', 'tags' => ['Winter abaya', 'Asked price'], 'city' => 'Riyadh', 'from_automation' => true, 'first_seen_at' => now()->subHours(1)->subMinutes(14)],
            'hessa.q' => ['handle' => 'hessa.q', 'tags' => ['Ready to order'], 'city' => 'Riyadh', 'from_automation' => true, 'first_seen_at' => now()->subHours(1)->subMinutes(43)],
            '+966502102210' => ['handle' => '+966 50 210 2210', 'tags' => ['Customer', 'Repeat buyer'], 'city' => 'Dammam', 'phone' => '+966 50 210 2210', 'first_seen_at' => now()->subMonths(3)],
            'noor_a' => ['handle' => 'noor_a', 'tags' => ['Fan'], 'city' => 'Riyadh', 'first_seen_at' => now()->subMonths(2)],
            'lulu.style' => ['handle' => 'lulu.style', 'tags' => ['Asked price'], 'city' => 'Kuwait City', 'first_seen_at' => now()->subDay()],
        ];

        $conversations = Conversation::with('channel')
            ->whereIn('channel_id', $user->channels()->pluck('id'))
            ->whereIn('contact_platform_id', array_keys($contacts))
            ->get();

        foreach ($conversations as $conversation) {
            $data = $contacts[$conversation->contact_platform_id];

            $contact = Contact::updateOrCreate(
                ['channel_id' => $conversation->channel_id, 'platform_id' => $conversation->contact_platform_id],
                [
                    'user_id' => $user->id,
                    'platform' => $conversation->channel->platform,
                    'name' => $conversation->contact_name,
                    'handle' => $data['handle'],
                    'avatar' => $conversation->contact_avatar,
                    'phone' => $data['phone'] ?? null,
                    'city' => $data['city'],
                    'language' => 'ar',
                    'tags' => $data['tags'],
                    'source_automation_id' => ($data['from_automation'] ?? false) ? $automation->id : null,
                    'first_seen_at' => $data['first_seen_at'],
                    'last_active_at' => $conversation->last_message_at,
                ],
            );

            $conversation->update(['contact_id' => $contact->id]);
        }
    }
}
