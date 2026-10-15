<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_scholarship_print_setting',function(Blueprint $table){
            $table->id();
            $table->foreignId('campus_id')->constrained('tb_school_info')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('tb_academic_year')->cascadeOnDelete();
            $table->string('prepared_by_name',150);
            $table->date('prepared_date');
            $table->string('approved_by_name',150);
            $table->date('approved_date');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['campus_id','academic_year_id'],'scholarship_print_campus_year_unique');
        });
    }

    public function down(): void { Schema::dropIfExists('tb_scholarship_print_setting'); }
};
