<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each owner's plan. Trials take no card and do not convert: once trial_ends_at passes, the
     * account is on Free again.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('billing_period')->default('monthly')->comment('monthly | annual');
            $table->string('status')->comment('active | trialing');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('trial_used_at')->nullable()->comment('Set on the first trial; one trial per account');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
