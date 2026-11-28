<?php

namespace App\Support;

use App\Models\Permission;
use Illuminate\Support\Collection;

class PermissionHierarchy
{
    public static function moduleLabels(): array
    {
        $gepLabels=[];foreach(GepSettingsPermissions::SECTIONS as $key=>$section)$gepLabels['gep-settings.'.$key]=$section['label'];
        foreach(GepStudentPermissions::SECTIONS as $key=>$section)$gepLabels['gep-students.'.$key]=$section['label'];
        foreach(GepReportPermissions::SECTIONS as $key=>$section)$gepLabels['gep-reports.'.$key]=$section['label'];
        $nationalLabels=[];foreach(G12NationalExamPermissions::submenus() as $key=>$section)$nationalLabels['g12-national-exams.'.$key]=$section[0];
        foreach(G9NationalExamPermissions::sections() as $key=>$section)$nationalLabels['g9-national-exams.'.$key]=$section[0];
        return array_merge($gepLabels, [
            'program-workspaces.wis'=>'WIS Workspace', 'program-workspaces.gep'=>'GEP Workspace',
            'users' => 'Users', 'departments' => 'Departments', 'positions' => 'Positions', 'roles' => 'Roles', 'notifications' => 'Notifications', 'chat' => 'Chat',
            'academic-years' => 'Academic Years', 'grades' => 'Grades', 'classes' => 'Classes', 'sessions' => 'Sessions',
            'education-levels' => 'Education Levels', 'programs' => 'Programs', 'school-info' => 'School Information',
            'locations' => 'Locations', 'occupations' => 'Occupations', 'nationalities' => 'Nationalities', 'academic-tracks' => 'Academic Tracks', 'withdrawal-reasons' => 'Withdrawal Reasons',
            'student-document-types' => 'Document Types', 'dashboard-templates' => 'Dashboard Templates', 'branding' => 'Branding', 'database-backups' => 'Database Backups',
            'groups' => 'School Groups', 'terms' => 'Terms', 'campuses' => 'Campuses', 'summer-school' => 'Summer School', 'student-withdrawals' => 'Student Withdrawals',
            'attendance' => 'Attendance', 'schedules' => 'Schedules', 'grading-system' => 'Grading System', 'reports' => 'Reports', 'g9-national-exams'=>'G9 National Exams',
            'students.search' => 'Search Students', 'students.enrollment' => 'Student Enrollment', 'student-photo-import' => 'Bulk Upload Stu. Photos', 'families' => 'Family Management',
            'students.promotion' => 'Student Promotion', 'students.transfer' => 'Student Transfer', 'students.graduation' => 'Student Graduation',
            'student-reentry' => 'Student Re-entry', 'student-skipping-grade' => 'Stu. Skipping Grade', 'student-id-card-qr' => 'Stu. ID Card (QR)', 'student-documents' => 'Student Documents', 'student-data-transfer' => 'Import / Export Data', 'homeroom' => 'Homeroom Activities',
            'western-scholarship' => 'WIS Scholarship', 'placement-tests'=>'Student Placement Test', 'g12-national-exams'=>'G12 National Exams', 'photo-editor' => 'Photo Editor',
            'staff' => 'Staff Management', 'hrm-settings' => 'HRM Settings', 'hrm-staff-data'=>'Staff Import / Export',
            'hrm-staff-attendance' => 'Staff Attendance', 'hrm-payroll' => 'Payroll', 'hrm-leave-requests' => 'Leave Requests',
            'hrm-performance-reviews' => 'Performance Reviews', 'hrm-staff-reports' => 'Staff Reports',
            ...AdditionalMenuPermissions::MODULES,
        ], $nationalLabels, collect(\App\Http\Controllers\WmiManagementController::SECTIONS)->mapWithKeys(fn($section,$key)=>['wmi-management.'.$key=>$section['label']])->all(), AcademicReportPermissions::moduleLabels(), collect(AttendancePermissions::sections())->mapWithKeys(fn($section,$key)=>['attendance.'.$key=>$section[0]])->all());
    }

