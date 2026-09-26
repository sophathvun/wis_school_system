<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_student_enrollment', function (Blueprint $table) {
            if (!Schema::hasColumn('tb_student_enrollment', 'id_book_list_no')) {
                $table->string('id_book_list_no', 5)->nullable()->after('student_type');
                $table->index(['academic_year_id', 'campus_id', 'id_book_list_no'], 'student_enrollment_id_book_list_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tb_student_enrollment', function (Blueprint $table) {
            if (Schema::hasColumn('tb_student_enrollment', 'id_book_list_no')) {
                $table->dropIndex('student_enrollment_id_book_list_index');
                $table->dropColumn('id_book_list_no');
            }
        });
    }
};