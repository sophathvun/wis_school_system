<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        // Keep the existing permission codes, role grants and user overrides.
        foreach(\App\Support\G12NationalExamPermissions::catalog() as $action=>$name){
            DB::table('access_permissions')->where('code','g12-national-exams.'.$action)->where(function($q){$q->where('name','like','Tab:%')->orWhere('name','G12 National Exams: View submenu');})->update(['name'=>$name,'updated_at'=>now()]);
        }
    }
    public function down(): void {
        foreach(\App\Support\G12NationalExamPermissions::submenus() as $key=>$group)DB::table('access_permissions')->where('code','g12-national-exams.'.$key)->update(['name'=>'Tab: '.$group[0],'updated_at'=>now()]);
        DB::table('access_permissions')->where('code','g12-national-exams.view')->update(['name'=>'G12 National Exams: View submenu','updated_at'=>now()]);
    }
};
