<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_g9_photo_batch',function(Blueprint $table){
            $table->id();$table->unsignedBigInteger('user_id');$table->unsignedBigInteger('academic_year_id');
            $table->string('token_hash',64)->unique();$table->string('folder');$table->json('payload');$table->json('summary');
            $table->boolean('replace_existing')->default(false);$table->string('status',20)->default('preview');$table->timestamp('expires_at');$table->timestamps();
        });
        foreach(['photos'=>'View G9 bulk student photos','photos-upload'=>'Preview and upload matched G9 student photos','photos-replace'=>'Replace existing G9 student photos'] as $action=>$name)
            DB::table('access_permissions')->updateOrInsert(['code'=>'g9-national-exams.'.$action],['module'=>'g9-national-exams','action'=>$action,'name'=>$name,'created_at'=>now(),'updated_at'=>now()]);
    }
    public function down(): void {Schema::dropIfExists('tb_g9_photo_batch');}
};
