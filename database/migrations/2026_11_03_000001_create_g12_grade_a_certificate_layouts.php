<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_g12_grade_a_layout', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year',9);
            $table->unsignedBigInteger('campus_id');
            $table->json('layout');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();
            $table->unique(['academic_year','campus_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('tb_g12_grade_a_layout'); }
};
