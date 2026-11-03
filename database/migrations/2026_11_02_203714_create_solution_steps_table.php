<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A numbered step of a solution page's "How it works".
     */
    public function up(): void
    {
        Schema::create('solution_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('solution_id')->constrained()->cascadeOnDelete();
            $table->json('text');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solution_steps');
    }
};
