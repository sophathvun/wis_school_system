<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_scholarship_result_print_setting',function(Blueprint $table){
            $table->id();
            $table->foreignId('exam_id')->unique()->constrained('tb_scholarship_exam')->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('tb_school_info')->restrictOnDelete();
            $table->string('signing_location',150)->default('Phnom Penh');
            foreach(['prepared','checked','approved'] as $role){
                $table->foreignId($role.'_staff_id')->nullable()->constrained('hrm_staff')->nullOnDelete();
                $table->string($role.'_by_name')->nullable();$table->string($role.'_position')->nullable();$table->date($role.'_date')->nullable();
            }
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();$table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('tb_scholarship_result_print_setting'); }
};
