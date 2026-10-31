<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_reply_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('keywords')->comment('Matched in any language, so not translated');
            $table->json('reply');
            $table->json('action');
            $table->json('result');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_reply_rules');
    }
};