    public static function groups(): array
    {
        $nationalModules=[];$nationalActions=[];
        foreach(G12NationalExamPermissions::submenus() as $key=>$section){$module='g12-national-exams.'.$key;$nationalModules[]=$module;$nationalActions[$module]=array_map(fn($action)=>'g12-national-exams.'.$action,$section[1]);}
        return [
            'program-workspaces'=>[
                'label'=>'Program Workspaces','permission'=>'program-workspaces.view',
                'description'=>'Choose WIS, GEP, or both. Program access is required in addition to module and campus permissions.',
                'modules'=>['program-workspaces.wis','program-workspaces.gep'],'actions'=>[],
            ],
            'gep-settings'=>[
                'label'=>'GEP Settings','permission'=>'gep-settings.view',
                'description'=>'GEP workspace access is also required. These settings belong to the General English Program.',
                'modules'=>array_map(fn($key)=>'gep-settings.'.$key,array_keys(GepSettingsPermissions::SECTIONS)),
                'actions'=>[],
            ],
            'gep-students'=>[
                'label'=>'GEP Students','permission'=>'gep-students.view','modules'=>array_map(fn($key)=>'gep-students.'.$key,array_keys(GepStudentPermissions::SECTIONS)),'actions'=>[],
                'description'=>'Access GEP student modules. GEP workspace access is also required. GEP Stu. Promotion requires View and Promote permissions plus enrollment View access for the same campus. Campus staff can promote only students within their permitted campus; promotion does not transfer students to another campus.',
            ],
            ...collect(GepAcademicPermissions::MENUS)->mapWithKeys(fn($menu,$key)=>['gep-'.$key=>[
                'label'=>$menu['label'],'permission'=>'gep-'.$key.'.view','modules'=>[],'actions'=>[],
                'description'=>'GEP workspace access is also required.',
            ]])->all(),
            'gep-reports'=>[
                'label'=>'GEP Reports','permission'=>'gep-reports.view',
                'modules'=>array_map(fn($key)=>'gep-reports.'.$key,array_keys(GepReportPermissions::SECTIONS)),'actions'=>[],
                'description'=>'Access GEP reports. GEP workspace access is also required.',
            ],
            'wmi-management'=>[
                'label'=>'WMI Management','permission'=>'wmi-management.view',
                'description'=>'Authorized campus users can register Western and other-school students for any campus and view all WMI candidates. Campus Study and Campus Registered do not restrict WMI access. Enable each submenu and tab before its functions.',
                'modules'=>array_map(fn($key)=>'wmi-management.'.$key,array_keys(\App\Http\Controllers\WmiManagementController::SECTIONS)),
                'module_permissions'=>collect(\App\Http\Controllers\WmiManagementController::SECTIONS)->mapWithKeys(fn($section,$key)=>['wmi-management.'.$key=>'wmi-management.'.$key])->all(),
                'module_actions'=>collect(\App\Http\Controllers\WmiManagementController::SECTIONS)->mapWithKeys(fn($section,$key)=>['wmi-management.'.$key=>array_values(array_filter(array_keys(WmiPermissions::catalog()),fn($code)=>str_starts_with($code,'wmi-management.'.$key.'.')))])->all(),
                'actions'=>[],
            ],
            'photo-editor' => [
                'label' => 'Photo Editor', 'permission' => 'photo-editor.view',
                'description' => 'Allow photo upload, crop and rotation, background replacement, color and clarity adjustments, photo sizes, and JPG / WebP downloads. Applies to assigned users independently of campus selection.',
                'modules' => [], 'actions' => [],
            ],
            'administrator' => [
                'label' => 'Administrator', 'permission' => 'administrator.view',
                'modules' => ['users', 'roles', 'dashboard-templates', 'branding', 'database-backups', 'campuses'],
                'actions' => [],
            ],
            'communication' => [
                'label' => 'Communication', 'permission' => 'communication.view',
                'modules' => ['chat', 'notifications'],
                'actions' => [],
            ],
            'registrar-doc' => [
                'label' => 'Registrar DOC', 'permission' => 'registrar-doc.view',
                'description' => 'Show the Registrar DOC main menu linking to the registrar document sharing site.',
                'modules' => [], 'actions' => [],
            ],
            'settings' => [
                'label' => 'Settings', 'permission' => 'settings.view',
                'modules' => ['academic-years', 'grades', 'classes', 'sessions', 'education-levels', 'programs', 'groups', 'terms', 'school-info', 'locations', 'occupations', 'nationalities', 'academic-tracks', 'withdrawal-reasons', 'student-document-types'],
                'actions' => ['settings.manage'],
            ],
            'pr-request' => [
                'label' => 'PR Request', 'permission' => 'pr-request.view',
                'modules' => [],
                'actions' => [],
            ],
            'students' => [
                'label' => 'Students', 'permission' => 'students.view',
                'modules' => ['students.search', 'students.enrollment', 'placement-tests', 'summer-school', 'families', 'students.promotion', 'students.transfer', 'students.graduation', 'student-withdrawals', 'student-reentry', 'student-skipping-grade', 'western-scholarship', 'student-id-card-qr', 'student-documents', 'homeroom', 'student-data-transfer', 'student-photo-import'],
                'module_permissions' => ['student-photo-import'=>'students.enrollment.bulk-photos'],
                'actions' => ['students.manage'],
            ],
            'dashboard' => [
                'label' => 'Dashboard', 'permission' => 'dashboard.view',
                'modules' => [],
                'actions' => ['dashboard.customize', 'dashboard.reset'],
            ],
            'attendance' => [
                'label' => 'Attendance', 'permission' => 'attendance.view',
                'description' => 'Changes are limited to assigned campuses. Permitted reports can view students in all campuses. Central users require the same action permissions.',
                'modules' => array_map(fn($key)=>'attendance.'.$key,array_keys(AttendancePermissions::sections())),
                'module_permissions' => collect(AttendancePermissions::sections())->mapWithKeys(fn($section,$key)=>['attendance.'.$key=>'attendance.'.$key])->all(),
                'module_actions' => collect(AttendancePermissions::sections())->mapWithKeys(fn($section,$key)=>['attendance.'.$key=>array_map(fn($action)=>'attendance.'.$action,$section[1])])->all(),
                'actions' => [],
            ],
            'schedules' => [
                'label' => 'Schedules', 'permission' => 'schedules.view',
                'modules' => [],
                'actions' => ['schedules.manage','schedules.setup'],
            ],
            'grading-system' => [
                'label' => 'Grading System', 'permission' => 'grading-system.view',
                'modules' => [],
                'actions' => ['grading-system.manage'],
            ],
            'g12-national-exams'=>[
                'label'=>'G12 National Exams','permission'=>'g12-national-exams.view','modules'=>$nationalModules,
                'module_permissions'=>array_combine($nationalModules,$nationalModules),'module_actions'=>$nationalActions,'actions'=>[],
            ],
            'g9-national-exams'=>[
                'label'=>'G9 National Exams','permission'=>'g9-national-exams.view',
                'description'=>'Candidate lists and permitted reports cover all campuses. Score and candidate changes are limited to assigned campuses; central users can manage all permitted campuses. Shared settings require central access.',
                'modules'=>array_map(fn($key)=>'g9-national-exams.'.$key,array_keys(G9NationalExamPermissions::sections())),
                'module_permissions'=>collect(G9NationalExamPermissions::sections())->mapWithKeys(fn($section,$key)=>['g9-national-exams.'.$key=>'g9-national-exams.'.$key])->all(),
                'module_actions'=>collect(G9NationalExamPermissions::sections())->mapWithKeys(fn($section,$key)=>['g9-national-exams.'.$key=>array_map(fn($action)=>'g9-national-exams.'.$action,$section[1])])->all(),
                'actions'=>[],
            ],
            'hrm' => [
                'label' => 'HRM', 'permission' => 'hrm.view',
                'modules' => ['staff', 'hrm-settings', 'departments', 'positions', 'hrm-staff-data', 'hrm-staff-attendance', 'hrm-payroll', 'hrm-leave-requests', 'hrm-performance-reviews', 'hrm-staff-reports'],
                'actions' => [],
            ],
            'student-discipline' => [
                'label' => 'Student Discipline', 'permission' => 'student-discipline.view',
                'modules' => [], 'actions' => [],
            ],
            'financial-management' => [
                'label' => 'Financial Management', 'permission' => 'financial-management.view',
                'modules' => ['financial-management.school-fee', 'financial-management.invoices', 'financial-management.payments', 'financial-management.expenses'],
                'actions' => [],
            ],
            'reports' => [
                'label' => 'Reports', 'permission' => 'reports.view',
                'modules_label' => 'Report Types',
                'modules' => [...array_keys(AcademicReportPermissions::moduleLabels()), 'reports.hrm', 'reports.financial'],
                'actions' => ['reports.export'],
            ],
        ];
    }

