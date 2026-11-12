<?php

use App\Models\Plan;
use Database\Seeders\ArabicPlanNamesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('gives the plans Arabic names and keeps a name written in the admin', function () {
    $free = Plan::factory()->create(['slug' => 'free', 'name' => ['en' => 'Free', 'ar' => 'Free']]);
    $pro = Plan::factory()->create(['slug' => 'pro', 'name' => ['en' => 'Pro', 'ar' => 'برو']]);

    $this->seed(ArabicPlanNamesSeeder::class);
    $this->seed(ArabicPlanNamesSeeder::class);

    expect($free->fresh()->getTranslation('name', 'ar'))->toBe('مجانية')
        ->and($free->fresh()->getTranslation('name', 'en'))->toBe('Free')
        ->and($pro->fresh()->getTranslation('name', 'ar'))->toBe('برو');

    $this->getJson('/api/v1/plans?lang=ar')->assertOk()->assertJsonFragment(['id' => 'free', 'name' => 'مجانية']);
});
