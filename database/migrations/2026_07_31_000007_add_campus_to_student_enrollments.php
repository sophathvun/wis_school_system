<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_student_enrollment', function (Blueprint $table) {
            $table->foreignId('campus_id')->nullable()->after('student_id')->constrained('tb_school_info')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tb_student_enrollment', function (Blueprint $table) {
            $table->dropForeign(['campus_id']);
            $table->dropColumn('campus_id');
        });
    }
};
