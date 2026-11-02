<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_g12_exam_file', function (Blueprint $table) {
            $table->string('thumbnail_path', 700)->nullable()->after('path');
            $table->unsignedBigInteger('thumbnail_size')->nullable()->after('size');
            $table->unsignedInteger('width')->nullable()->after('thumbnail_size');
            $table->unsignedInteger('height')->nullable()->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('tb_g12_exam_file', function (Blueprint $table) {
            $table->dropColumn(['thumbnail_path', 'thumbnail_size', 'width', 'height']);
        });
    }
};
