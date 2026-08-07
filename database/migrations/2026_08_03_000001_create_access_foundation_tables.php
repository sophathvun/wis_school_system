<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)->after('password');
            $table->string('preferred_locale', 10)->default('en')->after('status');
            $table->foreignId('active_campus_id')->nullable()->after('preferred_locale')
                ->constrained('tb_school_info')->nullOnDelete();
        });

        Schema::create('access_roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->boolean('is_global')->default(false);
            $table->boolean('is_system')->default(true);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('access_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 120)->unique();
            $table->string('module', 80);
            $table->string('action', 80);
            $table->string('name', 120);
            $table->timestamps();
        });

        Schema::create('access_role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('access_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('access_permissions')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('access_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('access_roles')->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained('tb_school_info')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'role_id', 'campus_id']);
            $table->index(['user_id', 'campus_id']);
        });

        Schema::create('access_user_campuses', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('tb_school_info')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();
            $table->primary(['user_id', 'campus_id']);
            $table->index(['campus_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_user_campuses');
        Schema::dropIfExists('access_user_roles');
        Schema::dropIfExists('access_role_permissions');
        Schema::dropIfExists('access_permissions');
        Schema::dropIfExists('access_roles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['active_campus_id']);
            $table->dropColumn(['active_campus_id', 'preferred_locale', 'status']);
        });
    }
};
