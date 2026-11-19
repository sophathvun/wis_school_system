<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up():void
    {
        Schema::create('tb_gep_group',function(Blueprint $t){
            $t->id();$t->string('session_name',30)->unique();$t->string('group_code',1)->unique();
            $t->unsignedInteger('sort_order')->default(0);$t->boolean('status')->default(true);$t->timestamps();
        });
        foreach(['Morning'=>'M','Afternoon'=>'A','Evening'=>'E'] as $session=>$code)DB::table('tb_gep_group')->insert([
            'session_name'=>$session,'group_code'=>$code,'sort_order'=>array_search($session,['Morning','Afternoon','Evening'])+1,
            'status'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        \App\Support\GepSettingsPermissions::registerMissing();
        $ids=DB::table('access_permissions')->whereIn('code',array_map(fn($a)=>'gep-settings.groups.'.$a,['create','edit','delete','status']))->pluck('id');
        foreach(DB::table('access_roles')->whereIn('code',['super-admin','central-office-admin'])->pluck('id') as $role)foreach($ids as $permission)DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>$role,'permission_id'=>$permission,'created_at'=>now(),'updated_at'=>now()]);
    }
    public function down():void
    {
        Schema::dropIfExists('tb_gep_group');
        DB::table('access_permissions')->whereIn('code',array_map(fn($a)=>'gep-settings.groups.'.$a,['create','edit','delete','status']))->delete();
    }
};
