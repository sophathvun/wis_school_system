<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up():void
    {
        Schema::create('tb_gep_class_type',function(Blueprint $t){
            $t->id();$t->string('type_name',100)->unique();$t->unsignedInteger('sort_order')->default(0);
            $t->boolean('status')->default(true);$t->timestamps();
        });
        foreach(['Full-Time','Part-Time'] as $order=>$name)DB::table('tb_gep_class_type')->insert([
            'type_name'=>$name,'sort_order'=>$order+1,'status'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        \App\Support\GepSettingsPermissions::registerMissing();
        $ids=DB::table('access_permissions')->whereIn('code',array_map(fn($a)=>'gep-settings.class-types.'.$a,['view','create','edit','delete','status']))->pluck('id');
        foreach(DB::table('access_roles')->whereIn('code',['super-admin','central-office-admin'])->pluck('id') as $role)foreach($ids as $permission)DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>$role,'permission_id'=>$permission,'created_at'=>now(),'updated_at'=>now()]);
    }
    public function down():void
    {
        Schema::dropIfExists('tb_gep_class_type');
        DB::table('access_permissions')->where('module','gep-settings.class-types')->delete();
    }
};
