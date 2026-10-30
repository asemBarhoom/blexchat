<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        Schema::dropIfExists('marketing_pages');
    }
};
