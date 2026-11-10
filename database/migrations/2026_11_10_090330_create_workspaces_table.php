<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BE-11: an owner can run several businesses (an agency's clients), each a workspace with its own
 * channels, automations, folders, contacts and team. The plan and its limits stay the owner's.
 * Every existing account gets one workspace holding everything it has today.
 */
return new class extends Migration
{
    /** Tables whose rows belong to one workspace. */
    private const SCOPED = ['channels', 'automations', 'automation_folders', 'contacts', 'team_invitations'];

    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        foreach (self::SCOPED as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('workspace_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        Schema::table('team_invitations', function (Blueprint $table) {
            $table->index('owner_id');
            $table->dropUnique(['owner_id', 'email']);
            $table->unique(['workspace_id', 'email']);
        });

        Schema::table('automation_folders', function (Blueprint $table) {
            $table->index('user_id');
            $table->dropUnique(['user_id', 'name']);
            $table->unique(['workspace_id', 'name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->after('team_owner_id')->constrained()->nullOnDelete();
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('workspaces_limit')->default(1)->after('teammates_limit');
        });

        DB::table('plans')->where('slug', 'advanced')->update(['workspaces_limit' => 10]);

        DB::table('users')->whereNull('team_owner_id')->orderBy('id')->each(function (object $user): void {
            $workspaceId = DB::table('workspaces')->insertGetId([
                'owner_id' => $user->id,
                'name' => $user->business_name ?: ($user->name ?: $user->email),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (self::SCOPED as $name) {
                DB::table($name)->where($name === 'team_invitations' ? 'owner_id' : 'user_id', $user->id)->update(['workspace_id' => $workspaceId]);
            }

            DB::table('users')->where('team_owner_id', $user->id)->update(['workspace_id' => $workspaceId]);
        });
    }

    public function down(): void
    {
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn('workspaces_limit'));

        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'email']);
            $table->unique(['owner_id', 'email']);
            $table->dropIndex(['owner_id']);
        });

        Schema::table('automation_folders', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'name']);
            $table->unique(['user_id', 'name']);
            $table->dropIndex(['user_id']);
        });

        foreach ([...self::SCOPED, 'users'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('workspace_id'));
        }

        Schema::dropIfExists('workspaces');
    }
};
