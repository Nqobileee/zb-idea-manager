<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A group conversation has no user pair: every signed-in member takes part.
        Schema::table('conversations', function (Blueprint $table) {
            $table->boolean('is_group')->default(false)->index();
            $table->string('name')->nullable();
        });
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('user_a')->nullable()->change();
            $table->foreignId('user_b')->nullable()->change();
        });

        // How far each person has read a group. A group message has many readers, so messages.read_at cannot hold it.
        Schema::create('conversation_reads', function (Blueprint $table) {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_message_id')->default(0);
            $table->primary(['conversation_id', 'user_id']);
        });
        // Supabase exposes public tables through its REST API, so every new table gets RLS with no policies.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table "conversation_reads" enable row level security');
        }

        // Set when the message first reaches someone else's app. Shown to the sender as green ticks.
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable();
        });
        DB::table('messages')->whereNotNull('read_at')->update(['delivered_at' => DB::raw('read_at')]);

        DB::table('conversations')->insert(['is_group' => true, 'name' => 'All staff', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_reads');
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('delivered_at');
        });
        DB::table('conversations')->where('is_group', true)->delete();
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['is_group']);
        });
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['is_group', 'name']);
            $table->foreignId('user_a')->nullable(false)->change();
            $table->foreignId('user_b')->nullable(false)->change();
        });
    }
};
