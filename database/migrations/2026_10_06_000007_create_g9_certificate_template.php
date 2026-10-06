<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_g9_certificate_template', function (Blueprint $table) {
            $table->id();
            $table->json('layout')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });
        DB::table('tb_g9_certificate_template')->insert(['id'=>1,'version'=>0,'created_at'=>now(),'updated_at'=>now()]);
    }

    public function down(): void { Schema::dropIfExists('tb_g9_certificate_template'); }
};
