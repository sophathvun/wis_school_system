<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_scholarship_paper',fn(Blueprint $t)=>$t->json('draft_points')->nullable());
        Schema::table('tb_scholarship_answer_key',fn(Blueprint $t)=>$t->json('question_points')->nullable());
        // Null keeps the original uniform scoring for already approved keys.
    }
    public function down(): void
    {
        Schema::table('tb_scholarship_answer_key',fn(Blueprint $t)=>$t->dropColumn('question_points'));
        Schema::table('tb_scholarship_paper',fn(Blueprint $t)=>$t->dropColumn('draft_points'));
    }
};
