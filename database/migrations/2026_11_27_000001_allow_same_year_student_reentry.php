<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table='tb_student_enrollment';
        $unique='tb_student_enrollment_student_id_academic_year_id_unique';
        $lookup='student_enrollment_student_year_lookup';
        // Keep the student foreign key indexed before removing the unique index in MySQL.
        if (!Schema::hasIndex($table,$lookup)) Schema::table($table,fn(Blueprint $t)=>$t->index(['student_id','academic_year_id'],$lookup));
        if (Schema::hasIndex($table,$unique)) Schema::table($table,fn(Blueprint $t)=>$t->dropUnique($unique));
    }

    public function down(): void
    {
        // Preserve valid withdrawal/re-entry pairs; restoring the unique rule would reject them.
    }
};
