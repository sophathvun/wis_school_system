<?php

use App\Support\G9NationalExamPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_g9_eoy_campus_setting',function(Blueprint $table){
            $table->id();$table->unsignedBigInteger('academic_year_id');$table->unsignedBigInteger('campus_id');
            $table->string('compiler_name')->nullable();$table->string('director_name')->nullable();
            $table->unsignedInteger('version')->default(1);$table->unsignedBigInteger('updated_by')->nullable();$table->timestamps();
            $table->unique(['academic_year_id','campus_id']);
        });
        G9NationalExamPermissions::register();
        foreach(['export'=>['eoy-print','eoy-pdf','eoy-report-cover','eoy-report-card'],'settings-manage'=>['eoy-settings-manage']] as $source=>$actions){
            $sourceId=DB::table('access_permissions')->where('code','g9-national-exams.'.$source)->value('id');
            foreach($actions as $action){
                $target=DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id');
                foreach(['access_role_permissions'=>'role_id','access_department_permissions'=>'department_id','access_user_permission_overrides'=>'user_id'] as $table=>$owner){
                    $owners=DB::table($table)->where('permission_id',$sourceId)->when($owner==='user_id',fn($q)=>$q->where('allowed',true))->pluck($owner);
                    foreach($owners as $id)if(!DB::table($table)->where($owner,$id)->where('permission_id',$target)->exists())
                        DB::table($table)->insert([$owner=>$id,'permission_id'=>$target,'created_at'=>now(),'updated_at'=>now()]+($owner==='user_id'?['allowed'=>true]:[]));
                }
            }
        }
    }
    public function down(): void {} // Retain saved names and permission assignments.
};
