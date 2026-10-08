<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Accounts used to be created with a made-up role, department and bio. Clear those so people fill in their own. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereIn('title', ['Business Analyst', 'Executive, Digital Strategy', 'Executive', 'Employee'])->update(['title' => null]);
        DB::table('users')->where('dept', 'Digital Banking')->update(['dept' => null]);
        DB::table('users')->where('bio', 'I like ideas that save a customer a trip to the branch.')->update(['bio' => null]);
    }

    public function down(): void
    {
        // the cleared text was placeholder content, nothing to restore
    }
};
