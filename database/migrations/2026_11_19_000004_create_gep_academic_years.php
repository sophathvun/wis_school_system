<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_gep_academic_year',function(Blueprint $t){
            $t->id();$t->string('academic_year',9)->unique();
            $t->enum('lifecycle_status',['pending','started','finished'])->default('pending');
            $t->unsignedTinyInteger('started_slot')->nullable()->unique();$t->timestamps();
        });
        \App\Support\GepSettingsPermissions::registerMissing();
        $permissions=DB::table('access_permissions')->whereIn('code',array_map(fn($action)=>'gep-settings.academic-years.'.$action,['create','edit','delete','start']))->pluck('id');
        foreach(DB::table('access_roles')->whereIn('code',['super-admin','central-office-admin'])->pluck('id') as $role)foreach($permissions as $permission)
            DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>$role,'permission_id'=>$permission,'created_at'=>now(),'updated_at'=>now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_gep_academic_year');
        DB::table('access_permissions')->whereIn('code',array_map(fn($action)=>'gep-settings.academic-years.'.$action,['create','edit','delete','start']))->delete();
    }
};
