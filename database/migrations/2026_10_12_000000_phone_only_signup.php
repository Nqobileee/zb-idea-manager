<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->timestamp('wa_welcomed_at')->nullable();
            $table->string('source', 12)->nullable();
        });
        // everyone who exists today has already been welcomed: only new members see the welcome and temporary password
        DB::table('users')->update(['wa_welcomed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['wa_welcomed_at', 'source']);
        });
    }
};
