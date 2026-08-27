<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('layout')->default('premium_grid');
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type')->default('stat_card');
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->text('description')->nullable();
            $table->string('permission_code')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('dashboard_template_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_template_id')->constrained('dashboard_templates')->cascadeOnDelete();
            $table->foreignId('dashboard_widget_id')->constrained('dashboard_widgets')->cascadeOnDelete();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->string('width')->default('medium');
            $table->boolean('status')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['dashboard_template_id', 'dashboard_widget_id'], 'dashboard_template_widget_unique');
        });

        Schema::create('dashboard_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_template_id')->constrained('dashboard_templates')->cascadeOnDelete();
            $table->string('assignment_type');
            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['assignment_type', 'assignment_id'], 'dashboard_assignment_target_unique');
        });

        $now = now();
        foreach ($this->widgets() as $widget) {
            DB::table('dashboard_widgets')->updateOrInsert(
                ['code' => $widget['code']],
                $widget + ['created_at' => $now, 'updated_at' => $now]
            );
        }

        foreach ($this->permissions() as [$module, $action, $name]) {
            $permissionId = DB::table('access_permissions')->updateOrInsert(
                ['code' => "{$module}.{$action}"],
                ['module' => $module, 'action' => $action, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $permissionIds = DB::table('access_permissions')->where('module', 'dashboard-templates')->pluck('id')->all();
        $superAdminRoleId = DB::table('access_roles')->where('code', 'super-admin')->value('id');
        if ($superAdminRoleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('access_role_permissions')->insertOrIgnore([
                    'role_id' => $superAdminRoleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('access_permissions')->where('module', 'dashboard-templates')->delete();
        Schema::dropIfExists('dashboard_assignments');
        Schema::dropIfExists('dashboard_template_widgets');
        Schema::dropIfExists('dashboard_widgets');
        Schema::dropIfExists('dashboard_templates');
    }

    private function widgets(): array
    {
        return [
            ['name' => 'Total Students', 'code' => 'total_students', 'type' => 'stat_card', 'icon' => 'ti-users', 'color' => 'primary', 'description' => 'Total registered students.', 'permission_code' => 'students.search.view', 'status' => true],
            ['name' => 'Active Students', 'code' => 'active_students', 'type' => 'stat_card', 'icon' => 'ti-user-check', 'color' => 'success', 'description' => 'Current active students.', 'permission_code' => 'students.search.view', 'status' => true],
            ['name' => 'New Enrollments', 'code' => 'new_enrollments', 'type' => 'stat_card', 'icon' => 'ti-user-plus', 'color' => 'info', 'description' => 'Recent student enrollments.', 'permission_code' => 'students.enrollment.view', 'status' => true],
            ['name' => 'Withdrawn Students', 'code' => 'withdrawn_students', 'type' => 'stat_card', 'icon' => 'ti-user-minus', 'color' => 'danger', 'description' => 'Withdrawn student summary.', 'permission_code' => 'students.enrollment.view', 'status' => true],
            ['name' => 'Active Academic Year', 'code' => 'active_academic_year', 'type' => 'info_card', 'icon' => 'ti-calendar', 'color' => 'azure', 'description' => 'Current started academic year.', 'permission_code' => 'academic-years.view', 'status' => true],
            ['name' => 'Total Staff / Users', 'code' => 'total_staff_users', 'type' => 'stat_card', 'icon' => 'ti-id-badge', 'color' => 'purple', 'description' => 'Total active staff users.', 'permission_code' => 'users.view', 'status' => true],
            ['name' => 'Notifications', 'code' => 'notifications', 'type' => 'list', 'icon' => 'ti-bell', 'color' => 'yellow', 'description' => 'Recent notifications.', 'permission_code' => 'notifications.view', 'status' => true],
            ['name' => 'Recent Activities', 'code' => 'recent_activities', 'type' => 'timeline', 'icon' => 'ti-activity', 'color' => 'teal', 'description' => 'Recent system activity.', 'permission_code' => 'dashboard.view', 'status' => true],
        ];
    }

    private function permissions(): array
    {
        return [
            ['dashboard-templates', 'view', 'View Dashboard Templates'],
            ['dashboard-templates', 'create', 'Create Dashboard Templates'],
            ['dashboard-templates', 'update', 'Update Dashboard Templates'],
            ['dashboard-templates', 'delete', 'Delete Dashboard Templates'],
            ['dashboard-templates', 'assign', 'Assign Dashboard Templates'],
        ];
    }
};
