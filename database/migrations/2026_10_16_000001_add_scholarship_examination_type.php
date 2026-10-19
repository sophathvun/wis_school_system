<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_scholarship_exam',fn(Blueprint $table)=>$table->string('examination_type',120)->default('WIS Scholarship Exams'));
    }
    public function down(): void
    {
        Schema::table('tb_scholarship_exam',fn(Blueprint $table)=>$table->dropColumn('examination_type'));
    }
};
