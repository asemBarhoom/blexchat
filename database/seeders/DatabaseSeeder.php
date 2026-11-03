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
        // System admin for the Filament panel; is_admin is not mass-assignable, so it is set explicitly.
        User::firstOrCreate(
            ['email' => 'admin@blexchat.test'],
            [
                'name' => 'مدير النظام',
                'password' => bcrypt('Admin@2025!'),
            ]
        )->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();

        // @nourboutique demo data (realistic Gulf Arabic abaya boutique)
        $this->call([
            HomeContentSeeder::class,
            OnboardingSeeder::class,
            ChannelConnectedScreenSeeder::class,
            ChannelOptionSeeder::class,
            FirstMoveSeeder::class,
            UserSeeder::class,
            ChannelSeeder::class,
            AutomationSeeder::class,
            PostSeeder::class,
            CommentSeeder::class,
            ConversationSeeder::class,
        ]);
    }
}
