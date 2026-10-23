<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function(){
            $ids=DB::table('access_permissions')->where('code','western-scholarship.add-late-candidates')->pluck('id');
            foreach(['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table){
                DB::table($table)->whereIn('permission_id',$ids)->delete();
            }
            DB::table('access_permissions')->whereIn('id',$ids)->delete();
        });
    }

    public function down(): void
    {
        // The retired capability and its removed grants must not be restored.
    }
};
