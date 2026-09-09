<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_school_info', function (Blueprint $table) {
            $table->foreignId('address_country_id')->nullable()->after('phone')->constrained('tb_country')->nullOnDelete();
            $table->foreignId('address_province_id')->nullable()->after('address_country_id')->constrained('tb_province')->nullOnDelete();
            $table->foreignId('address_district_id')->nullable()->after('address_province_id')->constrained('tb_district')->nullOnDelete();
            $table->foreignId('address_commune_id')->nullable()->after('address_district_id')->constrained('tb_commune')->nullOnDelete();
            $table->foreignId('address_village_id')->nullable()->after('address_commune_id')->constrained('tb_village')->nullOnDelete();
            $table->text('address_en')->nullable()->after('address_village_id');
            $table->text('address_kh')->nullable()->after('address_en');
        });
    }

    public function down(): void
    {
        Schema::table('tb_school_info', function (Blueprint $table) {
            foreach (['address_country_id', 'address_province_id', 'address_district_id', 'address_commune_id', 'address_village_id'] as $column) {
                $table->dropForeign([$column]);
            }

            $table->dropColumn([
                'address_country_id',
                'address_province_id',
                'address_district_id',
                'address_commune_id',
                'address_village_id',
                'address_en',
                'address_kh',
            ]);
        });
    }
};
