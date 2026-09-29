<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hrm_staff', function (Blueprint $table) {
            $table->id();
            $table->string('staff_code', 50)->unique();
            $table->string('name_en');
            $table->string('name_kh')->nullable();
            $table->string('gender', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('photo_path')->nullable();
            $table->foreignId('position_id')->nullable()->constrained('access_positions')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('access_departments')->nullOnDelete();
            $table->string('staff_category', 30)->default('staff');
            $table->string('employment_type', 10)->default('FT');
            $table->string('employment_status', 30)->default('active');
            $table->date('joined_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->foreignId('primary_campus_id')->nullable()->constrained('tb_school_info')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['staff_category', 'employment_status', 'status']);
            $table->index(['department_id', 'position_id']);
        });

        Schema::create('hrm_staff_campuses', function (Blueprint $table) {
            $table->foreignId('staff_id')->constrained('hrm_staff')->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('tb_school_info')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();
            $table->primary(['staff_id', 'campus_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('staff_profile_id')->nullable()->after('id')->constrained('hrm_staff')->nullOnDelete();
            $table->unique('staff_profile_id', 'users_staff_profile_unique');
        });

        Schema::table('homeroom_assignments', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->after('id')->constrained('hrm_staff')->nullOnDelete();
            $table->index(['staff_id', 'academic_year_id', 'status'], 'homeroom_staff_year_status_index');
        });

        $now = now();
        DB::table('users')->orderBy('id')->chunkById(100, function ($users) use ($now) {
            foreach ($users as $user) {
                $staffCode = trim((string) ($user->staff_id ?: 'U' . $user->id));
                if ($staffCode === '') {
                    $staffCode = 'U' . $user->id;
                }

                $originalCode = $staffCode;
                $counter = 1;
                while (DB::table('hrm_staff')->where('staff_code', $staffCode)->exists()) {
                    $staffCode = $originalCode . '-' . $counter++;
                }

                $staffId = DB::table('hrm_staff')->insertGetId([
                    'staff_code' => $staffCode,
                    'name_en' => $user->name ?: $staffCode,
                    'gender' => $user->gender,
                    'date_of_birth' => $user->date_of_birth,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'photo_path' => $user->photo_path,
                    'position_id' => $user->position_id,
                    'department_id' => $user->department_id,
                    'staff_category' => 'staff_teacher',
                    'employment_type' => 'FT',
                    'employment_status' => ((int) $user->status === 1) ? 'active' : 'suspended',
                    'primary_campus_id' => $user->active_campus_id,
                    'status' => (int) $user->status === 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('users')->where('id', $user->id)->update(['staff_profile_id' => $staffId]);

                $campusRows = DB::table('access_user_campuses')->where('user_id', $user->id)->get();
                foreach ($campusRows as $campusRow) {
                    DB::table('hrm_staff_campuses')->updateOrInsert(
                        ['staff_id' => $staffId, 'campus_id' => $campusRow->campus_id],
                        ['is_primary' => (bool) $campusRow->is_primary, 'status' => true, 'assigned_at' => $campusRow->assigned_at ?: $now, 'created_at' => $now, 'updated_at' => $now]
                    );
                }
            }
        });

        DB::table('homeroom_assignments')
            ->join('users', 'users.id', '=', 'homeroom_assignments.teacher_id')
            ->whereNotNull('users.staff_profile_id')
            ->update(['homeroom_assignments.staff_id' => DB::raw('users.staff_profile_id')]);

        $permissions = [
            ['staff.view', 'staff', 'view', 'View Staff Management'],
            ['staff.create', 'staff', 'create', 'Create Staff Profiles'],
            ['staff.edit', 'staff', 'edit', 'Edit Staff Profiles'],
            ['staff.delete', 'staff', 'delete', 'Delete Staff Profiles'],
        ];

        foreach ($permissions as [$code, $module, $action, $name]) {
            DB::table('access_permissions')->updateOrInsert(
                ['code' => $code],
                ['module' => $module, 'action' => $action, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        if ($superAdminId = DB::table('access_roles')->where('code', 'super-admin')->value('id')) {
            $permissionIds = DB::table('access_permissions')->where('module', 'staff')->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('access_role_permissions')->updateOrInsert(
                    ['role_id' => $superAdminId, 'permission_id' => $permissionId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('access_permissions')->where('module', 'staff')->delete();

        Schema::table('homeroom_assignments', function (Blueprint $table) {
            $table->dropIndex('homeroom_staff_year_status_index');
            $table->dropConstrainedForeignId('staff_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_staff_profile_unique');
            $table->dropConstrainedForeignId('staff_profile_id');
        });

        Schema::dropIfExists('hrm_staff_campuses');
        Schema::dropIfExists('hrm_staff');
    }
};
