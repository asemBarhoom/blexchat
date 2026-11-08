<?php

namespace Database\Seeders;

use App\Models\Automation;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The contacts list's sample people (W22, W23): the six of the frontend's inbox
 * (src/pages/app/inbox/inboxContent.ts and app.inbox.contacts in its en.json), one per seeded
 * conversation and linked to it. Run after ConversationSeeder.
 */
class ContactSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'nour@nourboutique.com')->firstOrFail();
        $automation = Automation::where('user_id', $user->id)->where('name', 'Comment "PRICE" → send the catalogue')->firstOrFail();

        $contacts = [
            'reem.k' => ['handle' => 'reem.k', 'tags' => ['Winter jacket', 'Asked price', 'Cairo'], 'city' => 'Cairo', 'phone' => '+20 10 2385 4821', 'email' => 'reem.khalid@gmail.com', 'from_automation' => true, 'first_seen_at' => now()->subMinutes(5)],
            'sara.m' => ['handle' => 'sara.m', 'tags' => ['Winter jacket', 'Asked price'], 'city' => 'Amman', 'phone' => '+962 79 314 5567', 'email' => 'sara.m@outlook.com', 'from_automation' => true, 'first_seen_at' => now()->subMinutes(14)],
            'hala.q' => ['handle' => 'hala.q', 'tags' => ['Ready to order'], 'city' => 'Tunis', 'phone' => '+216 22 418 903', 'email' => 'hala.q@gmail.com', 'from_automation' => true, 'first_seen_at' => now()->subMinutes(41)],
            '+962790002210' => ['handle' => '+962 79 000 2210', 'tags' => ['Customer', 'Repeat buyer'], 'city' => 'Amman', 'phone' => '+962 79 000 2210', 'email' => 'mona.f@gmail.com', 'first_seen_at' => Carbon::create(now()->year, 8, 12, 18, 20)],
            'noor_a' => ['handle' => 'noor_a', 'tags' => ['Fan'], 'city' => 'Casablanca', 'phone' => '+212 661 234 578', 'email' => 'noor.a@hotmail.com', 'first_seen_at' => Carbon::create(now()->year, 9, 3, 16, 5)],
            'lulu.style' => ['handle' => 'lulu.style', 'tags' => ['Asked price'], 'city' => 'Beirut', 'phone' => '+961 71 245 330', 'email' => 'lulu.style@gmail.com', 'first_seen_at' => now()->subDay()],
        ];

        // The old seed's keys for Hala and Mona, before their threads matched the frontend.
        Contact::where('user_id', $user->id)->whereIn('platform_id', ['hessa.q', '+966502102210'])->delete();

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
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'city' => $data['city'],
                    'gender' => 'female',
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
