<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('access_permissions')->updateOrInsert(['code'=>'western-scholarship.answer-marked-view'],[
            'module'=>'western-scholarship','action'=>'answer-marked-view','name'=>'View marked student answer sheets, correct answers and scores (Central only)',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }
    public function down(): void
    {
        $ids=DB::table('access_permissions')->where('code','western-scholarship.answer-marked-view')->pluck('id');
        foreach(['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table)DB::table($table)->whereIn('permission_id',$ids)->delete();
        DB::table('access_permissions')->whereIn('id',$ids)->delete();
    }
};
