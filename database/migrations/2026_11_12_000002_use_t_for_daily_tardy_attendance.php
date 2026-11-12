<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        DB::table('attendance_daily_marks')->where('status','L')->update(['status'=>'T']);
    }
    public function down(): void {
        DB::table('attendance_daily_marks')->where('status','T')->update(['status'=>'L']);
    }
};
