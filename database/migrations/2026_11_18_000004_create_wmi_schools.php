<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wmi_schools',function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_kh');
            $table->string('name_key',64)->unique();
            $table->string('school_type',10);
            $table->foreignId('province_id')->constrained('tb_province')->restrictOnDelete();
            $table->text('remark')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('wmi_schools'); }
};
