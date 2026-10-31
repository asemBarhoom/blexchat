<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Homepage content now lives in home_blocks and the list tables, seeded by HomeContentSeeder.
     */
    public function up(): void
    {
        Schema::dropIfExists('marketing_pages');
    }

    /**
     * Restores the empty table only; its old content is not recreated.
     */
    public function down(): void
    {
        Schema::create('marketing_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug');
            $table->string('locale', 5);
            $table->json('content');
            $table->timestamps();
            $table->unique(['slug', 'locale']);
        });
    }
};
