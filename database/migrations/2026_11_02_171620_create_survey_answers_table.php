<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What each owner picked, one row per answered question. A skipped question has no row.
     * Option keys are kept as written, so an answer survives its option being renamed.
     */
    public function up(): void
    {
        Schema::create('survey_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_question_id')->constrained()->cascadeOnDelete();
            $table->json('options');
            $table->string('other_text')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'survey_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_answers');
    }
};
