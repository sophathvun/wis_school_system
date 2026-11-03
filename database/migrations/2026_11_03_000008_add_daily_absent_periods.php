<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendance_daily_marks',fn(Blueprint $table)=>$table->json('absent_periods')->nullable());
    }

    public function down(): void
    {
        Schema::table('attendance_daily_marks',fn(Blueprint $table)=>$table->dropColumn('absent_periods'));
    }
};
