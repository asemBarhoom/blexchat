<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comparison_points', function (Blueprint $table): void {
            $table->id();
            $table->string('side')->comment('before | after');
            $table->json('text');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['side', 'active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comparison_points');
    }
};
