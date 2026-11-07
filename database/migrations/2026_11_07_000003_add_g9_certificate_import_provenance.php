<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_g9_exam_certificate_tracking',fn(Blueprint $t)=>$t->json('import_source')->nullable());
    }
    public function down(): void
    {
        Schema::table('tb_g9_exam_certificate_tracking',fn(Blueprint $t)=>$t->dropColumn('import_source'));
    }
};
