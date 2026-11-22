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
        $this->call([
            HomeContentSeeder::class,
            SolutionSeeder::class,
            OnboardingSeeder::class,
            ChannelConnectedScreenSeeder::class,
            CheckoutSeeder::class,
            AutomationTemplateSeeder::class,
            ChannelConnectScreenSeeder::class,
            PricingPageSeeder::class,
            HelpArticleSeeder::class,
            AuthScreenSeeder::class,
            LegalPageSeeder::class,
            EmailTemplateSeeder::class,
            AlignmentItemSeeder::class,
            ChannelOptionSeeder::class,
            FirstMoveSeeder::class,
        ]);

        if (app()->isProduction()) {
            return;
        }

        // Local and test environments get an admin and the frontend's sample boutique.
        User::firstOrCreate(
            ['email' => 'admin@blexchat.test'],
            [
                'name' => 'مدير النظام',
                'password' => bcrypt('Admin@2025!'),
            ]
        )->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();

        $this->call([
            UserSeeder::class,
            ChannelSeeder::class,
            PostSeeder::class,
            AutomationSeeder::class,
            CommentSeeder::class,
            ConversationSeeder::class,
            ContactSeeder::class,
            AutomationHistorySeeder::class,
        ]);
    }
}
