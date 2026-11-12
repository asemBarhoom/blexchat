<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('help_articles', function (Blueprint $table): void {
            $table->id();
            $table->string('category')->default('general');
            $table->json('title');
            $table->json('body');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'category', 'sort']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('help_articles');
    }
};
