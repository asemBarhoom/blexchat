<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_blocks', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->unsignedSmallInteger('sort')->nullable()->comment('Position among page sections; null for navigation, footer and demo replies');
            $table->json('texts')->comment('Visible copy per locale');
            $table->json('settings')->comment('Images, links, numbers and frontend keys shared by every locale');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_blocks');
    }
};
