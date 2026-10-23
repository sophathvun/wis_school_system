<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('access_permissions')->updateOrInsert(['code'=>'western-scholarship.answer-clear-scans'],[
            'module'=>'western-scholarship','action'=>'answer-clear-scans','name'=>'Clear Test Scans and scan-created scores (Central only)',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }
    public function down(): void
    {
        $ids=DB::table('access_permissions')->where('code','western-scholarship.answer-clear-scans')->pluck('id');
        foreach(['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table)DB::table($table)->whereIn('permission_id',$ids)->delete();
        DB::table('access_permissions')->whereIn('id',$ids)->delete();
    }
};
