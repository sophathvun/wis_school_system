<?php

use App\Support\ScholarshipPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach(ScholarshipPermissions::catalog() as $action=>$name)DB::table('access_permissions')->updateOrInsert(['code'=>'western-scholarship.'.$action],[
            'module'=>'western-scholarship','action'=>$action,'name'=>$name,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $inherit=[
            'tab-result'=>['tab-scholarship-result'],
            'export'=>['export-ranks','export-seats','export-scores','export-results','export-result-scores','result-print-settings','export-scholarship-result','export-committee','export-data'],
        ];
        // Preserve existing grants and explicit user denials. Central-only rules are enforced at runtime.
        foreach(['access_role_permissions'=>'role_id','access_department_permissions'=>'department_id','access_user_permission_overrides'=>'user_id'] as $table=>$owner){
            foreach($inherit as $old=>$newActions){
                $oldId=DB::table('access_permissions')->where('code','western-scholarship.'.$old)->value('id');
                if(!$oldId)continue;
                foreach(DB::table($table)->where('permission_id',$oldId)->get() as $grant)foreach($newActions as $action){
                    $newId=DB::table('access_permissions')->where('code','western-scholarship.'.$action)->value('id');
                    DB::table($table)->insertOrIgnore([$owner=>$grant->{$owner},'permission_id'=>$newId,'created_at'=>now(),'updated_at'=>now()]
                        +($owner==='user_id'?['allowed'=>(bool)$grant->allowed]:[]));
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('access_permissions')->where('module','western-scholarship')->whereIn('action',[
            'tab-scholarship-result','export-ranks','export-seats','export-scores','export-results','export-result-scores',
            'result-print-settings','export-scholarship-result','export-committee','export-data',
        ])->delete();
    }
};
