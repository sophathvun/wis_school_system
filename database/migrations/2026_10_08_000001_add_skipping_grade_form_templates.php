<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_skipping_grade_settings', function (Blueprint $table) {
            $table->json('request_template')->nullable();
            $table->json('approval_template')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tb_skipping_grade_settings', fn (Blueprint $table) => $table->dropColumn(['request_template', 'approval_template']));
    }
};
