<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendance_calendars',fn(Blueprint $table)=>$table->json('holiday_descriptions')->nullable());
    }
    public function down(): void
    {
        Schema::table('attendance_calendars',fn(Blueprint $table)=>$table->dropColumn('holiday_descriptions'));
    }
};
