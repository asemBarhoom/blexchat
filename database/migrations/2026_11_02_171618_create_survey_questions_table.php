<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The onboarding survey (W08), one row per step.
     */
    public function up(): void
    {
        Schema::create('survey_questions', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique()->comment('Stable id the frontend and answers refer to');
            $table->json('question');
            $table->json('hint')->nullable();
            $table->string('kind')->comment('radio | multi | grid');
            $table->string('other_option')->nullable()->comment('Option key that reveals a free-text field');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_questions');
    }
};
