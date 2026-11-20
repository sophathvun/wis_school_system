<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up():void
    {
        Schema::create('tb_gep_term_assignment',function(Blueprint $t){
            $t->id();$t->foreignId('academic_year_id')->constrained('tb_gep_academic_year')->restrictOnDelete();
            $t->foreignId('term_id')->constrained('tb_gep_term')->restrictOnDelete();
            $t->unsignedInteger('sort_order');$t->date('start_date');$t->date('end_date');
            $t->string('lifecycle_status',10)->default('pending');$t->unsignedTinyInteger('started_slot')->nullable();
            $t->timestamps();
            $t->unique(['academic_year_id','term_id'],'gep_assignment_year_term_unique');
            $t->unique(['academic_year_id','sort_order'],'gep_assignment_year_order_unique');
            $t->unique(['academic_year_id','started_slot'],'gep_assignment_year_started_unique');
        });
        \App\Support\GepSettingsPermissions::registerMissing();
        $ids=DB::table('access_permissions')->where('module','gep-settings.assign-terms')->pluck('id');
        foreach(DB::table('access_roles')->whereIn('code',['super-admin','central-office-admin'])->pluck('id') as $role)foreach($ids as $permission)DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>$role,'permission_id'=>$permission,'created_at'=>now(),'updated_at'=>now()]);
    }
    public function down():void
    {
        Schema::dropIfExists('tb_gep_term_assignment');
        DB::table('access_permissions')->where('module','gep-settings.assign-terms')->delete();
    }
};
