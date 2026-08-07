<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tb_school_info', function (Blueprint $table) {
            $table->id();
            $table->string('school_name_en', 100);
            $table->string('school_name_kh', 100);
            $table->string('campus_name_en',20)->unique();
            $table->string('campus_name_kh',20)->unique();
            $table->string('address',250)->nullable();
            $table->string('phone',50)->nullable();
            $table->string('description', 100)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_school_info');
    }
};
