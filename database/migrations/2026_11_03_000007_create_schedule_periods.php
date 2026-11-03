<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('schedule_periods',function(Blueprint $table){
            $table->id();
            $table->string('name',80);
            $table->string('session',15);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
        foreach([7,8,9,10,11,13,14,15] as $index=>$hour){
            DB::table('schedule_periods')->insert(['name'=>($hour<12?'Morning':'Afternoon').' Period '.($hour<12?$index+1:$index-4),
                'session'=>$hour<12?'morning':'afternoon','starts_at'=>sprintf('%02d:00',$hour),'ends_at'=>sprintf('%02d:50',$hour),
                'display_order'=>$index+1,'active'=>true,'version'=>1,'created_at'=>now(),'updated_at'=>now()]);
        }
        Schema::table('teaching_schedules',fn(Blueprint $table)=>$table->unsignedBigInteger('period_id')->nullable());
        DB::table('access_permissions')->updateOrInsert(['code'=>'schedules.setup'],['module'=>'schedules','action'=>'setup','name'=>'Manage shared session periods for all campuses','created_at'=>now(),'updated_at'=>now()]);
    }

    public function down(): void
    {
        Schema::table('teaching_schedules',fn(Blueprint $table)=>$table->dropColumn('period_id'));
        Schema::dropIfExists('schedule_periods');
        DB::table('access_permissions')->where('code','schedules.setup')->delete();
    }
};
