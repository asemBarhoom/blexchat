<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // System admin for the Filament panel
        User::firstOrCreate(
            ['email' => 'admin@blexchat.test'],
            [
                'name' => 'مدير النظام',
                'password' => bcrypt('Admin@2025!'),
                'email_verified_at' => now(),
            ]
        );

        // @nourboutique demo data (realistic Gulf Arabic abaya boutique)
        $this->call([
            MarketingHomeSeeder::class,
            UserSeeder::class,
            ChannelSeeder::class,
            AutomationSeeder::class,
            PostSeeder::class,
            CommentSeeder::class,
            ConversationSeeder::class,
        ]);
    }
}
