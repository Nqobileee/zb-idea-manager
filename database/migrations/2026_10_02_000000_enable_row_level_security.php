<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Supabase exposes every table in the public schema through its REST API.
 * Laravel talks to Postgres directly (as a role that bypasses row level security),
 * so we switch RLS on for every table and add NO policies. That keeps the data
 * unreadable through Supabase's public API keys while the app works as normal.
 *
 * Any table you add later needs the same line:  ALTER TABLE name ENABLE ROW LEVEL SECURITY;
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        foreach (DB::select("select tablename from pg_tables where schemaname = 'public'") as $row) {
            DB::statement('alter table "'.$row->tablename.'" enable row level security');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        foreach (DB::select("select tablename from pg_tables where schemaname = 'public'") as $row) {
            DB::statement('alter table "'.$row->tablename.'" disable row level security');
        }
    }
};
