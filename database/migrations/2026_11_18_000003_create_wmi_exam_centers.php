<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wmi_exam_centers',function (Blueprint $table) {
            $table->id();
            $table->foreignId('wmi_year_id')->constrained('wmi_years')->restrictOnDelete();
            $table->string('name'); $table->string('name_key',64); $table->string('location');
            $table->timestamps(); $table->unique(['wmi_year_id','name_key']);
        });
    }
    public function down(): void { Schema::dropIfExists('wmi_exam_centers'); }
};
