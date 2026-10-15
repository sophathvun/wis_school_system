<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_scholarship_print_setting',function(Blueprint $table){
            foreach(['prepared','checked','approved'] as $role){
                $table->foreignId($role.'_staff_id')->nullable()->constrained('hrm_staff')->nullOnDelete();
                $table->string($role.'_position',255)->nullable();
                $table->string($role.'_by_name',255)->nullable($role==='checked')->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tb_scholarship_print_setting',function(Blueprint $table){
            foreach(['prepared','checked','approved'] as $role){
                $table->dropConstrainedForeignId($role.'_staff_id');
                $table->dropColumn($role.'_position');
            }
        });
    }
};
