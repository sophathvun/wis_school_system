<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_g12_ocr_setting', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('monthly_limit')->default(999);
            $table->string('project_id')->nullable();
            $table->string('service_account_email')->nullable();
            $table->longText('credentials')->nullable();
            $table->text('school_aliases')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
        Schema::create('tb_g12_ocr_usage', function (Blueprint $table) {
            $table->id();
            $table->char('billing_month',7)->index();
            $table->foreignId('import_id')->constrained('tb_g12_exam_import')->cascadeOnDelete();
            $table->unsignedInteger('image_index');
            $table->unsignedBigInteger('user_id');
            $table->string('status',20)->default('reserved');
            $table->string('sha256',64);
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['import_id','image_index']);
        });

        \App\Support\G12NationalExamPermissions::registerMissing();
        $manageId=DB::table('access_permissions')->where('code','g12-national-exams.scoring-manage')->value('id');
        $actions=['scoring-csv-export','scoring-csv-import','scoring-ocr-upload','scoring-ocr-review'];
        foreach($actions as $action){
            $target=DB::table('access_permissions')->where('code','g12-national-exams.'.$action)->value('id');
            if(!$manageId||!$target)continue;
            foreach(['access_role_permissions'=>'role_id','access_department_permissions'=>'department_id','access_user_permission_overrides'=>'user_id'] as $table=>$owner){
                if(!Schema::hasTable($table))continue;
                $owners=DB::table($table)->where('permission_id',$manageId)->when($owner==='user_id',fn($q)=>$q->where('allowed',true))->pluck($owner);
                foreach($owners as $ownerId)DB::table($table)->insertOrIgnore([$owner=>$ownerId,'permission_id'=>$target,'created_at'=>now(),'updated_at'=>now()]+($owner==='user_id'?['allowed'=>true]:[]));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_g12_ocr_usage');
        Schema::dropIfExists('tb_g12_ocr_setting');
        $ids=DB::table('access_permissions')->whereIn('code',collect(['scoring-csv-export','scoring-csv-import','scoring-ocr-upload','scoring-ocr-review','scoring-ocr-settings'])->map(fn($a)=>'g12-national-exams.'.$a))->pluck('id');
        foreach(['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table)if(Schema::hasTable($table))DB::table($table)->whereIn('permission_id',$ids)->delete();
        DB::table('access_permissions')->whereIn('id',$ids)->delete();
    }
};
