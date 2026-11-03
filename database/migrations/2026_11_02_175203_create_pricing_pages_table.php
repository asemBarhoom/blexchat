<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_pages', function (Blueprint $table): void {
            $table->id();
            $table->json('texts')->comment('The page copy per locale; one row only');
            $table->string('agency_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_pages');
    }
};
