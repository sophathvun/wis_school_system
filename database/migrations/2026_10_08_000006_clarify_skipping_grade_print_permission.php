<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('access_permissions')->where('code','student-skipping-grade.print')->update([
            'name'=>'Print Grade Skipping Request and Approval Forms (All Campuses)','updated_at'=>now(),
        ]);
    }

    public function down(): void
    {
        DB::table('access_permissions')->where('code','student-skipping-grade.print')->update([
            'name'=>'Print Grade Skipping Request and Approval Forms','updated_at'=>now(),
        ]);
    }
};
