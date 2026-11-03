<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_compare_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pricing_compare_group_id')->constrained()->cascadeOnDelete();
            $table->string('key')->unique()->comment('Stable id for the frontend');
            $table->json('label');
            $table->string('source')->nullable()->comment('A plan field every cell is read from (has_ai, paid); null: the cells below');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_compare_rows');
    }
};
