<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_screens', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique()->comment('Screen or shared area, e.g. signup, check_email, layout');
            $table->json('texts')->comment('Visible copy per locale: a flat map of text key to string');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_screens');
    }
};
