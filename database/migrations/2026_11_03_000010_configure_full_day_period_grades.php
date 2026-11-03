<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('schedule_periods',fn(Blueprint $table)=>$table->json('full_day_grades')->nullable());
        foreach(DB::table('schedule_periods')->get() as $period){
            $grades=$period->session==='morning'&&substr($period->ends_at,0,5)>'10:50'
                ?array_map('strval',range(4,12)):array_merge(['N','K1','K2','K3'],array_map('strval',range(1,12)));
            DB::table('schedule_periods')->where('id',$period->id)->update(['full_day_grades'=>json_encode($grades)]);
        }
    }
    public function down(): void
    {
        Schema::table('schedule_periods',fn(Blueprint $table)=>$table->dropColumn('full_day_grades'));
    }
};
