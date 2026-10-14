<?php

use App\Support\StudentSkippingGradePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('skipping_document_archives', function (Blueprint $t) {
            $t->id(); $t->uuid('reference')->unique(); $t->unsignedBigInteger('academic_year_id');
            $t->string('academic_year'); $t->unsignedBigInteger('campus_id')->nullable();
            $t->string('status')->default('building'); $t->text('error')->nullable();
            $t->unsignedBigInteger('created_by'); $t->unsignedBigInteger('confirmed_by')->nullable();
            $t->timestamp('confirmed_at')->nullable(); $t->timestamps();
        });
        Schema::create('skipping_document_archive_parts', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('archive_id')->index(); $t->unsignedInteger('number');
            $t->string('path'); $t->string('status')->default('queued'); $t->string('sha256',64)->nullable();
            $t->unsignedBigInteger('bytes')->default(0); $t->text('error')->nullable();
            $t->timestamp('verified_at')->nullable(); $t->timestamp('downloaded_at')->nullable();
            $t->timestamp('restored_at')->nullable(); $t->timestamps(); $t->unique(['archive_id','number']);
        });
        Schema::create('skipping_document_archive_files', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('archive_id')->index(); $t->unsignedBigInteger('part_id')->index();
            $t->unsignedBigInteger('request_id')->index(); $t->string('kind'); $t->unsignedInteger('document_index')->nullable();
            $t->string('source_path'); $t->string('entry_path'); $t->string('original_name');
            $t->unsignedBigInteger('bytes'); $t->string('sha256',64)->nullable(); $t->json('student');
            $t->timestamp('deleted_at')->nullable(); $t->timestamp('restored_at')->nullable(); $t->timestamps();
        });
        Schema::create('skipping_document_archive_events', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('archive_id')->index(); $t->unsignedBigInteger('user_id')->nullable();
            $t->string('action'); $t->text('details')->nullable(); $t->timestamp('created_at');
        });
        Schema::table('tb_student_skipping_grade', function (Blueprint $t) {
            $t->timestamp('signed_request_archived_at')->nullable();
            $t->unsignedBigInteger('signed_request_archive_id')->nullable();
        });
        foreach (['document-archive','archive-backup','archive-delete','archive-restore'] as $action) {
            DB::table('access_permissions')->updateOrInsert(['code'=>'student-skipping-grade.'.$action], [
                'module'=>'student-skipping-grade','action'=>$action,'name'=>StudentSkippingGradePermissions::catalog()[$action],
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
        $role=DB::table('access_roles')->where('code','super-admin')->value('id');
        if ($role) foreach (DB::table('access_permissions')->whereIn('action',['document-archive','archive-backup','archive-delete','archive-restore'])->where('module','student-skipping-grade')->pluck('id') as $id) {
            DB::table('access_role_permissions')->updateOrInsert(['role_id'=>$role,'permission_id'=>$id],['created_at'=>now(),'updated_at'=>now()]);
        }
    }

    public function down(): void
    {
        // Archive manifests are needed to restore deleted files. Require an explicit
        // recovery/export decision rather than silently destroying that history.
        if (DB::table('skipping_document_archives')->exists()) throw new RuntimeException('Document archive history exists. Preserve it before rolling back this migration.');
        $ids=DB::table('access_permissions')->where('module','student-skipping-grade')->whereIn('action',['document-archive','archive-backup','archive-delete','archive-restore'])->pluck('id');
        foreach (['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table) DB::table($table)->whereIn('permission_id',$ids)->delete();
        DB::table('access_permissions')->whereIn('id',$ids)->delete();
        Schema::table('tb_student_skipping_grade',fn(Blueprint $t)=>$t->dropColumn(['signed_request_archived_at','signed_request_archive_id']));
        foreach (['events','files','parts'] as $table) Schema::dropIfExists('skipping_document_archive_'.$table);
        Schema::dropIfExists('skipping_document_archives');
    }
};
