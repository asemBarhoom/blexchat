<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "popular first move" cards of onboarding W10: each one shows a sample incoming message and
     * the automated reply, and opens the automation wizard on its trigger (and template, if any).
     */
    public function up(): void
    {
        Schema::create('first_moves', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique()->comment('Stable id for the frontend, e.g. comment-dm');
            $table->string('trigger')->comment('comment | story | dm: the wizard trigger');
            $table->string('template')->nullable()->comment('Optional wizard template key');
            $table->json('title');
            $table->json('line');
            $table->string('incoming_who')->comment('Sample handle of the person writing in');
            $table->json('incoming_text');
            $table->json('incoming_kind')->comment('Label of the incoming message: Comment, DM…');
            $table->json('reply');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('first_moves');
    }
};
