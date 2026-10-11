<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('access_permissions')->where('code','student-skipping-grade.print-reports')
            ->update(['name'=>'Print A4 Skipping Reports (All Statuses and Campuses)','updated_at'=>now()]);
    }

    public function down(): void
    {
        DB::table('access_permissions')->where('code','student-skipping-grade.print-reports')
            ->update(['name'=>'Print A4 Approved Skipping Reports (All Campuses)','updated_at'=>now()]);
    }
};
