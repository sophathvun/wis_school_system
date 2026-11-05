<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach(['eoy'=>'View G9 Assign EOY Average','eoy-assign'=>'Assign G9 semester scores and EOY total'] as $action=>$name)
            DB::table('access_permissions')->updateOrInsert(['code'=>'g9-national-exams.'.$action],['module'=>'g9-national-exams','action'=>$action,'name'=>$name,'created_at'=>now(),'updated_at'=>now()]);
    }
    public function down(): void {}
};
