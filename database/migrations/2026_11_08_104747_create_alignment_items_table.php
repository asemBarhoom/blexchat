<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('alignment_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('title');
            $table->string('owner', 20)->index();
            $table->string('priority', 5)->index();
            $table->string('status', 20)->index();
            $table->string('screen')->nullable();
            $table->string('figma_url')->nullable();
            $table->string('endpoint')->nullable();
            $table->text('frontend_ref')->nullable();
            $table->text('backend_ref')->nullable();
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alignment_items');
    }
};
