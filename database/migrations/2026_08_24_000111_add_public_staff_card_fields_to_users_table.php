<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'public_card_token')) {
                $table->string('public_card_token', 80)->nullable()->unique()->after('photo_path');
            }

            if (!Schema::hasColumn('users', 'public_card_enabled')) {
                $table->boolean('public_card_enabled')->default(true)->after('public_card_token');
            }

            if (!Schema::hasColumn('users', 'public_card_scan_count')) {
                $table->unsignedInteger('public_card_scan_count')->default(0)->after('public_card_enabled');
            }

            if (!Schema::hasColumn('users', 'public_card_last_viewed_at')) {
                $table->timestamp('public_card_last_viewed_at')->nullable()->after('public_card_scan_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'public_card_last_viewed_at')) {
                $table->dropColumn('public_card_last_viewed_at');
            }

            if (Schema::hasColumn('users', 'public_card_scan_count')) {
                $table->dropColumn('public_card_scan_count');
            }

            if (Schema::hasColumn('users', 'public_card_enabled')) {
                $table->dropColumn('public_card_enabled');
            }

            if (Schema::hasColumn('users', 'public_card_token')) {
                $table->dropUnique('users_public_card_token_unique');
                $table->dropColumn('public_card_token');
            }
        });
    }
};
