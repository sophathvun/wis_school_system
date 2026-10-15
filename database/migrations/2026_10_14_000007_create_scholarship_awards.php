<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_scholarship_exam', fn (Blueprint $table) => $table->json('award_scheme')->nullable());
        Schema::create('tb_scholarship_award', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('tb_scholarship_exam')->cascadeOnDelete();
            $table->unsignedTinyInteger('grade_number');
            $table->string('slot', 40);
            $table->foreignId('candidate_id')->nullable()->constrained('tb_scholarship_candidate')->cascadeOnDelete();
            $table->decimal('award_percent', 5, 2);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['exam_id','grade_number','slot'], 'scholarship_award_slot_unique');
            $table->unique(['exam_id','candidate_id'], 'scholarship_award_recipient_unique');
        });
        DB::table('access_permissions')->updateOrInsert(['code'=>'western-scholarship.awards'], ['module'=>'western-scholarship','action'=>'awards','name'=>'Select and edit scholarship award winners','created_at'=>now(),'updated_at'=>now()]);
        $role=DB::table('access_roles')->where('code','super-admin')->value('id');
        if ($role) DB::table('access_role_permissions')->updateOrInsert(['role_id'=>$role,'permission_id'=>DB::table('access_permissions')->where('code','western-scholarship.awards')->value('id')], ['created_at'=>now(),'updated_at'=>now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_scholarship_award');
        Schema::table('tb_scholarship_exam', fn (Blueprint $table) => $table->dropColumn('award_scheme'));
        $id=DB::table('access_permissions')->where('code','western-scholarship.awards')->value('id');
        foreach (['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table) DB::table($table)->where('permission_id',$id)->delete();
        DB::table('access_permissions')->where('id',$id)->delete();
    }
};
