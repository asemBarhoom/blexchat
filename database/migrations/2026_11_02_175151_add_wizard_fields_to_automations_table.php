<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the wizard (W11+) collects beyond the trigger and the replies: the post a comment
     * automation watches, the template it started from, and the paid extras.
     */
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table): void {
            $table->foreignId('automation_template_id')->nullable()->after('channel_id')->constrained()->nullOnDelete();
            $table->string('post_mode')->nullable()->after('trigger_type')->comment('specific | any | next; null: every post');
            $table->foreignId('post_id')->nullable()->after('post_mode')->constrained()->nullOnDelete();
            $table->json('extras')->nullable()->after('actions');
        });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('automation_template_id');
            $table->dropConstrainedForeignId('post_id');
            $table->dropColumn(['post_mode', 'extras']);
        });
    }
};
