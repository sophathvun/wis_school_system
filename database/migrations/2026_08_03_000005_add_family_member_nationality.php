<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_family_member', function (Blueprint $table) {
            $table->string('nationality_en', 80)->nullable()->after('workplace');
            $table->string('nationality_kh', 80)->nullable()->after('nationality_en');
        });
    }

    public function down(): void
    {
        Schema::table('tb_family_member', function (Blueprint $table) {
            $table->dropColumn(['nationality_en', 'nationality_kh']);
        });
    }
};
