<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Inbox teammates per plan, the owner included, as Pricing sells them. */
    private const SEATS = ['free' => 1, 'essential' => 1, 'pro' => 3, 'business' => 10, 'advanced' => 25];

    /** "Team roles and assignment". */
    private const ASSIGNMENT = ['business', 'advanced'];

    /**
     * A teammate is a user who works in an owner's inbox and contacts; a conversation can be
     * assigned to one of the team.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('team_owner_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('teammates_limit')->default(1)->after('live_automations_limit');
            $table->boolean('has_assignment')->default(false)->after('has_email_capture');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('contact_id')->constrained('users')->nullOnDelete();
        });

        foreach (self::SEATS as $slug => $seats) {
            DB::table('plans')->where('slug', $slug)->update(['teammates_limit' => $seats, 'has_assignment' => in_array($slug, self::ASSIGNMENT, true)]);
        }
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['teammates_limit', 'has_assignment']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_owner_id');
        });
    }
};
