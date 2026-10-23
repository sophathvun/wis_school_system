<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_scholarship_teacher_key_sheet',function(Blueprint $t){
            $t->id();$t->foreignId('paper_id')->constrained('tb_scholarship_paper')->cascadeOnDelete();
            $t->string('token',24)->unique();$t->string('fingerprint',64);$t->json('snapshot');
            $t->foreignId('printed_by')->constrained('users')->restrictOnDelete();$t->timestamps();
            $t->unique(['paper_id','fingerprint'],'sch_teacher_key_configuration');
        });
        foreach(['answer-key-print'=>'Print blank teacher answer-key sheets',
            'answer-key-scan'=>'Scan/upload teacher answer keys (requires Edit shared draft answer keys)'] as $action=>$name)
            DB::table('access_permissions')->updateOrInsert(['code'=>'western-scholarship.'.$action],[
                'module'=>'western-scholarship','action'=>$action,'name'=>$name,'created_at'=>now(),'updated_at'=>now(),
            ]);
    }
    public function down(): void
    {
        Schema::dropIfExists('tb_scholarship_teacher_key_sheet');
        $ids=DB::table('access_permissions')->whereIn('code',['western-scholarship.answer-key-print','western-scholarship.answer-key-scan'])->pluck('id');
        foreach(['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table)DB::table($table)->whereIn('permission_id',$ids)->delete();
        DB::table('access_permissions')->whereIn('id',$ids)->delete();
    }
};
