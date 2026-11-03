<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('schedule_periods',function(Blueprint $table){
            $table->json('applicable_groups')->nullable();
            $table->softDeletes();
        });
        foreach(DB::table('schedule_periods')->get() as $period){
            $start=substr($period->starts_at,0,5);$end=substr($period->ends_at,0,5);
            if($start==='07:00'&&$end==='07:50'){
                $used=DB::table('teaching_schedules')->where('period_id',$period->id)->exists()
                    ||DB::table('attendance_daily_marks')->whereNotNull('absent_periods')->get(['absent_periods'])->contains(fn($mark)=>collect(json_decode($mark->absent_periods,true)?:[])->contains(fn($saved)=>(int)($saved['id']??0)===(int)$period->id));
                if($used)DB::table('schedule_periods')->where('id',$period->id)->update(['active'=>false,'deleted_at'=>now(),'applicable_groups'=>'[]','updated_at'=>now()]);
                else DB::table('schedule_periods')->where('id',$period->id)->delete();
                continue;
            }
            $groups=[];
            if($period->session==='morning'&&$start>='08:00'&&$end<='11:50')$groups=['M','III'];
            if($period->session==='afternoon'&&$start>='13:00'&&$end<='16:50')$groups=$end<='15:50'?['A','III']:['A'];
            DB::table('schedule_periods')->where('id',$period->id)->update(['applicable_groups'=>json_encode($groups),'updated_at'=>now()]);
        }
        if(!DB::table('schedule_periods')->where('starts_at','16:00')->where('ends_at','16:50')->whereNull('deleted_at')->exists()){
            DB::table('schedule_periods')->insert(['name'=>'Afternoon Period 4','session'=>'afternoon','starts_at'=>'16:00','ends_at'=>'16:50','display_order'=>9,'active'=>true,'version'=>1,'applicable_groups'=>json_encode(['A']),'created_at'=>now(),'updated_at'=>now()]);
        }
    }

    public function down(): void
    {
        Schema::table('schedule_periods',fn(Blueprint $table)=>$table->dropColumn(['applicable_groups','deleted_at']));
    }
};
