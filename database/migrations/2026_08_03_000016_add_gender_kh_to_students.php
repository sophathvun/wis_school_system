<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tb_student', function (Blueprint $table) {
            $table->string('gender_kh', 30)->nullable()->after('gender');
        });
    }

    public function down(): void
    {
        Schema::table('tb_student', fn (Blueprint $table) => $table->dropColumn('gender_kh'));
    }
};
