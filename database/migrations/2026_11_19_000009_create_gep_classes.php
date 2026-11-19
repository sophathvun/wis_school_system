<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up():void
    {
        Schema::create('tb_gep_class',function(Blueprint $t){$t->id();$t->string('class_name',100)->unique();$t->string('short_name',30)->unique();$t->unsignedInteger('sort_order')->default(0);$t->boolean('status')->default(true);$t->timestamps();});
        \App\Support\GepSettingsPermissions::registerMissing();
        $ids=DB::table('access_permissions')->whereIn('code',array_map(fn($a)=>'gep-settings.classes.'.$a,['create','edit','delete','status']))->pluck('id');
        foreach(DB::table('access_roles')->whereIn('code',['super-admin','central-office-admin'])->pluck('id') as $role)foreach($ids as $permission)DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>$role,'permission_id'=>$permission,'created_at'=>now(),'updated_at'=>now()]);
    }
    public function down():void
    {
        Schema::dropIfExists('tb_gep_class');
        DB::table('access_permissions')->whereIn('code',array_map(fn($a)=>'gep-settings.classes.'.$a,['create','edit','delete','status']))->delete();
    }
};
