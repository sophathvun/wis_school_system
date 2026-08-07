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
        Schema::create('tb_grade', function (Blueprint $table) {
            $table->id();
            $table->string('grade', 20)->unique();
            $table->string('grade_short_name',20)->unique();
            $table->string('grade_order', 3)->nullable();
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
        Schema::dropIfExists('tb_grade');
    }
};
