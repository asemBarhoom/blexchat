<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('ai_replies_limit')->default(0)->after('has_ai');
        });

        DB::table('plans')->where('slug', 'pro')->update(['ai_replies_limit' => 250]);
        DB::table('plans')->where('slug', 'business')->update(['ai_replies_limit' => 1500]);
        DB::table('plans')->where('slug', 'advanced')->update(['ai_replies_limit' => 5000]);

        Schema::create('ai_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('automation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 30)->default('anthropic');
            $table->string('model', 100)->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('estimated_cost_micros')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usages');

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('ai_replies_limit');
        });
    }
};
