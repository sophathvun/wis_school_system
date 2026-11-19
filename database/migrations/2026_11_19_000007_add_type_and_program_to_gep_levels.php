<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_gep_level', function (Blueprint $table) {
            $table->string('type', 100)->nullable();
            $table->string('program', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tb_gep_level', fn (Blueprint $table) => $table->dropColumn(['type', 'program']));
    }
};
