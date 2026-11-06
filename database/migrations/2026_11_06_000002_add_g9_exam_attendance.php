<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_g9_exam_candidate',function(Blueprint $table){
            $table->string('attendance_status',16)->nullable();
        });
    }
    public function down(): void {} // Preserve recorded exam attendance on application rollback.
};
