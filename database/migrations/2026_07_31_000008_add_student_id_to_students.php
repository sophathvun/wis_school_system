<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_student', function (Blueprint $table) {
            $table->string('student_id', 30)->nullable()->after('student_no')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('tb_student', function (Blueprint $table) {
            $table->dropUnique(['student_id']);
            $table->dropColumn('student_id');
        });
    }
};
