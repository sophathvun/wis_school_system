<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_scholarship_exam',function(Blueprint $table){
            $table->timestamp('historical_imported_at')->nullable();
            $table->foreignId('historical_imported_by')->nullable()->constrained('users')->nullOnDelete();
        });
        foreach(['tab-data-transfer'=>'Tab: Import / Export','import-history'=>'Import historical scholarship records'] as $action=>$name){
            DB::table('access_permissions')->updateOrInsert(['code'=>'western-scholarship.'.$action],['module'=>'western-scholarship','action'=>$action,'name'=>$name,'created_at'=>now(),'updated_at'=>now()]);
            $role=DB::table('access_roles')->where('code','super-admin')->value('id');
            $permission=DB::table('access_permissions')->where('code','western-scholarship.'.$action)->value('id');
            if($role)DB::table('access_role_permissions')->updateOrInsert(['role_id'=>$role,'permission_id'=>$permission],['created_at'=>now(),'updated_at'=>now()]);
        }
    }

    public function down(): void
    {
        Schema::table('tb_scholarship_exam',function(Blueprint $table){$table->dropConstrainedForeignId('historical_imported_by');$table->dropColumn('historical_imported_at');});
        $ids=DB::table('access_permissions')->whereIn('code',['western-scholarship.tab-data-transfer','western-scholarship.import-history'])->pluck('id');
        foreach(['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table)DB::table($table)->whereIn('permission_id',$ids)->delete();
        DB::table('access_permissions')->whereIn('id',$ids)->delete();
    }
};
