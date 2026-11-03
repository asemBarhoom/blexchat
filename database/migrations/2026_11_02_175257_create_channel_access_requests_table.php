<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Request access" on W06: an owner signed in to an account that another workspace already
     * connected, and asks that workspace for access to it.
     */
    public function up(): void
    {
        Schema::create('channel_access_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->comment('Who asks')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->nullable()->comment('The connected channel asked for')->constrained()->nullOnDelete();
            $table->string('platform');
            $table->string('account_id');
            $table->string('account_handle')->nullable();
            $table->string('status')->default('pending')->comment('pending | approved | declined');
            $table->text('note')->nullable()->comment('Admin note');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_access_requests');
    }
};