    public static function tree(Collection $permissions): array
    {
        $byCode = $permissions->keyBy('code');
        $tree = [];

        foreach (self::groups() as $key => $group) {
            $modules = [];
            foreach ($group['modules'] as $module) {
                $viewCode = $group['module_permissions'][$module]??"{$module}.view";
                $items = isset($group['module_actions'][$module])?$permissions->whereIn('code',$group['module_actions'][$module])->values():$permissions->filter(fn (Permission $permission) => str_starts_with($permission->code, "{$module}.") && $permission->code !== $viewCode
                    && !($module === 'students.enrollment' && in_array($permission->action, ['manage', 'status', 'bulk-photos'], true))
                    && !(in_array($module, ['summer-school','families'], true) && $permission->action === 'manage')
                    && !($module==='western-scholarship' && $permission->action==='export'))->values();
                if ($byCode->has($viewCode)) {
                    $modules[$module] = ['label' => self::moduleLabels()[$module] ?? ucwords(str_replace(['.', '-', '_'], ' ', $module)), 'permission' => $byCode->get($viewCode), 'actions' => $items];
                    if($module==='western-scholarship'){
                        $modules[$module]['tabs']=collect(ScholarshipPermissions::tabGroups())->map(function($group,$tab)use($byCode){
                            return ['label'=>$group['label'],'permission'=>$byCode->get('western-scholarship.'.ScholarshipPermissions::TABS[$tab]),
                                'actions'=>collect($group['actions'])->map(fn($action)=>$byCode->get('western-scholarship.'.$action))->filter()->values()];
                        })->all();
                    }
                    if(str_starts_with($module,'wmi-management.')){
                        $section=substr($module,strlen('wmi-management.'));
                        $tabs=\App\Http\Controllers\WmiManagementController::SECTIONS[$section]['tabs'];
                        if($tabs)$modules[$module]['tabs']=collect($tabs)->map(function($tab,$key)use($module,$byCode){
                            $code=$module.'.'.$key;
                            return ['label'=>$tab[0],'permission'=>$byCode->get($code),'actions'=>collect(WmiPermissions::functions()[substr($code,strlen('wmi-management.'))]??[])->keys()->map(fn($action)=>$byCode->get($code.'.'.$action))->filter()->values()];
                        })->all();
                    }
                }
            }
            $actions = collect($group['actions'])->map(fn ($code) => $byCode->get($code))->filter()->values();
            $tree[$key] = ['label' => $group['label'], 'description' => $group['description'] ?? null, 'permission' => $byCode->get($group['permission']), 'modules' => $modules, 'modules_label' => $group['modules_label'] ?? null, 'actions' => $actions];
        }

        return $tree;
    }

