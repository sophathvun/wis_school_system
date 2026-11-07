<?php

use App\Support\G9NationalExamPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_g9_exam_certificate_tracking',function(Blueprint $table){
            $table->id();$table->unsignedBigInteger('candidate_id')->unique();
            $table->string('status',30)->default('awaiting');$table->string('number',100)->nullable();
            $table->date('printed_signed_date')->nullable();$table->date('received_date')->nullable();$table->string('recipient')->nullable();
            $table->unsignedInteger('version')->default(1);$table->unsignedBigInteger('updated_by');$table->timestamps();
        });
        Schema::create('tb_g9_exam_certificate_file',function(Blueprint $table){
            $table->id();$table->unsignedBigInteger('candidate_id');$table->string('kind',20);
            $table->string('path');$table->string('thumbnail_path')->nullable();$table->string('original_name');$table->string('mime',80);
            $table->unsignedInteger('size');$table->string('sha256',64);$table->unsignedBigInteger('uploaded_by');$table->timestamps();
            $table->index(['candidate_id','kind','id']);
        });
        G9NationalExamPermissions::register();
        // Existing viewers retain access to documents. Managing and correcting remain separate grants.
        $source=DB::table('access_permissions')->where('code','g9-national-exams.certificates')->value('id');
        $target=DB::table('access_permissions')->where('code','g9-national-exams.certificates-documents-view')->value('id');
        foreach(['access_role_permissions'=>'role_id','access_department_permissions'=>'department_id','access_user_permission_overrides'=>'user_id'] as $table=>$owner){
            $owners=DB::table($table)->where('permission_id',$source)->when($owner==='user_id',fn($q)=>$q->where('allowed',true))->pluck($owner);
            foreach($owners as $id)if(!DB::table($table)->where($owner,$id)->where('permission_id',$target)->exists())
                DB::table($table)->insert([$owner=>$id,'permission_id'=>$target,'created_at'=>now(),'updated_at'=>now()]+($owner==='user_id'?['allowed'=>true]:[]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_g9_exam_certificate_file');Schema::dropIfExists('tb_g9_exam_certificate_tracking');
    }
};
