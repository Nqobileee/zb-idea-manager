<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The avatar colour now follows the real ZB logo green (#049016). */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('color', '#0d4a36')->update(['color' => '#049016']);
        Schema::table('users', function (Blueprint $table) {
            $table->string('color', 9)->default('#049016')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('color', 9)->default('#0d4a36')->change();
        });
    }
};
