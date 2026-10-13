<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrm_staff', function (Blueprint $table) {
            $table->foreignId('birth_country_id')->nullable()->constrained('tb_country')->nullOnDelete();
            $table->foreignId('birth_province_id')->nullable()->constrained('tb_province')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hrm_staff', function (Blueprint $table) {
            $table->dropConstrainedForeignId('birth_province_id');
            $table->dropConstrainedForeignId('birth_country_id');
        });
    }
};
