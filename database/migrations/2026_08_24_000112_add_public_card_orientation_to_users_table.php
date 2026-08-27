<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'public_card_orientation')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('public_card_orientation', 12)
                    ->default('landscape')
                    ->after('public_card_enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'public_card_orientation')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('public_card_orientation');
            });
        }
    }
};
