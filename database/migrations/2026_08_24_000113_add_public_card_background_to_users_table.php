<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'public_card_background')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('public_card_background', 7)
                    ->default('#206bc4')
                    ->after('public_card_orientation');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'public_card_background')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('public_card_background');
            });
        }
    }
};
