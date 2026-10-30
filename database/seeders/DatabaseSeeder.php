<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'admin@blexchat.test'],
            [
                'name' => 'مدير النظام',
                'email' => 'admin@blexchat.test',
                'password' => bcrypt('Admin@2025!'),
                'email_verified_at' => now(),
            ]
        );
    }
}
