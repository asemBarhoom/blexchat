<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The plan ladder shown on the onboarding plan screen (W09) and the checkout. Prices are whole
     * US dollars a month; annual_price is per month, billed yearly. Contacts are never unlimited.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique()->comment('free | essential | pro | business | advanced');
            $table->json('name');
            $table->json('tagline')->nullable();
            $table->unsignedInteger('monthly_price')->default(0);
            $table->unsignedInteger('annual_price')->default(0);
            $table->unsignedInteger('contacts_limit');
            $table->boolean('has_ai')->default(false);
            $table->json('features')->nullable();
            $table->unsignedSmallInteger('trial_days')->default(0)->comment('0: sold without a free trial');
            $table->boolean('is_recommended')->default(false)->comment('"Our pick for you"');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
