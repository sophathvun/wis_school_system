<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tb_academic_year')->where('lifecycle_status', 'planning')->update(['lifecycle_status' => 'pending']);
        DB::table('tb_academic_year')->where('lifecycle_status', 'current')->update(['lifecycle_status' => 'started']);
        DB::table('tb_academic_year')->where('lifecycle_status', 'closed')->update(['lifecycle_status' => 'finished']);
    }

    public function down(): void
    {
        DB::table('tb_academic_year')->where('lifecycle_status', 'pending')->update(['lifecycle_status' => 'planning']);
        DB::table('tb_academic_year')->where('lifecycle_status', 'started')->update(['lifecycle_status' => 'current']);
        DB::table('tb_academic_year')->where('lifecycle_status', 'finished')->update(['lifecycle_status' => 'closed']);
    }
};
