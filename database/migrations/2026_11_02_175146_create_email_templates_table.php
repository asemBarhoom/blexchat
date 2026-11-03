<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The admin-edited texts of the emails, one row per template; an empty text falls back to lang/.
     */
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique()->comment('Template and mode, e.g. login_link.signin');
            $table->json('subject')->nullable();
            $table->json('line')->nullable();
            $table->json('button')->nullable();
            $table->json('expiry')->nullable();
            $table->json('ignore')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
