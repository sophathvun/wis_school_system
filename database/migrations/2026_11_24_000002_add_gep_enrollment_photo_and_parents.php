<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_gep_student', fn(Blueprint $t) => $t->string('photo_path')->nullable());
        Schema::table('tb_gep_enrollment', fn(Blueprint $t) => $t->json('parent_information')->nullable());
    }

    public function down(): void
    {
        Schema::table('tb_gep_enrollment', fn(Blueprint $t) => $t->dropColumn('parent_information'));
        Schema::table('tb_gep_student', fn(Blueprint $t) => $t->dropColumn('photo_path'));
    }
};
