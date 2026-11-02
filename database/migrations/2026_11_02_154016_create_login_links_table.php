<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Emailed sign-in links. Only a hash of the token is kept, so a leaked table cannot sign anyone in.
     */
    public function up(): void
    {
        Schema::create('login_links', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->index();
            $table->string('token_hash', 64)->unique();
            $table->string('mode')->comment('signup | signin');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_links');
    }
};
