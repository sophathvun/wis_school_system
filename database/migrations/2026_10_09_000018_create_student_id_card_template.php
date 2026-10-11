<?php

use App\Support\StudentIdCardLayout;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_student_id_card_template', function (Blueprint $table) {
            $table->id();
            $table->json('layout')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });
        DB::table('tb_student_id_card_template')->insert(['id' => 1, 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['student-id-card-qr.view' => 'View Student ID Cards', StudentIdCardLayout::EDIT_PERMISSION => 'Student ID Card: Edit Template (all years)'] as $code => $name) {
            if (!DB::table('access_permissions')->where('code', $code)->exists()) {
                DB::table('access_permissions')->insert([
                    'code' => $code, 'module' => 'student-id-card-qr', 'action' => substr($code, strlen('student-id-card-qr.')),
                    'name' => $name, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        $superAdmin = DB::table('access_roles')->where('code', 'super-admin')->value('id');
        if ($superAdmin) {
            foreach (DB::table('access_permissions')->whereIn('code', ['student-id-card-qr.view', StudentIdCardLayout::EDIT_PERMISSION])->pluck('id') as $id) {
                DB::table('access_role_permissions')->updateOrInsert(['role_id' => $superAdmin, 'permission_id' => $id], ['created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_student_id_card_template');
        DB::table('access_permissions')->where('code', StudentIdCardLayout::EDIT_PERMISSION)->delete();
    }
};
