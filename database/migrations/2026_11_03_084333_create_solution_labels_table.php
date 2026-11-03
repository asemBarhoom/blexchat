<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The words every solution page shares (section titles, buttons, trigger names), per locale. One row only.
     */
    public function up(): void
    {
        Schema::create('solution_labels', function (Blueprint $table): void {
            $table->id();
            $table->json('texts')->comment('The template copy per locale; one row only');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solution_labels');
    }
};
