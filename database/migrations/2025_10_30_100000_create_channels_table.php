<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform'); // instagram | whatsapp | tiktok | messenger
            $table->string('account_id');
            $table->string('username')->nullable();
            $table->string('profile_picture')->nullable();
            $table->text('access_token');
            $table->unsignedBigInteger('followers')->default(0);
            $table->string('status')->default('active'); // active | inactive | expired
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'platform', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
