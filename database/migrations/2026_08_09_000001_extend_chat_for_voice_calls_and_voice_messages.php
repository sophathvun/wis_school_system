<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('message_type', 20)->default('text')->after('message');
            $table->string('media_path')->nullable()->after('message_type');
            $table->string('media_name')->nullable()->after('media_path');
            $table->string('media_mime', 120)->nullable()->after('media_name');
            $table->unsignedSmallInteger('media_duration_seconds')->nullable()->after('media_mime');
            $table->index(['conversation_id', 'message_type', 'created_at']);
        });

        Schema::create('chat_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('call_type', 20)->default('audio');
            $table->string('status', 20)->default('ringing');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['conversation_id', 'status', 'created_at']);
        });

        Schema::create('chat_call_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained('chat_calls')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20)->default('participant');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['call_id', 'user_id']);
        });

        Schema::create('chat_call_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained('chat_calls')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('signal_type', 30);
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['call_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_call_signals');
        Schema::dropIfExists('chat_call_participants');
        Schema::dropIfExists('chat_calls');

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'message_type', 'created_at']);
            $table->dropColumn(['message_type', 'media_path', 'media_name', 'media_mime', 'media_duration_seconds']);
        });
    }
};
