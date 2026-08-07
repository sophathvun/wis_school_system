<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('tb_class')->cascadeOnDelete();
            $table->string('group_name', 20);
            $table->string('group_order', 3)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['class_id', 'group_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_group');
    }
};
