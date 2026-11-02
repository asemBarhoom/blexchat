<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('survey_question_id')->constrained()->cascadeOnDelete();
            $table->string('key')->comment('Stable id; the frontend picks the icon by it');
            $table->json('label');
            $table->json('note')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['survey_question_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_options');
    }
};
