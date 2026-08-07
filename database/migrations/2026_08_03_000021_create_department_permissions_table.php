<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_department_permissions', function (Blueprint $table) {
            $table->foreignId('department_id')->constrained('access_departments')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('access_permissions')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['department_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_department_permissions');
    }
};
