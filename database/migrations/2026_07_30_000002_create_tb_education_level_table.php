<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_education_level', function (Blueprint $table) {
            $table->id();
            $table->string('level_name', 50)->unique();
            $table->string('level_short_name', 20)->unique();
            $table->string('level_order', 3)->nullable();
            $table->string('description', 100)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_education_level');
    }
};
