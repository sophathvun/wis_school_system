<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_student', function (Blueprint $table) {
            $table->foreignId('nationality_country_id')->nullable()->after('gender')->constrained('tb_country')->nullOnDelete();
            $table->string('home_phone', 50)->nullable()->after('date_of_birth');
            $table->string('email', 150)->nullable()->after('home_phone');
            $table->foreignId('address_country_id')->nullable()->after('birth_village_id')->constrained('tb_country')->nullOnDelete();
            $table->foreignId('address_province_id')->nullable()->after('address_country_id')->constrained('tb_province')->nullOnDelete();
            $table->foreignId('address_district_id')->nullable()->after('address_province_id')->constrained('tb_district')->nullOnDelete();
            $table->foreignId('address_commune_id')->nullable()->after('address_district_id')->constrained('tb_commune')->nullOnDelete();
            $table->foreignId('address_village_id')->nullable()->after('address_commune_id')->constrained('tb_village')->nullOnDelete();
            $table->string('address_house_no_en', 100)->nullable();
            $table->string('address_house_no_kh', 100)->nullable();
            $table->string('address_street_en', 150)->nullable();
            $table->string('address_street_kh', 150)->nullable();
            $table->text('current_address_en')->nullable();
            $table->text('current_address_kh')->nullable();
            $table->string('previous_school', 200)->nullable();
            $table->text('experienced_english')->nullable();
            $table->text('test_result')->nullable();
            $table->string('tested_by', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tb_student', function (Blueprint $table) {
            foreach (['nationality_country_id', 'address_country_id', 'address_province_id', 'address_district_id', 'address_commune_id', 'address_village_id'] as $column) $table->dropForeign([$column]);
            $table->dropColumn(['nationality_country_id', 'home_phone', 'email', 'address_country_id', 'address_province_id', 'address_district_id', 'address_commune_id', 'address_village_id', 'address_house_no_en', 'address_house_no_kh', 'address_street_en', 'address_street_kh', 'current_address_en', 'current_address_kh', 'previous_school', 'experienced_english', 'test_result', 'tested_by']);
        });
    }
};