    public static function visibleIds(Collection $permissions): Collection
    {
        $byCode = $permissions->keyBy('code');
        $ids = collect();

        foreach (self::groups() as $group) {
            if ($permission = $byCode->get($group['permission'])) {
                $ids->push($permission->id);
            }

            foreach ($group['actions'] as $actionCode) {
                if ($permission = $byCode->get($actionCode)) {
                    $ids->push($permission->id);
                }
            }

            foreach ($group['modules'] as $module) {
                $viewCode = $group['module_permissions'][$module]??"{$module}.view";
                if ($permission = $byCode->get($viewCode)) {
                    $ids->push($permission->id);
                }

                if(isset($group['module_actions'][$module])){
                    $permissions->whereIn('code',$group['module_actions'][$module])->each(fn(Permission $p)=>$ids->push($p->id));
                    continue;
                }
                $permissions
                    ->filter(fn (Permission $permission) => str_starts_with($permission->code, "{$module}.") && $permission->code !== $viewCode
                        && !($module === 'students.enrollment' && in_array($permission->action, ['manage', 'status', 'bulk-photos'], true))
                        && !(in_array($module, ['summer-school','families'], true) && $permission->action === 'manage')
                        && !($module==='western-scholarship' && $permission->action==='export'))
                    ->each(fn (Permission $permission) => $ids->push($permission->id));
            }
        }

        return $ids->unique()->values();
    }

