<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A question and answer of a solution page's FAQ.
     */
    public function up(): void
    {
        Schema::create('solution_faq_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('solution_id')->constrained()->cascadeOnDelete();
            $table->json('question');
            $table->json('answer');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solution_faq_items');
    }
};
