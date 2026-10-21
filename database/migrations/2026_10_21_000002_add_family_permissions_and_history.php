<?php

use App\Support\FamilyPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_family_history', function (Blueprint $t) {
            $t->id(); $t->foreignId('family_id')->constrained('tb_family');
            $t->string('action', 60); $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('changed_by_name', 160); $t->json('before_values')->nullable(); $t->json('after_values')->nullable();
            $t->timestamps(); $t->index(['family_id','id']);
        });
        foreach (FamilyPermissions::catalog() as $action => $name) DB::table('access_permissions')->updateOrInsert(['code' => 'families.'.$action], [
            'module' => 'families', 'action' => $action, 'name' => $name, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['access_role_permissions' => 'role_id','access_department_permissions' => 'department_id','access_user_permission_overrides' => 'user_id'] as $table => $owner) {
            foreach (['view' => ['search','history'], 'manage' => ['create','update','members-create','members-update','members-delete','link','unlink']] as $old => $actions) {
                $oldId = DB::table('access_permissions')->where('code', 'families.'.$old)->value('id');
                if (!$oldId) continue;
                $owners = DB::table($table)->where('permission_id', $oldId)->when($owner === 'user_id', fn ($q) => $q->where('allowed', true))->pluck($owner);
                foreach ($owners as $ownerId) foreach ($actions as $action) {
                    $id = DB::table('access_permissions')->where('code','families.'.$action)->value('id');
                    DB::table($table)->insertOrIgnore([$owner => $ownerId,'permission_id' => $id,'created_at' => now(),'updated_at' => now()]
                        + ($owner === 'user_id' ? ['allowed' => true] : []));
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_family_history');
        DB::table('access_permissions')->where('module','families')->whereNotIn('action',['view','manage','delete'])->delete();
    }
};
