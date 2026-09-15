<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AccessFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['code' => 'dashboard.view', 'module' => 'dashboard', 'action' => 'view', 'name' => 'View dashboard'],
            ['code' => 'dashboard.customize', 'module' => 'dashboard', 'action' => 'customize', 'name' => 'Customize Own Dashboard'],
            ['code' => 'dashboard.reset', 'module' => 'dashboard', 'action' => 'reset', 'name' => 'Reset Own Dashboard'],
            ['code' => 'students.view', 'module' => 'students', 'action' => 'view', 'name' => 'View students'],
            ['code' => 'students.manage', 'module' => 'students', 'action' => 'manage', 'name' => 'Manage students'],
            ['code' => 'settings.view', 'module' => 'settings', 'action' => 'view', 'name' => 'View Settings menu'],
            ['code' => 'settings.manage', 'module' => 'settings', 'action' => 'manage', 'name' => 'Manage settings'],
            ['code' => 'reports.view', 'module' => 'reports', 'action' => 'view', 'name' => 'View reports'],
            ['code' => 'reports.export', 'module' => 'reports', 'action' => 'export', 'name' => 'Export reports'],
            ['code' => 'administrator.view', 'module' => 'administrator', 'action' => 'view', 'name' => 'View Administrator menu'],
            ['code' => 'communication.view', 'module' => 'communication', 'action' => 'view', 'name' => 'View Communication menu'],
            ['code' => 'database-backups.view', 'module' => 'database-backups', 'action' => 'view', 'name' => 'View database backups'],
            ['code' => 'database-backups.create', 'module' => 'database-backups', 'action' => 'create', 'name' => 'Create database backups'],
            ['code' => 'database-backups.download', 'module' => 'database-backups', 'action' => 'download', 'name' => 'Download database backups'],
            ['code' => 'database-backups.delete', 'module' => 'database-backups', 'action' => 'delete', 'name' => 'Delete database backups'],
        ];

        // Keep the permission catalog in sync with the modules exposed by the
        // application. updateOrCreate below makes this safe to run on existing
        // installations without changing current assignments.
        $moduleActions = [
            'users' => ['view' => 'View users', 'manage' => 'Manage users', 'export' => 'Export users', 'delete' => 'Delete users'],
            'departments' => ['view' => 'View departments', 'manage' => 'Manage departments', 'export' => 'Export departments', 'delete' => 'Delete departments'],
            'positions' => ['view' => 'View positions', 'manage' => 'Manage positions', 'export' => 'Export positions', 'delete' => 'Delete positions'],
            'roles' => ['view' => 'View roles', 'manage' => 'Manage roles', 'export' => 'Export roles', 'delete' => 'Delete roles'],
            'dashboard-templates' => ['view' => 'View dashboard templates', 'manage' => 'Manage dashboard templates', 'delete' => 'Delete dashboard templates'],
            'branding' => ['view' => 'View branding settings', 'manage' => 'Manage branding settings'],
            'chat' => ['view' => 'View chat', 'manage' => 'Manage chat', 'download' => 'Download chat media'],
            'notifications' => ['view' => 'View notifications', 'manage' => 'Manage notifications', 'send' => 'Send notifications', 'delete' => 'Delete notifications'],
            'academic-years' => ['view' => 'View academic years', 'manage' => 'Manage academic years', 'export' => 'Export academic years', 'delete' => 'Delete academic years'],
            'grades' => ['view' => 'View grades', 'manage' => 'Manage grades', 'export' => 'Export grades', 'delete' => 'Delete grades'],
            'classes' => ['view' => 'View classes', 'manage' => 'Manage classes', 'export' => 'Export classes', 'delete' => 'Delete classes'],
            'sessions' => ['view' => 'View sessions', 'manage' => 'Manage sessions', 'export' => 'Export sessions', 'delete' => 'Delete sessions'],
            'education-levels' => ['view' => 'View education levels', 'manage' => 'Manage education levels', 'delete' => 'Delete education levels'],
            'programs' => ['view' => 'View programs', 'manage' => 'Manage programs', 'delete' => 'Delete programs'],
            'groups' => ['view' => 'View school groups', 'manage' => 'Manage school groups', 'delete' => 'Delete school groups'],
            'terms' => ['view' => 'View terms', 'manage' => 'Manage terms', 'delete' => 'Delete terms'],
            'school-info' => ['view' => 'View school information', 'manage' => 'Manage school information', 'export' => 'Export school information'],
            'locations' => ['view' => 'View locations', 'manage' => 'Manage locations', 'export' => 'Export locations', 'delete' => 'Delete locations'],
            'occupations' => ['view' => 'View occupations', 'manage' => 'Manage occupations', 'export' => 'Export occupations', 'delete' => 'Delete occupations'],
            'nationalities' => ['view' => 'View nationalities', 'manage' => 'Manage nationalities', 'delete' => 'Delete nationalities'],
            'academic-tracks' => ['view' => 'View academic tracks', 'manage' => 'Manage academic tracks', 'export' => 'Export academic tracks', 'delete' => 'Delete academic tracks'],
            'withdrawal-reasons' => ['view' => 'View withdrawal reasons', 'manage' => 'Manage withdrawal reasons', 'export' => 'Export withdrawal reasons', 'delete' => 'Delete withdrawal reasons'],
            'student-document-types' => ['view' => 'View student document types', 'manage' => 'Manage student document types', 'delete' => 'Delete student document types'],
            'students.search' => ['view' => 'Search students', 'export' => 'Export student search results'],
            'students.enrollment' => ['view' => 'View student enrollment', 'manage' => 'Manage student enrollment', 'delete' => 'Delete enrollments'],
            'summer-school' => ['view' => 'View summer school', 'manage' => 'Manage summer school'],
            'families' => ['view' => 'View families', 'manage' => 'Manage families', 'delete' => 'Delete families'],
            'students.promotion' => ['view' => 'View student promotion and transfer', 'manage' => 'Manage student promotion and transfer'],
            'students.graduation' => ['view' => 'View student graduation', 'manage' => 'Manage student graduation'],
            'student-reentry' => ['view' => 'View student re-entry', 'manage' => 'Manage student re-entry'],
            'student-documents' => ['view' => 'View student documents', 'manage' => 'Manage student documents', 'preview' => 'View student document files', 'download' => 'Download student documents', 'delete' => 'Delete student documents'],
            'student-data-transfer' => ['view' => 'View student data transfer', 'import' => 'Import student data', 'export' => 'Export student data'],
            'student-withdrawals' => ['view' => 'View student withdrawals', 'manage' => 'Manage student withdrawals', 'approve' => 'Approve student withdrawals', 'delete' => 'Delete student withdrawals'],
            'campuses' => ['view' => 'View campuses', 'manage' => 'Manage campuses'],
        ];

        // Keep the catalog in sync with the granular actions introduced by
        // the current application workflows.  This merge is intentionally
        // additive so existing permission assignments remain untouched.
        $moduleActions = array_replace_recursive($moduleActions, [
            'users' => ['create' => 'Create Users', 'update' => 'Update Users', 'status' => 'Activate / Deactivate Users', 'permissions' => 'Assign User Permissions', 'campuses' => 'Assign User Campuses'],
            'departments' => ['create' => 'Create Departments', 'update' => 'Update Departments', 'status' => 'Activate / Deactivate Departments', 'permissions' => 'Assign Department Permissions'],
            'positions' => ['create' => 'Create Positions', 'update' => 'Update Positions', 'status' => 'Activate / Deactivate Positions'],
            'roles' => ['create' => 'Create Roles', 'update' => 'Update Roles', 'status' => 'Activate / Deactivate Roles', 'permissions' => 'Assign Role Permissions'],
            'notifications' => ['create' => 'Create Notifications', 'update' => 'Update Notifications', 'send' => 'Send Notifications'],
            'chat' => ['send' => 'Send Messages', 'attach' => 'Attach Files and Photos', 'voice' => 'Send Voice Messages'],
            'branding' => ['update' => 'Update Branding'],
            'database-backups' => ['restore' => 'Restore Database Backups'],
            'students.search' => ['view' => 'View Student Search'],
            'students.enrollment' => ['create' => 'Create Student Enrollment', 'update' => 'Update Student Enrollment', 'status' => 'Change Enrollment Status', 'export' => 'Export Enrollment'],
            'students.promotion' => ['execute' => 'Promote or Transfer Students', 'cancel' => 'Cancel Promotion or Transfer', 'export' => 'Export Promotion History'],
            'students.transfer' => ['view' => 'View Student Transfer', 'manage' => 'Manage Student Transfer', 'execute' => 'Transfer Students', 'cancel' => 'Cancel Student Transfer', 'export' => 'Export Transfer Records'],
            'students.graduation' => ['execute' => 'Graduate Students', 'cancel' => 'Cancel Graduation', 'export' => 'Export Graduation Records'],
            'student-reentry' => ['create' => 'Create Student Re-entry', 'update' => 'Update Student Re-entry', 'cancel' => 'Cancel Student Re-entry'],
            'student-documents' => ['create' => 'Upload Student Documents', 'update' => 'Update Student Documents', 'preview' => 'View Student Document Files'],
            'student-withdrawals' => ['approve' => 'Approve student withdrawals'],
        ]);

        foreach (['academic-years' => 'Academic Years', 'grades' => 'Grades', 'classes' => 'Classes', 'sessions' => 'Groups', 'education-levels' => 'Education Levels', 'programs' => 'Programs', 'school-info' => 'School Information', 'locations' => 'Locations', 'occupations' => 'Occupations', 'academic-tracks' => 'Academic Tracks', 'withdrawal-reasons' => 'Withdrawal Reasons', 'student-document-types' => 'Document Types'] as $module => $label) {
            $moduleActions[$module] = array_replace($moduleActions[$module] ?? [], [
                'create' => "Create {$label}", 'update' => "Update {$label}", 'status' => "Activate / Deactivate {$label}",
            ]);
        }

        foreach ($moduleActions as $module => $actions) {
            foreach ($actions as $action => $name) {
                $permissions[] = [
                    'code' => "{$module}.{$action}",
                    'module' => $module,
                    'action' => $action,
                    'name' => $name,
                ];
            }
        }

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['code' => $permission['code']], $permission);
        }

        $roles = [
            ['code' => 'super-admin', 'name' => 'Super Administrator', 'is_global' => true],
            ['code' => 'central-office-admin', 'name' => 'Central Office Administrator', 'is_global' => true],
            ['code' => 'campus-admin', 'name' => 'Campus Administrator', 'is_global' => false],
            ['code' => 'registrar', 'name' => 'Registrar', 'is_global' => false],
            ['code' => 'teacher', 'name' => 'Teacher', 'is_global' => false],
        ];

        $allPermissions = Permission::all();

        foreach ($roles as $roleData) {
            $role = Role::updateOrCreate(['code' => $roleData['code']], $roleData + ['is_system' => true, 'status' => 1]);
            $role->permissions()->sync($allPermissions->modelKeys());
        }
    }
}
