<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('attendance_daily_registers',function(Blueprint $t){
            $t->unsignedBigInteger('grade_id')->default(0);
            $t->dropUnique('daily_class_register_unique');
            $t->unique(['academic_year_id','campus_id','grade_id','class_id','attendance_date'],'daily_grade_class_register_unique');
        });
    }
    public function down(): void {
        Schema::table('attendance_daily_registers',function(Blueprint $t){$t->dropUnique('daily_grade_class_register_unique');$t->dropColumn('grade_id');$t->unique(['academic_year_id','campus_id','class_id','attendance_date'],'daily_class_register_unique');});
    }
};
