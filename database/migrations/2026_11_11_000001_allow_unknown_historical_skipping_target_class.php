<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        // Legacy requests specify the requested grade, but do not specify a target class.
        // The normal application form still requires a class for new requests.
        Schema::table('tb_student_skipping_grade',function(Blueprint $table){$table->unsignedBigInteger('target_class_id')->nullable()->change();});
    }
    public function down(): void
    {
        if(\Illuminate\Support\Facades\DB::table('tb_student_skipping_grade')->whereNull('target_class_id')->exists())throw new RuntimeException('Preserve historical requests with unknown target classes before rollback.');
        Schema::table('tb_student_skipping_grade',function(Blueprint $table){$table->unsignedBigInteger('target_class_id')->nullable(false)->change();});
    }
};
