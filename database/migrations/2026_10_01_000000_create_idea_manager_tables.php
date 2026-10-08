<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('brief');
            $table->json('keywords');
            $table->date('deadline')->nullable();
            $table->timestamps();
        });

        Schema::create('ideas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('num')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('challenge_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('summary');
            $table->longText('body');
            $table->string('status', 16)->default('Idea')->index();
            $table->boolean('approved')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_note')->nullable();
            $table->unsignedInteger('shares')->default(0);
            $table->string('source', 12)->default('web');
            $table->timestamps();
        });

        Schema::create('idea_likes', function (Blueprint $table) {
            $table->foreignId('idea_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['idea_id', 'user_id']);
        });

        Schema::create('idea_saves', function (Blueprint $table) {
            $table->foreignId('idea_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['idea_id', 'user_id']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('idea_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 8)->default('doc'); // doc | image
            $table->string('name');
            $table->string('path')->nullable();
            $table->string('size', 16)->nullable();
            $table->timestamps();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_a')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_b')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->unique(['user_a', 'user_b']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16); // approval, comment, challenge, stage
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('idea_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('challenge_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sent_emails', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16);
            $table->string('to');
            $table->string('from');
            $table->string('subject');
            $table->text('body');
            $table->timestamps();
        });

        // WhatsApp bot: one row per phone number holds the conversation state.
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('state', 24)->default('idle');
            $table->json('draft')->nullable();
            $table->string('pending_email')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('wa_id')->nullable()->unique();
            $table->string('phone', 20)->index();
            $table->string('direction', 3); // in | out
            $table->string('intent', 24)->nullable();
            $table->string('status', 12)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['whatsapp_messages', 'whatsapp_sessions', 'sent_emails', 'activities', 'messages', 'conversations', 'idea_files', 'comments', 'idea_saves', 'idea_likes', 'ideas', 'challenges', 'login_codes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
