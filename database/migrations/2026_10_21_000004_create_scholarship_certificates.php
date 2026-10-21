<?php

use App\Support\ScholarshipPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_scholarship_certificate_setting',function(Blueprint $t){
            $t->id();$t->foreignId('academic_year_id')->unique()->constrained('tb_academic_year');
            $t->date('issue_date')->nullable();$t->string('location',150)->default('Phnom Penh');
            $t->string('scholarship_year',30)->nullable();$t->json('signers')->nullable();$t->json('layouts')->nullable();
            $t->string('frame_path')->nullable();$t->string('logo_path')->nullable();$t->unsignedInteger('version')->default(1);$t->timestamps();
        });
        Schema::create('tb_scholarship_certificate',function(Blueprint $t){
            $t->id();$t->foreignId('academic_year_id')->constrained('tb_academic_year');
            $t->foreignId('student_id')->constrained('tb_student');$t->foreignId('campus_id')->constrained('tb_school_info');
            $t->unsignedInteger('sequence');$t->string('certificate_number',20)->unique();$t->string('type',20);$t->json('snapshot');
            $t->string('status',20)->default('active');$t->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();$t->text('cancel_reason')->nullable();$t->timestamp('cancelled_at')->nullable();
            $t->timestamps();$t->unique(['academic_year_id','student_id'],'schcert_year_student_unique');$t->unique(['academic_year_id','sequence'],'schcert_year_sequence_unique');$t->index(['academic_year_id','campus_id','status'],'schcert_year_campus_status');
        });
        foreach(ScholarshipPermissions::catalog() as $action=>$name)DB::table('access_permissions')->updateOrInsert(['code'=>'western-scholarship.'.$action],[
            'module'=>'western-scholarship','action'=>$action,'name'=>$name,'created_at'=>now(),'updated_at'=>now(),
        ]);
    }
    public function down(): void
    {
        Schema::dropIfExists('tb_scholarship_certificate');Schema::dropIfExists('tb_scholarship_certificate_setting');
        DB::table('access_permissions')->where('module','western-scholarship')->whereIn('action',['tab-certificates','certificate-settings','certificate-template','certificate-assign','certificate-print','certificate-cancel'])->delete();
    }
};
