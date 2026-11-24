<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            // Kept when the paying customer deletes their account: the commission was still earned.
            $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();
            // The payment it was earned on (checkout:{session} or invoice:{invoice}), so a replayed event counts once.
            $table->string('source')->unique();
            $table->unsignedInteger('payment_cents');
            $table->decimal('rate', 5, 2);
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_id', 'status']);
            $table->index(['referred_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_commissions');
    }
};
