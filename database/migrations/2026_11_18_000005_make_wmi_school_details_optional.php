<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wmi_schools',function (Blueprint $table) {
            $table->string('name_kh')->nullable()->change();
            $table->unsignedBigInteger('province_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('wmi_schools',function (Blueprint $table) {
            $table->string('name_kh')->nullable(false)->change();
            $table->unsignedBigInteger('province_id')->nullable(false)->change();
        });
    }
};
