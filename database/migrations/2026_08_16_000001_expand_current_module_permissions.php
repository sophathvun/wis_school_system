<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function permissions(): array
    {
        return [
            ['users', 'view', 'View Users'], ['users', 'create', 'Create Users'], ['users', 'update', 'Update Users'], ['users', 'delete', 'Delete Users'], ['users', 'status', 'Activate / Deactivate Users'], ['users', 'permissions', 'Assign User Permissions'], ['users', 'campuses', 'Assign User Campuses'],
            ['departments', 'view', 'View Departments'], ['departments', 'create', 'Create Departments'], ['departments', 'update', 'Update Departments'], ['departments', 'delete', 'Delete Departments'], ['departments', 'status', 'Activate / Deactivate Departments'], ['departments', 'permissions', 'Assign Department Permissions'],
            ['positions', 'view', 'View Positions'], ['positions', 'create', 'Create Positions'], ['positions', 'update', 'Update Positions'], ['positions', 'delete', 'Delete Positions'], ['positions', 'status', 'Activate / Deactivate Positions'],
            ['roles', 'view', 'View Roles'], ['roles', 'create', 'Create Roles'], ['roles', 'update', 'Update Roles'], ['roles', 'delete', 'Delete Roles'], ['roles', 'status', 'Activate / Deactivate Roles'], ['roles', 'permissions', 'Assign Role Permissions'],
            ['notifications', 'view', 'View Notifications'], ['notifications', 'create', 'Create Notifications'], ['notifications', 'update', 'Update Notifications'], ['notifications', 'delete', 'Delete Notifications'], ['notifications', 'send', 'Send Notifications'],
            ['chat', 'view', 'View Chat'], ['chat', 'send', 'Send Messages'], ['chat', 'attach', 'Attach Files and Photos'], ['chat', 'voice', 'Send Voice Messages'],
            ['academic-years', 'view', 'View Academic Years'], ['academic-years', 'create', 'Create Academic Years'], ['academic-years', 'update', 'Update Academic Years'], ['academic-years', 'delete', 'Delete Academic Years'], ['academic-years', 'status', 'Start / Finish Academic Years'],
            ['grades', 'view', 'View Grades'], ['grades', 'create', 'Create Grades'], ['grades', 'update', 'Update Grades'], ['grades', 'delete', 'Delete Grades'], ['grades', 'status', 'Activate / Deactivate Grades'],
            ['classes', 'view', 'View Classes'], ['classes', 'create', 'Create Classes'], ['classes', 'update', 'Update Classes'], ['classes', 'delete', 'Delete Classes'], ['classes', 'status', 'Activate / Deactivate Classes'],
            ['sessions', 'view', 'View Groups'], ['sessions', 'create', 'Create Groups'], ['sessions', 'update', 'Update Groups'], ['sessions', 'delete', 'Delete Groups'], ['sessions', 'status', 'Activate / Deactivate Groups'],
            ['education-levels', 'view', 'View Education Levels'], ['education-levels', 'create', 'Create Education Levels'], ['education-levels', 'update', 'Update Education Levels'], ['education-levels', 'delete', 'Delete Education Levels'], ['education-levels', 'status', 'Activate / Deactivate Education Levels'],
            ['programs', 'view', 'View Programs'], ['programs', 'create', 'Create Programs'], ['programs', 'update', 'Update Programs'], ['programs', 'delete', 'Delete Programs'], ['programs', 'status', 'Activate / Deactivate Programs'],
            ['school-info', 'view', 'View School Information'], ['school-info', 'create', 'Create School Information'], ['school-info', 'update', 'Update School Information'], ['school-info', 'delete', 'Delete School Information'], ['school-info', 'status', 'Activate / Deactivate School Information'],
            ['locations', 'view', 'View Locations'], ['locations', 'create', 'Create Locations'], ['locations', 'update', 'Update Locations'], ['locations', 'delete', 'Delete Locations'], ['locations', 'status', 'Activate / Deactivate Locations'], ['locations', 'import', 'Import Locations'],
            ['occupations', 'view', 'View Occupations'], ['occupations', 'create', 'Create Occupations'], ['occupations', 'update', 'Update Occupations'], ['occupations', 'delete', 'Delete Occupations'], ['occupations', 'status', 'Activate / Deactivate Occupations'],
            ['academic-tracks', 'view', 'View Academic Tracks'], ['academic-tracks', 'create', 'Create Academic Tracks'], ['academic-tracks', 'update', 'Update Academic Tracks'], ['academic-tracks', 'delete', 'Delete Academic Tracks'], ['academic-tracks', 'status', 'Activate / Deactivate Academic Tracks'],
            ['withdrawal-reasons', 'view', 'View Withdrawal Reasons'], ['withdrawal-reasons', 'create', 'Create Withdrawal Reasons'], ['withdrawal-reasons', 'update', 'Update Withdrawal Reasons'], ['withdrawal-reasons', 'delete', 'Delete Withdrawal Reasons'], ['withdrawal-reasons', 'status', 'Activate / Deactivate Withdrawal Reasons'],
            ['student-document-types', 'view', 'View Document Types'], ['student-document-types', 'create', 'Create Document Types'], ['student-document-types', 'update', 'Update Document Types'], ['student-document-types', 'delete', 'Delete Document Types'], ['student-document-types', 'status', 'Activate / Deactivate Document Types'],
            ['branding', 'view', 'View Branding'], ['branding', 'update', 'Update Branding'],
            ['database-backups', 'view', 'View Database Backups'], ['database-backups', 'create', 'Create Database Backups'], ['database-backups', 'download', 'Download Database Backups'], ['database-backups', 'restore', 'Restore Database Backups'], ['database-backups', 'delete', 'Delete Database Backups'],
            ['students.search', 'view', 'View Student Search'], ['students.search', 'export', 'Export Student Search'],
            ['students.enrollment', 'view', 'View Student Enrollment'], ['students.enrollment', 'create', 'Create Student Enrollment'], ['students.enrollment', 'update', 'Update Student Enrollment'], ['students.enrollment', 'delete', 'Delete Student Enrollment'], ['students.enrollment', 'status', 'Change Enrollment Status'], ['students.enrollment', 'export', 'Export Enrollment'],
            ['families', 'view', 'View Family Management'], ['families', 'create', 'Create Families'], ['families', 'update', 'Update Families'], ['families', 'delete', 'Delete Families'], ['families', 'status', 'Change Family Status'],
            ['students.promotion', 'view', 'View Promotion / Transfer'], ['students.promotion', 'execute', 'Promote or Transfer Students'], ['students.promotion', 'cancel', 'Cancel Promotion or Transfer'], ['students.promotion', 'export', 'Export Promotion History'],
            ['students.graduation', 'view', 'View Graduation'], ['students.graduation', 'execute', 'Graduate Students'], ['students.graduation', 'cancel', 'Cancel Graduation'], ['students.graduation', 'export', 'Export Graduation Records'],
            ['student-reentry', 'view', 'View Student Re-entry'], ['student-reentry', 'create', 'Create Student Re-entry'], ['student-reentry', 'update', 'Update Student Re-entry'], ['student-reentry', 'cancel', 'Cancel Student Re-entry'],
            ['student-documents', 'view', 'View Student Documents'], ['student-documents', 'create', 'Upload Student Documents'], ['student-documents', 'update', 'Update Student Documents'], ['student-documents', 'delete', 'Delete Student Documents'], ['student-documents', 'download', 'Download Student Documents'],
            ['student-data-transfer', 'view', 'View Import / Export Data'], ['student-data-transfer', 'import', 'Import Student Data'], ['student-data-transfer', 'export', 'Export Student Data'],
        ];
    }

    public function up(): void
    {
        foreach ($this->permissions() as [$module, $action, $name]) {
            DB::table('access_permissions')->updateOrInsert(
                ['code' => "{$module}.{$action}"],
                ['module' => $module, 'action' => $action, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('access_permissions')->whereIn('code', collect($this->permissions())->map(fn ($item) => "{$item[0]}.{$item[1]}")->all())->delete();
    }
};
