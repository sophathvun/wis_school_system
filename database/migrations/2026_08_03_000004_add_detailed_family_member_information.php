<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_family_member', function (Blueprint $table) {
            $table->string('name_en', 160)->nullable()->after('last_name_en');
            $table->string('name_kh', 160)->nullable()->after('last_name_kh');
            $table->string('occupation_en', 120)->nullable()->after('occupation');
            $table->string('occupation_kh', 120)->nullable()->after('occupation_en');
            $table->string('workplace', 160)->nullable()->after('occupation_kh');
        });
    }

    public function down(): void
    {
        Schema::table('tb_family_member', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'name_kh', 'occupation_en', 'occupation_kh', 'workplace']);
        });
    }
};
