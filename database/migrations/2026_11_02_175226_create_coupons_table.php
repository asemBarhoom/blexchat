<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Discount codes for the checkout. Codes are stored upper-case, so matching ignores case.
     * Payments are not built yet: codes are checked and priced, never redeemed, so times_used stays 0.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique()->comment('Upper-case, e.g. BLEX20');
            $table->unsignedTinyInteger('percent_off')->comment('1 to 100');
            $table->boolean('active')->default(true);
            $table->timestamp('expires_at')->nullable()->comment('Null: never expires');
            $table->unsignedInteger('usage_limit')->nullable()->comment('Null: no limit');
            $table->unsignedInteger('times_used')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
