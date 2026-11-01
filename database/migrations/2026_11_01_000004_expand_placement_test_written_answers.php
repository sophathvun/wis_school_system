<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_placement_test_question', function (Blueprint $table) {
            $table->text('correct_answer')->nullable()->change();
        });
        Schema::table('tb_placement_test_answer', function (Blueprint $table) {
            $table->longText('answer')->nullable()->change();
            $table->longText('detected_answer')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tb_placement_test_question', function (Blueprint $table) {
            $table->string('correct_answer', 10)->nullable(false)->change();
        });
        Schema::table('tb_placement_test_answer', function (Blueprint $table) {
            $table->string('answer', 20)->nullable()->change();
            $table->string('detected_answer', 20)->nullable()->change();
        });
    }
};
