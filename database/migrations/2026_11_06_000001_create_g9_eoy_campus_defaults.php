<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_g9_eoy_campus_default',function(Blueprint $table){
            $table->id();$table->unsignedBigInteger('campus_id')->unique();
            $table->string('compiler_name')->nullable();$table->string('director_name')->nullable();
            $table->unsignedInteger('version')->default(1);$table->unsignedBigInteger('updated_by')->nullable();$table->timestamps();
        });
    }
    public function down(): void {} // Retain shared names when rolling back application code.
};
