<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Department;
use App\Models\SchoolInfo;
use App\Models\BrandingSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\PermissionHierarchy;

class AccessManagementController
{
    public function index(Request $request)
    {
        $role = Role::with('permissions')->find($request->integer('role_id')) ?? Role::with('permissions')->orderBy('name')->first();
        $departments = DB::table('access_departments')->where('status', 1)->orderBy('name')->get();
        $department = Department::with('permissions')->find($request->integer('department_id')) ?? Department::where('status', 1)->orderBy('name')->first();
        $selectedUser = User::with('permissionOverrides')->find($request->integer('user_id')) ?? User::where('status', 1)->orderBy('name')->first();
        $permissionList = Permission::orderBy('module')->orderBy('action')->get();
        $permissionSearch = trim((string) $request->input('permission_search', ''));
        $permissionSortBy = in_array($request->query('permissionSortBy', 'module'), ['module', 'code', 'name', 'action'], true)
            ? $request->query('permissionSortBy', 'module')
            : 'module';
        $permissionSortDir = strtolower($request->query('permissionSortDir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $permissionRows = Permission::withCount(['roles', 'departments', 'users'])
            ->when($permissionSearch, fn ($query) => $query->where(function ($innerQuery) use ($permissionSearch) {
                $innerQuery->where('module', 'like', "%{$permissionSearch}%")
                    ->orWhere('code', 'like', "%{$permissionSearch}%")
                    ->orWhere('name', 'like', "%{$permissionSearch}%")
                    ->orWhere('action', 'like', "%{$permissionSearch}%");
            }))
            ->orderBy($permissionSortBy, $permissionSortDir)
            ->orderBy('module')
            ->orderBy('action')
            ->paginate(10, ['*'], 'permission_page')
            ->withQueryString();
        $hasAllPermissions = fn ($assigned) => $permissionList->isNotEmpty() && $permissionList->every(fn ($permission) => $assigned?->contains('id', $permission->id));
        $userSearch = trim((string) $request->input('user_search', ''));
        $userPerPage = min(max($request->integer('per_page', 10), 10), 100);
        $userList = User::with(['department', 'roles', 'campuses', 'permissionOverrides'])
            ->when($userSearch, fn ($query) => $query->where(function ($query) use ($userSearch) {
                $query->where('name', 'like', "%{$userSearch}%")
                    ->orWhere('username', 'like', "%{$userSearch}%")
                    ->orWhere('email', 'like', "%{$userSearch}%");
            }))
            ->orderBy('name')
            ->paginate($userPerPage, ['*'], 'users_page')
            ->withQueryString();

        return view('access-management', [
            'departments' => $departments,
            'campuses' => SchoolInfo::where('status', 1)->orderBy('campus_name_en')->get(),
            'roles' => Role::orderBy('name')->get(),
            'users' => User::where('status', 1)->orderBy('name')->get(['id', 'name', 'username', 'email']),
            'permissions' => $permissionList,
            'permissionRows' => $permissionRows,
            'permissionSearch' => $permissionSearch,
            'permissionSortBy' => $permissionSortBy,
            'permissionSortDir' => $permissionSortDir,
            'permissionHierarchy' => PermissionHierarchy::tree($permissionList),
            'userList' => $userList,
            'userSearch' => $userSearch,
            'roleFullAccess' => $role?->code === 'super-admin' || $hasAllPermissions($role?->permissions),
            'departmentFullAccess' => $hasAllPermissions($department?->permissions),
            'userFullAccess' => $selectedUser?->isSuperAdmin() || $hasAllPermissions($selectedUser?->permissionOverrides),
            'role' => $role,
            'department' => $department,
            'selectedUser' => $selectedUser,
        ]);
    }

    public function createRole(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'alpha_dash', 'max:80', 'unique:access_roles,code'], 'name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:255'], 'department_id' => ['nullable', 'exists:access_departments,id'], 'is_global' => ['nullable', 'boolean']]);
        Role::create($data + ['is_global' => $request->boolean('is_global'), 'is_system' => false, 'status' => 1]);
        return back()->with('success', 'Role created successfully.');
    }

    public function saveDepartment(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'code' => ['nullable', 'alpha_dash', 'max:80', 'unique:access_departments,code']]);
        Department::create(['name' => $data['name'], 'code' => $data['code'] ?: Str::slug($data['name']), 'status' => 1]);
        return back()->with('success', 'Department created successfully.');
    }

    public function savePermission(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'regex:/^[A-Za-z0-9._-]+$/', 'max:120', 'unique:access_permissions,code'], 'module' => ['required', 'string', 'max:80'], 'action' => ['required', 'string', 'max:80'], 'name' => ['required', 'string', 'max:120']]);
        Permission::create($data);
        return back()->with('success', 'Permission created successfully.');
    }

    public function deletePermission(Request $request, Permission $permission)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        if ($permission->roles()->exists() || $permission->departments()->exists() || $permission->users()->exists()) {
            return back()->withErrors(['permission' => 'This permission cannot be deleted because it is linked to roles, departments, or users.']);
        }

        $permission->delete();

        return back()->with('success', 'Permission deleted successfully.');
    }

    public function printReport(Request $request, string $type)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return view('access-permissions-report', $this->permissionReportData($type) + [
            'logoSrc' => $this->permissionReportLogoSrc(),
            'printMode' => true,
        ]);
    }

    public function exportReportExcel(Request $request, string $type)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $report = $this->permissionReportData($type);
        $filename = Str::slug($report['titleEn']) . '-' . now('Asia/Phnom_Penh')->format('Ymd-His') . '.xlsx';
        $path = $this->permissionReportExcelPath($report);

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public function saveDepartmentPermissions(Request $request)
    {
        $data = $request->validate(['department_id' => ['required', 'exists:access_departments,id'], 'permissions' => ['array'], 'permissions.*' => ['integer', 'exists:access_permissions,id']]);
        Department::findOrFail($data['department_id'])->permissions()->sync(PermissionHierarchy::normalizeIds($data['permissions'] ?? [], Permission::all()));
        return back()->with('success', 'Department permissions saved successfully.');
    }

    public function saveRole(Request $request)
    {
        $data = $request->validate(['role_id' => ['required', 'exists:access_roles,id'], 'department_id' => ['nullable', 'exists:access_departments,id'], 'permissions' => ['array'], 'permissions.*' => ['integer', 'exists:access_permissions,id']]);
        $role = Role::findOrFail($data['role_id']);
        $role->update(['department_id' => $data['department_id'] ?? null]);
        $role->permissions()->sync(PermissionHierarchy::normalizeIds($data['permissions'] ?? [], Permission::all()));
        return back()->with('success', 'Role permissions saved successfully.');
    }

    public function saveStaff(Request $request)
    {
        $data = $request->validate(['user_id' => ['required', 'exists:users,id'], 'permissions' => ['array'], 'permissions.*' => ['integer', 'exists:access_permissions,id'], 'campuses' => ['array'], 'campuses.*' => ['integer', 'exists:tb_school_info,id']]);
        DB::transaction(function () use ($data) {
            $user = User::findOrFail($data['user_id']);
            if (!$user->is_global) {
                $user->campuses()->sync(collect($data['campuses'] ?? [])->mapWithKeys(fn ($id, $index) => [$id => ['is_primary' => $index === 0, 'assigned_at' => now()]])->all());
            }
            DB::table('access_user_permission_overrides')->where('user_id', $data['user_id'])->delete();
            foreach (PermissionHierarchy::normalizeIds($data['permissions'] ?? [], Permission::all()) as $permissionId) DB::table('access_user_permission_overrides')->insert(['user_id' => $data['user_id'], 'permission_id' => $permissionId, 'allowed' => true, 'created_at' => now(), 'updated_at' => now()]);
        });
        return back()->with('success', 'Staff permission overrides saved successfully.');
    }

    private function permissionReportData(string $type): array
    {
        return match ($type) {
            'departments' => $this->permissionByDepartmentReport(),
            'roles' => $this->permissionByRoleReport(),
            'users' => $this->permissionByUserReport(),
            default => $this->permissionListReport(),
        };
    }

    private function permissionListReport(): array
    {
        $permissions = Permission::orderBy('module')->orderBy('action')->orderBy('code')->get();

        return [
            'titleKh' => 'តារាងសិទ្ធិប្រើប្រាស់',
            'titleEn' => 'Permission List',
            'columns' => ['No.', 'Module', 'Permission Code', 'Permission Name', 'Action'],
            'rows' => $permissions->values()->map(fn ($permission, $index) => [
                $index + 1,
                $permission->module,
                $permission->code,
                $permission->name,
                $permission->action,
            ])->all(),
        ];
    }

    private function permissionByDepartmentReport(): array
    {
        $rows = [];
        Department::with('permissions')->orderBy('name')->get()->each(function (Department $department) use (&$rows) {
            $permissions = $department->permissions->sortBy([['module', 'asc'], ['action', 'asc'], ['code', 'asc']])->values();
            if ($permissions->isEmpty()) {
                $rows[] = [$department->name, $department->code, '-', '-', '-', '-'];
                return;
            }

            foreach ($permissions as $permission) {
                $rows[] = [$department->name, $department->code, $permission->module, $permission->code, $permission->name, $permission->action];
            }
        });

        return [
            'titleKh' => 'តារាងសិទ្ធិតាមផ្នែក',
            'titleEn' => 'Permission by Department',
            'columns' => ['Department', 'Department Code', 'Module', 'Permission Code', 'Permission Name', 'Action'],
            'rows' => $rows,
        ];
    }

    private function permissionByRoleReport(): array
    {
        $rows = [];
        Role::with(['department', 'permissions'])->orderBy('name')->get()->each(function (Role $role) use (&$rows) {
            $permissions = $role->permissions->sortBy([['module', 'asc'], ['action', 'asc'], ['code', 'asc']])->values();
            if ($permissions->isEmpty()) {
                $rows[] = [$role->name, $role->code, $role->department?->name ?: '-', '-', '-', '-', '-'];
                return;
            }

            foreach ($permissions as $permission) {
                $rows[] = [$role->name, $role->code, $role->department?->name ?: '-', $permission->module, $permission->code, $permission->name, $permission->action];
            }
        });

        return [
            'titleKh' => 'តារាងសិទ្ធិតាមតួនាទី',
            'titleEn' => 'Permission by Role',
            'columns' => ['Role', 'Role Code', 'Department', 'Module', 'Permission Code', 'Permission Name', 'Action'],
            'rows' => $rows,
        ];
    }

    private function permissionByUserReport(): array
    {
        $allPermissions = Permission::orderBy('module')->orderBy('action')->get();
        $rows = [];
        User::with(['department', 'roles', 'campuses', 'permissionOverrides'])->orderBy('name')->get()->each(function (User $user) use (&$rows, $allPermissions) {
            $permissions = $user->isSuperAdmin() ? $allPermissions : $user->permissionOverrides->where('pivot.allowed', true);
            if ($permissions->isEmpty()) {
                $rows[] = [$user->name, $user->username, $user->department?->name ?: '-', $user->roles->pluck('name')->unique()->join(', ') ?: '-', $this->campusLabel($user), '-', '-', '-'];
                return;
            }

            foreach ($permissions as $permission) {
                $rows[] = [$user->name, $user->username, $user->department?->name ?: '-', $user->roles->pluck('name')->unique()->join(', ') ?: '-', $this->campusLabel($user), $permission->code, $permission->name, $user->isSuperAdmin() ? 'Full Access' : 'Allowed'];
            }
        });

        return [
            'titleKh' => 'តារាងសិទ្ធិតាមអ្នកប្រើប្រាស់',
            'titleEn' => 'Permission by User',
            'columns' => ['Staff Name', 'Username', 'Department', 'Role', 'Campus', 'Permission Code', 'Permission Name', 'Access'],
            'rows' => $rows,
        ];
    }

    private function campusLabel(User $user): string
    {
        return $user->is_global ? 'All Campuses' : ($user->campuses->pluck('campus_name_en')->filter()->join(', ') ?: '-');
    }

    private function permissionReportLogoSrc(): ?string
    {
        $logoPath = $this->permissionReportLogoPath();

        return $logoPath
            ? 'data:' . (mime_content_type($logoPath) ?: 'image/png') . ';base64,' . base64_encode(file_get_contents($logoPath))
            : null;
    }

    private function permissionReportLogoPath(): ?string
    {
        $school = SchoolInfo::latest('id')->first();
        $branding = BrandingSetting::current();
        $logoPath = $branding?->report_logo_1_path
            ? storage_path('app/public/' . ltrim($branding->report_logo_1_path, '/'))
            : ($school?->logo_path
                ? storage_path('app/public/' . ltrim($school->logo_path, '/'))
                : storage_path('app/public/school_logo/wis_logo.png'));

        if (!is_file($logoPath)) {
            $logoPath = $school?->logo_path
                ? storage_path('app/public/' . ltrim($school->logo_path, '/'))
                : storage_path('app/public/school_logo/wis_logo.png');
        }

        return is_file($logoPath) ? $logoPath : null;
    }

    private function permissionReportExcelPath(array $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'permission-report-');
        $xlsxPath = $path . '.xlsx';
        rename($path, $xlsxPath);

        $logoPath = $this->permissionReportLogoPath();
        $logoExtension = $logoPath ? $this->xlsxImageExtension($logoPath) : null;
        $entries = [
            '[Content_Types].xml' => $this->xlsxContentTypes($logoExtension),
            '_rels/.rels' => $this->xlsxRootRels(),
            'xl/workbook.xml' => $this->xlsxWorkbook($report['titleEn']),
            'xl/_rels/workbook.xml.rels' => $this->xlsxWorkbookRels(),
            'xl/styles.xml' => $this->xlsxStyles(),
            'xl/worksheets/sheet1.xml' => $this->permissionWorksheetXml($report, (bool) $logoPath),
        ];

        if ($logoPath && $logoExtension) {
            $entries['xl/worksheets/_rels/sheet1.xml.rels'] = $this->xlsxWorksheetRels();
            $entries['xl/drawings/drawing1.xml'] = $this->xlsxLogoDrawing();
            $entries['xl/drawings/_rels/drawing1.xml.rels'] = $this->xlsxLogoDrawingRels($logoExtension);
            $entries['xl/media/report-logo.' . $logoExtension] = file_get_contents($logoPath);
        }

        $this->writeZipArchive($xlsxPath, $entries);

        return $xlsxPath;
    }

    private function permissionWorksheetXml(array $report, bool $hasLogo): string
    {
        $columns = $report['columns'];
        $rowsData = $report['rows'];
        $lastColumn = $this->xlsxColumnName(count($columns));
        $lastRow = count($rowsData) + 9;
        $rows = [
            $this->xlsxRow(1, [], 28),
            $this->xlsxRow(2, [], 28),
            $this->xlsxRow(3, [], 28),
            $this->xlsxRow(4, [], 10),
            $this->xlsxRow(5, [['A', $report['titleKh'], 1]], 30),
            $this->xlsxRow(6, [['A', $report['titleEn'], 2]], 26),
            $this->xlsxRow(7, [['A', 'Generated: ' . now('Asia/Phnom_Penh')->format('d-M-Y h:i A'), 6]], 20),
            $this->xlsxRow(8, [], 8),
            $this->xlsxRow(9, collect($columns)->map(fn ($column, $index) => [$this->xlsxColumnName($index + 1), $column, 3])->all(), 24),
        ];

        foreach ($rowsData as $index => $rowData) {
            $rowNumber = $index + 10;
            $rows[] = $this->xlsxRow($rowNumber, collect($rowData)->values()->map(fn ($value, $columnIndex) => [
                $this->xlsxColumnName($columnIndex + 1),
                (string) $value,
                5,
            ])->all(), 22);
        }

        $columnWidths = collect($columns)->map(fn ($column, $index) => '<col min="' . ($index + 1) . '" max="' . ($index + 1) . '" width="' . ($index === 0 ? 10 : 24) . '" customWidth="1"/>')->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="9" topLeftCell="A10" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<cols>' . $columnWidths . '</cols>'
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . '<mergeCells count="5"><mergeCell ref="A1:' . $lastColumn . '3"/><mergeCell ref="A5:' . $lastColumn . '5"/><mergeCell ref="A6:' . $lastColumn . '6"/><mergeCell ref="A7:' . $lastColumn . '7"/><mergeCell ref="A8:' . $lastColumn . '8"/></mergeCells>'
            . ($hasLogo ? '<drawing r:id="rId1"/>' : '')
            . '</worksheet>';
    }

    private function xlsxColumnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function xlsxRow(int $row, array $cells, ?int $height = null): string
    {
        $heightAttribute = $height ? ' ht="' . $height . '" customHeight="1"' : '';

        return '<row r="' . $row . '"' . $heightAttribute . '>' . collect($cells)->map(function ($cell) use ($row) {
            [$column, $value, $style] = $cell;

            return '<c r="' . $column . $row . '" s="' . $style . '" t="inlineStr"><is><t>' . $this->xlsxEscape($value) . '</t></is></c>';
        })->implode('') . '</row>';
    }

    private function xlsxEscape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function xlsxContentTypes(?string $logoExtension = null): string
    {
        $imageDefault = match ($logoExtension) {
            'jpg', 'jpeg' => '<Default Extension="jpeg" ContentType="image/jpeg"/><Default Extension="jpg" ContentType="image/jpeg"/>',
            'gif' => '<Default Extension="gif" ContentType="image/gif"/>',
            default => '<Default Extension="png" ContentType="image/png"/>',
        };

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . ($logoExtension ? $imageDefault : '')
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . ($logoExtension ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '')
            . '</Types>';
    }

    private function xlsxRootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function xlsxWorkbook(string $title): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $this->xlsxEscape(substr($title, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function xlsxWorkbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function xlsxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="4"><font><sz val="11"/><name val="Arial"/></font><font><sz val="20"/><name val="Khmer OS Muol Light"/><color rgb="FF4F6380"/></font><font><b/><sz val="12"/><name val="Arial"/></font><font><sz val="11"/><name val="Arial"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE6F1FB"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FF999999"/></left><right style="thin"><color rgb="FF999999"/></right><top style="thin"><color rgb="FF999999"/></top><bottom style="thin"><color rgb="FF999999"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="7"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function xlsxWorksheetRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/></Relationships>';
    }

    private function xlsxLogoDrawing(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><xdr:oneCellAnchor><xdr:from><xdr:col>0</xdr:col><xdr:colOff>320000</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>120000</xdr:rowOff></xdr:from><xdr:ext cx="2500000" cy="850000"/><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="1" name="School Logo 1"/><xdr:cNvPicPr/></xdr:nvPicPr><xdr:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill><xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>';
    }

    private function xlsxLogoDrawingRels(string $logoExtension): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/report-logo.' . $logoExtension . '"/></Relationships>';
    }

    private function xlsxImageExtension(string $path): string
    {
        return match (mime_content_type($path)) {
            'image/jpeg' => 'jpeg',
            'image/gif' => 'gif',
            default => 'png',
        };
    }

    private function writeZipArchive(string $path, array $entries): void
    {
        $file = fopen($path, 'wb');
        $centralDirectory = '';
        $offset = 0;
        $dosTime = $this->zipDosTime();
        $dosDate = $this->zipDosDate();

        foreach ($entries as $name => $contents) {
            $name = str_replace('\\', '/', $name);
            $contents = (string) $contents;
            $size = strlen($contents);
            $crc = crc32($contents);
            $nameLength = strlen($name);
            $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0);
            fwrite($file, $localHeader . $name . $contents);
            $centralDirectory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset) . $name;
            $offset += strlen($localHeader) + $nameLength + $size;
        }

        $centralDirectorySize = strlen($centralDirectory);
        fwrite($file, $centralDirectory);
        fwrite($file, pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), $centralDirectorySize, $offset, 0));
        fclose($file);
    }

    private function zipDosTime(): int
    {
        $time = now('Asia/Phnom_Penh');

        return ($time->hour << 11) | ($time->minute << 5) | intdiv($time->second, 2);
    }

    private function zipDosDate(): int
    {
        $time = now('Asia/Phnom_Penh');

        return (($time->year - 1980) << 9) | ($time->month << 5) | $time->day;
    }
}
