<?php

use App\Support\G9NationalExamPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        G9NationalExamPermissions::register();
        $source=DB::table('access_permissions')->where('code','g9-national-exams.results')->value('id');
        foreach(['results-graph','results-statistics'] as $action){
            $target=DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id');
            foreach(['access_role_permissions'=>'role_id','access_department_permissions'=>'department_id','access_user_permission_overrides'=>'user_id'] as $table=>$owner){
                $owners=DB::table($table)->where('permission_id',$source)->when($owner==='user_id',fn($q)=>$q->where('allowed',true))->pluck($owner);
                foreach($owners as $id)if(!DB::table($table)->where($owner,$id)->where('permission_id',$target)->exists())
                    DB::table($table)->insert([$owner=>$id,'permission_id'=>$target,'created_at'=>now(),'updated_at'=>now()]+($owner==='user_id'?['allowed'=>true]:[]));
            }
        }
    }
    public function down(): void {} // Preserve permission assignments.
};
