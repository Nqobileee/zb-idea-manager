<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One shared note per project (current focus, how to help, blockers...).
        Schema::table('ideas', function (Blueprint $table) {
            $table->text('note')->nullable();
        });

        // Members tagged on a project: they can post updates, tick off pending items and move the stage.
        Schema::create('idea_members', function (Blueprint $table) {
            $table->foreignId('idea_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->primary(['idea_id', 'user_id']);
        });

        // Progress notes on a project, and the automatic "moved from X to Y" entries.
        Schema::create('idea_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 8)->default('update'); // update | stage
            $table->text('body')->nullable();
            $table->string('from_stage', 16)->nullable();
            $table->string('to_stage', 16)->nullable();
            $table->timestamps();
            $table->index(['idea_id', 'created_at']);
        });

        // The "pending stuff": things still to do on a project.
        Schema::create('idea_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('due_date')->nullable();
            $table->boolean('done')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->index(['idea_id', 'done']);
        });

        // Supabase exposes tables through its public API; keep the new ones private (Laravel bypasses RLS).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table "idea_updates" enable row level security');
            DB::statement('alter table "idea_tasks" enable row level security');
            DB::statement('alter table "idea_members" enable row level security');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('idea_members');
        Schema::dropIfExists('idea_tasks');
        Schema::table('ideas', fn (Blueprint $t) => $t->dropColumn('note'));
        Schema::dropIfExists('idea_updates');
    }
};
