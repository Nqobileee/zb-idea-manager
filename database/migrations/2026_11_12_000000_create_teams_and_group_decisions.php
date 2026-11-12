<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // the leader
            $table->string('name');
            $table->text('about')->nullable();
            $table->timestamps();
        });

        Schema::create('team_user', function (Blueprint $table) {
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['team_id', 'user_id']);
        });

        // An idea can be put forward by a team. Every teammate can then see it and work on it.
        Schema::table('ideas', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
        });

        // A challenge can ask for more than one executive before an answer counts as accepted.
        Schema::table('challenges', function (Blueprint $table) {
            $table->unsignedSmallInteger('approvals_required')->default(1);
        });

        // One row per executive who approved an idea. They also become members of its project group.
        Schema::create('idea_approvals', function (Blueprint $table) {
            $table->foreignId('idea_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->primary(['idea_id', 'user_id']);
        });

        // Team and project groups have named members. The all-staff group (scope null) has no member rows: everyone is in it.
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('scope', 16)->nullable()->index(); // null = all staff, 'team' or 'project'
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('idea_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::create('conversation_members', function (Blueprint $table) {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['conversation_id', 'user_id']);
        });

        // Supabase exposes public tables through its REST API, so every new table gets RLS with no policies.
        if (DB::getDriverName() === 'pgsql') {
            foreach (['teams', 'team_user', 'idea_approvals', 'conversation_members'] as $t) {
                DB::statement('alter table "'.$t.'" enable row level security');
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_members');
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('idea_id');
            $table->dropConstrainedForeignId('team_id');
            $table->dropIndex(['scope']);
            $table->dropColumn('scope');
        });
        Schema::dropIfExists('idea_approvals');
        Schema::table('challenges', function (Blueprint $table) {
            $table->dropColumn('approvals_required');
        });
        Schema::table('ideas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });
        Schema::dropIfExists('team_user');
        Schema::dropIfExists('teams');
    }
};
