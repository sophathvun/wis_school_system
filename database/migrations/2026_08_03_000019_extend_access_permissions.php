<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('access_departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::table('access_roles', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('description')->constrained('access_departments')->nullOnDelete();
        });

        Schema::create('access_user_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('access_permissions')->cascadeOnDelete();
            $table->boolean('allowed')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'permission_id']);
        });

        $departments = ['Central Office', 'Administration', 'Registrar', 'Academic', 'Finance', 'HR', 'Library', 'IT'];
        foreach ($departments as $name) DB::table('access_departments')->insertOrIgnore(['code' => str($name)->slug()->value(), 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);

        $items = [
            ['dashboard', 'view', 'View Dashboard'],
            ['students.search', 'view', 'View Student Search'], ['students.enrollment', 'view', 'View Student Enrollment'], ['students.enrollment', 'create', 'Add Student Enrollment'], ['students.enrollment', 'update', 'Update Student Enrollment'], ['students.enrollment', 'delete', 'Delete Student Enrollment'], ['students.enrollment', 'export', 'Export Enrollment'],
            ['families', 'view', 'View Family Management'], ['families', 'create', 'Add Family'], ['families', 'update', 'Update Family'], ['families', 'delete', 'Delete Family'],
            ['students.promotion', 'view', 'View Promotion / Transfer'], ['students.promotion', 'execute', 'Promote or Transfer Students'],
            ['students.graduation', 'view', 'View Graduation'], ['students.graduation', 'execute', 'Graduate Students'],
            ['settings', 'view', 'View Settings'], ['settings', 'manage', 'Manage Settings'], ['settings.branding', 'update', 'Update Branding'],
            ['reports', 'export', 'Export Reports'],
        ];
        foreach ($items as [$module, $action, $name]) DB::table('access_permissions')->updateOrInsert(['code' => "$module.$action"], ['module' => $module, 'action' => $action, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('access_user_permission_overrides');
        Schema::table('access_roles', fn (Blueprint $table) => $table->dropConstrainedForeignId('department_id'));
        Schema::dropIfExists('access_departments');
    }
};
