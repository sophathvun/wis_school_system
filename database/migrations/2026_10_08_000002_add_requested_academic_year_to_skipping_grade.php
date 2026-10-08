<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_student_skipping_grade', function (Blueprint $table) {
            $table->foreignId('target_academic_year_id')->nullable()->constrained('tb_academic_year')->restrictOnDelete();
            $table->foreignId('target_enrollment_id')->nullable()->constrained('tb_student_enrollment')->restrictOnDelete();
        });
        DB::table('tb_student_skipping_grade')->update(['target_academic_year_id'=>DB::raw('academic_year_id')]);
        DB::table('tb_student_skipping_grade')->where('status','approved')->update(['target_enrollment_id'=>DB::raw('enrollment_id')]);
    }

    public function down(): void
    {
        Schema::table('tb_student_skipping_grade', function (Blueprint $table) {
            $table->dropConstrainedForeignId('target_academic_year_id');
            $table->dropConstrainedForeignId('target_enrollment_id');
        });
    }
};