    public static function normalizeIds(array $ids, Collection $permissions): array
    {
        $byId = $permissions->keyBy('id');
        $selected = collect($ids)->map(fn ($id) => (int) $id)->filter(fn ($id) => $byId->has($id))->values();
        $codes = $selected->map(fn ($id) => $byId->get($id)->code)->flip();

        foreach (self::groups() as $group) {
            $mainOn = $codes->has($group['permission']);
            if (!$mainOn) {
                foreach ($group['actions'] as $action) $codes->forget($action);
            }
            foreach ($group['modules'] as $module) {
                $submenuCode = $group['module_permissions'][$module]??"{$module}.view";
                $submenuOn = $mainOn && $codes->has($submenuCode);
                if (!$mainOn) $codes->forget($group['permission']);
                if (!$submenuOn) {
                    $codes->forget($submenuCode);
                    foreach($group['module_actions'][$module]??[] as $action)$codes->forget($action);
                    foreach ($codes->keys() as $code) {
                        if (str_starts_with($code, "{$module}.")) $codes->forget($code);
                    }
                }
            }
        }

        foreach (StudentSkippingGradePermissions::PARENTS as $action=>$parent) {
            if (!$codes->has(StudentSkippingGradePermissions::MODULE.'.'.$parent)) $codes->forget(StudentSkippingGradePermissions::MODULE.'.'.$action);
        }
        foreach(WmiPermissions::parents() as $code=>$parent)if(!$codes->has($parent))$codes->forget($code);
        foreach (ScholarshipPermissions::PARENTS as $action=>$parent) {
            if (!$codes->has('western-scholarship.'.$parent)) $codes->forget('western-scholarship.'.$action);
        }
        foreach(G12NationalExamPermissions::PARENTS as $action=>$parent){
            if(!$codes->has('g12-national-exams.'.$parent))$codes->forget('g12-national-exams.'.$action);
        }
        foreach(AttendancePermissions::PARENTS as $action=>$parent) {
            if (!$codes->has('attendance.'.$parent)) $codes->forget('attendance.'.$action);
        }
        if(!$codes->has('western-scholarship.answer-key'))$codes->forget('western-scholarship.answer-key-scan');
        if(!$codes->has('western-scholarship.answer-verify'))$codes->forget('western-scholarship.answer-accept-all');

        if (!$codes->has('hrm-settings.view')) {
            foreach ($codes->keys() as $code) {
                if (str_starts_with($code,'departments.') || str_starts_with($code,'positions.') || str_starts_with($code,'hrm-staff-data.')) $codes->forget($code);
            }
        }
        if (!$codes->has('staff.view')) {
            foreach($codes->keys() as $code) if (str_starts_with($code,'hrm-staff-data.')) $codes->forget($code);
        }
        if (!$codes->has('staff.create')) $codes->forget('hrm-staff-data.import');

        return $codes->keys()->map(fn ($code) => $byId->firstWhere('code', $code)->id)->values()->all();
    }
}
