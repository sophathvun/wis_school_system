<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_province', fn(Blueprint $table) => $table->string('province_code', 10)->nullable()->unique());
        Schema::table('tb_district', fn(Blueprint $table) => $table->string('district_code', 10)->nullable()->unique());
        Schema::table('tb_commune', fn(Blueprint $table) => $table->string('commune_code', 10)->nullable()->unique());
        Schema::table('tb_village', fn(Blueprint $table) => $table->string('village_code', 10)->nullable()->unique());
    }

    public function down(): void
    {
        Schema::table('tb_village', fn(Blueprint $table) => $table->dropUnique(['village_code']));
        Schema::table('tb_commune', fn(Blueprint $table) => $table->dropUnique(['commune_code']));
        Schema::table('tb_district', fn(Blueprint $table) => $table->dropUnique(['district_code']));
        Schema::table('tb_province', fn(Blueprint $table) => $table->dropUnique(['province_code']));
        Schema::table('tb_village', fn(Blueprint $table) => $table->dropColumn('village_code'));
        Schema::table('tb_commune', fn(Blueprint $table) => $table->dropColumn('commune_code'));
        Schema::table('tb_district', fn(Blueprint $table) => $table->dropColumn('district_code'));
        Schema::table('tb_province', fn(Blueprint $table) => $table->dropColumn('province_code'));
    }
};
