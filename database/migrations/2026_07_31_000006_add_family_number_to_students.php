<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_student', function (Blueprint $table) {
            $table->string('family_number', 30)->nullable()->after('student_no');
        });
    }

    public function down(): void
    {
        Schema::table('tb_student', function (Blueprint $table) {
            $table->dropColumn('family_number');
        });
    }
};