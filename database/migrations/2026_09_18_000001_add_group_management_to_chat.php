<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('title');
        });

        Schema::table('chat_conversation_users', function (Blueprint $table) {
            $table->string('role', 20)->default('member')->after('user_id');
            $table->index(['conversation_id', 'role']);
        });

        DB::table('chat_conversation_users')
            ->join('chat_conversations', 'chat_conversations.id', '=', 'chat_conversation_users.conversation_id')
            ->where('chat_conversations.type', 'group')
            ->whereColumn('chat_conversation_users.user_id', 'chat_conversations.created_by')
            ->update(['chat_conversation_users.role' => 'owner']);
    }

    public function down(): void
    {
        Schema::table('chat_conversation_users', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'role']);
            $table->dropColumn('role');
        });

        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
