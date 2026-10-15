<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_scholarship_print_setting',function(Blueprint $table){
            $table->string('checked_by_name',150)->nullable();
            $table->date('checked_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tb_scholarship_print_setting',fn(Blueprint $table)=>$table->dropColumn(['checked_by_name','checked_date']));
    }
};
