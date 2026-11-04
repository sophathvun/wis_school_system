<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_g9_exam_import',function(Blueprint $table){
            $table->id();$table->unsignedBigInteger('user_id');$table->string('token_hash',64)->unique();
            $table->string('filename');$table->json('payload');$table->json('summary');
            $table->string('status',20)->default('preview');$table->timestamp('expires_at');$table->timestamps();
        });
        foreach(['import'=>'Preview and confirm G9 CSV / MoEYS Excel imports','export'=>'Download G9 CSV template / export all permitted data'] as $action=>$name)
            DB::table('access_permissions')->updateOrInsert(['code'=>'g9-national-exams.'.$action],['module'=>'g9-national-exams','action'=>$action,'name'=>$name,'created_at'=>now(),'updated_at'=>now()]);
    }
    public function down(): void {Schema::dropIfExists('tb_g9_exam_import');}
};
