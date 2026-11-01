<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The frontend owns its navbar and footer, so the homepage API no longer serves them.
     */
    public function up(): void
    {
        DB::table('home_blocks')->whereIn('key', ['navigation', 'footer'])->delete();

        Schema::dropIfExists('nav_menu_items');
        Schema::dropIfExists('footer_links');
    }

    /**
     * Restores the empty tables only; their old content is not recreated.
     */
    public function down(): void
    {
        Schema::create('nav_menu_items', function (Blueprint $table): void {
            $table->id();
            $table->string('menu')->comment('product | solutions');
            $table->string('icon')->nullable();
            $table->string('channel')->nullable();
            $table->json('title');
            $table->json('description');
            $table->json('cta')->nullable();
            $table->string('target')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['menu', 'active', 'sort']);
        });

        Schema::create('footer_links', function (Blueprint $table): void {
            $table->id();
            $table->string('group')->comment('product | solutions | account | legal');
            $table->json('label');
            $table->string('url');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['group', 'active', 'sort']);
        });
    }
};
