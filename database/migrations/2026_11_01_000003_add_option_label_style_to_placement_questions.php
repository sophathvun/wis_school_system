<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_placement_test_question', function (Blueprint $table) {
            $table->string('option_label_style', 16)->default('letters')->after('options');
        });
    }

    public function down(): void
    {
        Schema::table('tb_placement_test_question', function (Blueprint $table) {
            $table->dropColumn('option_label_style');
        });
    }
};
