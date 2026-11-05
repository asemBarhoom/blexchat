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

        // @nourboutique demo data: the frontend's sample fashion boutique (neutral Arabic, USD)
        $this->call([
            HomeContentSeeder::class,
            SolutionSeeder::class,
            OnboardingSeeder::class,
            ChannelConnectedScreenSeeder::class,
            CheckoutSeeder::class,
            AutomationTemplateSeeder::class,
            ChannelConnectScreenSeeder::class,
            PricingPageSeeder::class,
            AuthScreenSeeder::class,
            LegalPageSeeder::class,
            EmailTemplateSeeder::class,
            ChannelOptionSeeder::class,
            FirstMoveSeeder::class,
            UserSeeder::class,
            ChannelSeeder::class,
            PostSeeder::class,
            AutomationSeeder::class,
            CommentSeeder::class,
            ConversationSeeder::class,
            ContactSeeder::class,
        ]);
    }
}
