<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nav_menu_items', function (Blueprint $table): void {
            $table->id();
            $table->string('menu')->comment('product | solutions');
            $table->json('title');
            $table->json('description');
            $table->json('cta')->nullable();
            $table->string('target')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['menu', 'active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nav_menu_items');
    }
};
