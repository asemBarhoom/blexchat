<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_compare_cells', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pricing_compare_row_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->boolean('included')->default(false)->comment('A tick or a dash, when there is no text');
            $table->json('text')->nullable()->comment('What the plan gets in words; {contacts} is the plan limit');
            $table->timestamps();

            $table->unique(['pricing_compare_row_id', 'plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_compare_cells');
    }
};
