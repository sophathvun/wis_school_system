<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_g12_certificate_year', fn (Blueprint $table) => $table->json('typography')->nullable());
    }

    public function down(): void
    {
        Schema::table('tb_g12_certificate_year', fn (Blueprint $table) => $table->dropColumn('typography'));
    }
};
