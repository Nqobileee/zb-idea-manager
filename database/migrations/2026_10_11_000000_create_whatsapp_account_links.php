<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_account_links', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_used_at')->useCurrent();
            $table->unique(['phone', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_account_links');
    }
};
