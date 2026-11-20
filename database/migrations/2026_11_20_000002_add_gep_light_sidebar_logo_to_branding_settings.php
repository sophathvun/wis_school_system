<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up():void
    {
        Schema::table('tb_branding_setting',fn(Blueprint $table)=>$table->string('gep_light_sidebar_logo_path')->nullable());
    }
    public function down():void
    {
        Schema::table('tb_branding_setting',fn(Blueprint $table)=>$table->dropColumn('gep_light_sidebar_logo_path'));
    }
};
