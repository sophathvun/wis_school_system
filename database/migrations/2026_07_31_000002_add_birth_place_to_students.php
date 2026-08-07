<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_student', function (Blueprint $table) {
            $table->foreignId('birth_country_id')->nullable()->constrained('tb_country')->nullOnDelete();
            $table->foreignId('birth_province_id')->nullable()->constrained('tb_province')->nullOnDelete();
            $table->foreignId('birth_district_id')->nullable()->constrained('tb_district')->nullOnDelete();
            $table->foreignId('birth_commune_id')->nullable()->constrained('tb_commune')->nullOnDelete();
            $table->foreignId('birth_village_id')->nullable()->constrained('tb_village')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tb_student', function (Blueprint $table) {
            $table->dropForeign(['birth_country_id']); $table->dropForeign(['birth_province_id']);
            $table->dropForeign(['birth_district_id']); $table->dropForeign(['birth_commune_id']);
            $table->dropForeign(['birth_village_id']);
            $table->dropColumn(['birth_country_id', 'birth_province_id', 'birth_district_id', 'birth_commune_id', 'birth_village_id']);
        });
    }
};
