<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A photo for each use-case card that has none, so the cards show a picture in their shape until
 * the client uploads their own from the admin. Free photos under the Unsplash License, shipped in
 * storage/app/public/marketing/home/use-cases and copied to the bucket by marketing:publish-assets.
 * Unsplash ids: creators 1626379953822-baec19c3accd, ecommerce 1566576721346-d4a3b4eaeb55,
 * restaurants 1555396273-367ea4eb4db5, clinics 1629909613654-28e377c37b09,
 * agencies 1552664730-d307ca884978, realestate 1600596542815-ffad4c1539a9.
 */
return new class extends Migration
{
    private const KEYS = ['creators', 'ecommerce', 'restaurants', 'clinics', 'agencies', 'realestate'];

    public function up(): void
    {
        foreach (self::KEYS as $key) {
            DB::table('use_cases')->where('key', $key)->whereNull('photo')->update(['photo' => "marketing/home/use-cases/{$key}.webp"]);
        }
    }

    public function down(): void
    {
        foreach (self::KEYS as $key) {
            DB::table('use_cases')->where('key', $key)->where('photo', "marketing/home/use-cases/{$key}.webp")->update(['photo' => null]);
        }
    }
};
