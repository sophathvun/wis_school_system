<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_school_info', function (Blueprint $table) {
            $table->string('address_house_no_en', 100)->nullable()->after('address_kh');
            $table->string('address_house_no_kh', 100)->nullable()->after('address_house_no_en');
            $table->string('address_street_en', 100)->nullable()->after('address_house_no_kh');
            $table->string('address_street_kh', 100)->nullable()->after('address_street_en');
        });
    }

    public function down(): void
    {
        Schema::table('tb_school_info', function (Blueprint $table) {
            $table->dropColumn([
                'address_house_no_en',
                'address_house_no_kh',
                'address_street_en',
                'address_street_kh',
            ]);
        });
    }
};
