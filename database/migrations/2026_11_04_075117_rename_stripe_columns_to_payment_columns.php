<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paddle replaces Stripe, and the fake driver serves development: the provider ids get
     * provider-neutral names (data kept), and each row says which provider it belongs to.
     */
    public function up(): void
    {
        $this->renameUnique('users', 'stripe_customer_id', 'payment_customer_id');
        $this->renameUnique('subscriptions', 'stripe_subscription_id', 'payment_subscription_id');
        $this->renameUnique('checkout_sessions', 'stripe_session_id', 'payment_session_id');

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('payment_provider')->nullable()->after('trial_used_at')->comment('fake | paddle (stripe on older rows); null when nothing is paid');
        });

        Schema::table('checkout_sessions', function (Blueprint $table): void {
            $table->renameColumn('stripe_subscription_id', 'payment_subscription_id');
        });

        Schema::table('checkout_sessions', function (Blueprint $table): void {
            $table->string('payment_provider')->nullable()->after('user_id')->comment('fake | paddle (stripe on older rows)');
        });

        DB::table('checkout_sessions')->update([
            'payment_provider' => DB::raw("CASE WHEN payment_session_id LIKE 'fake_%' THEN 'fake' ELSE 'stripe' END"),
        ]);
        DB::table('subscriptions')->whereNotNull('payment_subscription_id')->update([
            'payment_provider' => DB::raw("CASE WHEN payment_subscription_id LIKE 'fake_%' THEN 'fake' ELSE 'stripe' END"),
        ]);
    }

    public function down(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table): void {
            $table->dropColumn('payment_provider');
        });

        Schema::table('checkout_sessions', function (Blueprint $table): void {
            $table->renameColumn('payment_subscription_id', 'stripe_subscription_id');
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn('payment_provider');
        });

        $this->renameUnique('checkout_sessions', 'payment_session_id', 'stripe_session_id');
        $this->renameUnique('subscriptions', 'payment_subscription_id', 'stripe_subscription_id');
        $this->renameUnique('users', 'payment_customer_id', 'stripe_customer_id');
    }

    /**
     * Renames a uniquely indexed column, data kept. The index is dropped and added back around the
     * rename, step by step, which SQLite needs as well.
     */
    private function renameUnique(string $table, string $from, string $to): void
    {
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique([$from]));
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->renameColumn($from, $to));
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($to));
    }
};
