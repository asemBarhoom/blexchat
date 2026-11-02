<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The plans a coupon is limited to. A coupon with no rows here applies to every paid plan.
     */
    public function up(): void
    {
        Schema::create('coupon_plan', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_plan');
    }
};
