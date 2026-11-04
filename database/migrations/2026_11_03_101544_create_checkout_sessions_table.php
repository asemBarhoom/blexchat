<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Stripe Checkout Session started for a paid plan, kept until Stripe says it was paid.
     */
    public function up(): void
    {
        Schema::create('checkout_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('billing_period')->comment('monthly | annual');
            $table->string('stripe_session_id')->unique();
            $table->text('url')->nullable()->comment('The Stripe-hosted payment page');
            $table->unsignedInteger('amount_total')->comment('Cents charged for the first period, after the coupon');
            $table->string('currency', 3);
            $table->string('status')->default('open')->comment('open | complete | expired');
            $table->string('stripe_subscription_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_sessions');
    }
};
