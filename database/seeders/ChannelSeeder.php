<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Database\Seeder;

class ChannelSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'nour@nourboutique.com')->firstOrFail();

        // Instagram — primary channel (@nourboutique)
        Channel::updateOrCreate(
            ['user_id' => $user->id, 'platform' => 'instagram', 'account_id' => '17841400000000001'],
            [
                'username' => 'nourboutique',
                'profile_picture' => 'avatars/sara.webp',
                'access_token' => 'EAAnourboutiqueInstagramToken',
                'followers' => 14800,
                'status' => 'active',
                'connected_at' => now()->subMonths(3),
            ]
        );

        // WhatsApp Business
        Channel::updateOrCreate(
            ['user_id' => $user->id, 'platform' => 'whatsapp', 'account_id' => '966501234567'],
            [
                'username' => '+962 79 123 0142',
                'profile_picture' => null,
                'access_token' => 'EAAnourboutiqueWhatsAppToken',
                'followers' => 0,
                'status' => 'active',
                'connected_at' => now()->subMonths(2),
            ]
        );
    }
}
