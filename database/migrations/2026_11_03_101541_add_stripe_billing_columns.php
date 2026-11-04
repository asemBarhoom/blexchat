<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Card payments through Stripe Checkout: the Stripe customer per user, and the Stripe
     * subscription that pays for a plan.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('stripe_customer_id')->nullable()->unique()->after('business_name');
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('stripe_subscription_id')->nullable()->unique()->after('trial_used_at');
            $table->timestamp('current_period_end')->nullable()->after('stripe_subscription_id')->comment('When the paid period ends; Stripe renews it');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropUnique(['stripe_subscription_id']);
            $table->dropColumn(['stripe_subscription_id', 'current_period_end']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['stripe_customer_id']);
            $table->dropColumn('stripe_customer_id');
        });
    }
};
