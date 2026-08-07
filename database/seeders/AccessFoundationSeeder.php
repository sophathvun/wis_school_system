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
            ['code' => 'students.view', 'module' => 'students', 'action' => 'view', 'name' => 'View students'],
            ['code' => 'students.manage', 'module' => 'students', 'action' => 'manage', 'name' => 'Manage students'],
            ['code' => 'settings.manage', 'module' => 'settings', 'action' => 'manage', 'name' => 'Manage settings'],
            ['code' => 'reports.export', 'module' => 'reports', 'action' => 'export', 'name' => 'Export reports'],
            ['code' => 'administrator.view', 'module' => 'administrator', 'action' => 'view', 'name' => 'View Administrator menu'],
            ['code' => 'communication.view', 'module' => 'communication', 'action' => 'view', 'name' => 'View Communication menu'],
            ['code' => 'database-backups.view', 'module' => 'database-backups', 'action' => 'view', 'name' => 'View database backups'],
            ['code' => 'database-backups.create', 'module' => 'database-backups', 'action' => 'create', 'name' => 'Create database backups'],
            ['code' => 'database-backups.download', 'module' => 'database-backups', 'action' => 'download', 'name' => 'Download database backups'],
            ['code' => 'database-backups.delete', 'module' => 'database-backups', 'action' => 'delete', 'name' => 'Delete database backups'],
        ];

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
