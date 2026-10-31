<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('footer_links');
    }
};
