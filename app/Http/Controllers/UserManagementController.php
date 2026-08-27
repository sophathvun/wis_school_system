<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\SchoolInfo;
use App\Models\User;
use App\Models\BrandingSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class UserManagementController
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $search = trim((string) $request->query('search'));
        $perPage = min(max($request->integer('per_page', 10), 10), 100);
        $sortBy = $request->query('sortBy', 'name');
        $sortDir = strtolower($request->query('sortDir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $usersQuery = User::with(['department', 'position', 'roles', 'campuses'])->when($search, fn ($query) => $query->where(function ($query) use ($search) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhereHas('department', fn ($department) => $department->where('name', 'like', "%{$search}%"))
                ->orWhereHas('position', fn ($position) => $position->where('name', 'like', "%{$search}%"))
                ->orWhereHas('roles', fn ($role) => $role->where('name', 'like', "%{$search}%"))
                ->orWhereHas('campuses', fn ($campus) => $campus->where('campus_name_en', 'like', "%{$search}%"));
        }));
        $this->applyUserSort($usersQuery, $sortBy, $sortDir);
        $users = $usersQuery->paginate($perPage)->withQueryString();
        $staffDetails = $users->getCollection()->map(fn ($user) => [
            'gender' => $user->gender ?: '',
            'date_of_birth' => $user->date_of_birth?->format('d-M-Y') ?: '',
            'phone' => $user->phone ?: '',
            'position' => $user->position?->name ?: '',
            'campus' => $user->is_global ? 'All Campuses' : $user->campuses->pluck('campus_name_en')->join(', '),
        ])->values();
        return view('user-management', [
            'users' => $users,
            'editUser' => $request->integer('edit') ? User::with(['campuses', 'position', 'roles', 'permissionOverrides'])->find($request->integer('edit')) : null,
            'createUser' => $request->boolean('create'),
            'departments' => Department::where('status', 1)->orderBy('name')->get(),
            'positions' => Position::with('department')->where('status', 1)->orderBy('name')->get(),
            'roles' => Role::where('status', 1)->orderBy('name')->get(),
            'campuses' => SchoolInfo::where('status', 1)->orderBy('campus_name_en')->get(),
            'permissions' => Permission::orderBy('module')->orderBy('action')->get()->groupBy('module'),
            'staffDetails' => $staffDetails,
        ]);
    }

    public function print(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        return view('user-management-pdf', [
            'users' => $this->userReportRows(),
            'logoSrc' => $this->userReportLogoSrc(),
            'printMode' => true,
        ]);
    }

    public function exportExcel(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $filename = 'user-list-' . now('Asia/Phnom_Penh')->format('Ymd-His') . '.xlsx';
        $path = $this->userExcelPath($this->userReportRows());

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public function save(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $userId = $request->integer('user_id');
        if (!$userId && !$request->has('status')) {
            $request->merge(['status' => '1']);
        }
        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'], 'name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:20'], 'date_of_birth' => ['nullable', 'date'], 'phone' => ['nullable', 'string', 'max:50'], 'position_id' => ['nullable', 'exists:access_positions,id'],
            'username' => ['required', 'string', 'max:80', 'unique:users,username,'.$userId],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$userId],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'department_id' => ['nullable', 'exists:access_departments,id'], 'role_id' => ['required', 'exists:access_roles,id'],
            'campuses' => ['array'], 'campuses.*' => ['integer', 'exists:tb_school_info,id'],
            'permission_ids' => ['array'], 'permission_ids.*' => ['integer', 'exists:access_permissions,id'],
            'status' => ['required', 'in:0,1'], 'login_identifier' => ['required', 'in:username,email,both'],
            'is_global' => ['nullable', 'boolean'], 'photo' => ['nullable', 'image', 'max:2048'],
        ]);
        if ($userId && (int) $userId === (int) $request->user()->id && (string) $data['status'] === '0') {
            return back()->withInput()->withErrors(['status' => 'You cannot deactivate your own account while you are logged in.']);
        }
        // Unchecked checkboxes are omitted from the request; always persist the actual switch state.
        $data['is_global'] = $request->boolean('is_global');
        DB::transaction(function () use ($request, $data, $userId) {
            $user = $userId ? User::findOrFail($userId) : new User();
            $user->fill(collect($data)->except(['user_id', 'role_id', 'campuses', 'permission_ids', 'photo', 'password'])->filter(fn ($value) => $value !== null && $value !== '')->toArray());
            if (!empty($data['password'])) {
                $user->password = $data['password'];
                $user->must_change_password = false;
            } elseif (!$userId) {
                $user->password = '1234567890';
                $user->must_change_password = true;
            }
            if ($request->hasFile('photo')) $user->photo_path = $request->file('photo')->store('users', 'public');
            $user->save();
            $user->campuses()->sync(collect($data['campuses'] ?? [])->mapWithKeys(fn ($id, $i) => [$id => ['is_primary' => $i === 0, 'assigned_at' => now()]])->all());
            $campusIds = collect($data['campuses'] ?? [])->values();
            DB::table('access_user_roles')->where('user_id', $user->id)->delete();
            if ($user->is_global) {
                DB::table('access_user_roles')->insert(['user_id' => $user->id, 'role_id' => $data['role_id'], 'campus_id' => null, 'created_at' => now(), 'updated_at' => now()]);
            } else {
                foreach ($campusIds as $campusId) DB::table('access_user_roles')->insert(['user_id' => $user->id, 'role_id' => $data['role_id'], 'campus_id' => $campusId, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('access_user_permission_overrides')->where('user_id', $user->id)->delete();
            foreach ($data['permission_ids'] ?? [] as $permissionId) DB::table('access_user_permission_overrides')->insert(['user_id' => $user->id, 'permission_id' => $permissionId, 'allowed' => true, 'created_at' => now(), 'updated_at' => now()]);
        });
        return redirect()->route('users.index')->with('success', $userId ? 'User updated successfully.' : 'User created successfully.');
    }

    public function delete(Request $request, User $user)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $superAdminCount = User::whereHas('roles', fn ($query) => $query->where('access_roles.code', 'super-admin')->where('access_roles.status', 1))->count();
        if ($user->isSuperAdmin() && $superAdminCount <= 1) {
            return back()->withErrors(['user' => 'The final Super Administrator account cannot be deleted.']);
        }
        $deletingCurrentUser = $request->user()->is($user);
        if ($user->photo_path) Storage::disk('public')->delete($user->photo_path);
        $user->delete();
        if ($deletingCurrentUser) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('success', 'Your account was deleted successfully.');
        }
        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    private function applyUserSort($query, string $sortBy, string $sortDir): void
    {
        match ($sortBy) {
            'username', 'email', 'phone', 'login_identifier', 'status' => $query->orderBy($sortBy, $sortDir)->orderBy('name'),
            'department' => $query->orderBy(Department::select('name')->whereColumn('access_departments.id', 'users.department_id')->limit(1), $sortDir)->orderBy('name'),
            'position' => $query->orderBy(Position::select('name')->whereColumn('access_positions.id', 'users.position_id')->limit(1), $sortDir)->orderBy('name'),
            'role' => $query->orderBy(DB::table('access_user_roles')
                ->join('access_roles', 'access_roles.id', '=', 'access_user_roles.role_id')
                ->selectRaw('MIN(access_roles.name)')
                ->whereColumn('access_user_roles.user_id', 'users.id'), $sortDir)->orderBy('name'),
            'campus' => $query->orderByRaw("(CASE WHEN users.is_global = 1 THEN 'All Campuses' ELSE COALESCE((SELECT MIN(tb_school_info.campus_name_en) FROM access_user_campuses INNER JOIN tb_school_info ON tb_school_info.id = access_user_campuses.campus_id WHERE access_user_campuses.user_id = users.id), '') END) {$sortDir}")->orderBy('name'),
            default => $query->orderBy('name', $sortDir),
        };
    }

    private function userReportRows()
    {
        return User::with(['department', 'position', 'roles', 'campuses'])->orderBy('name')->get();
    }

    private function campusLabel(User $user): string
    {
        return $user->is_global ? 'All Campuses' : ($user->campuses->pluck('campus_name_en')->filter()->join(', ') ?: '-');
    }

    private function userReportLogoSrc(): ?string
    {
        $logoPath = $this->userReportLogoPath();

        return $logoPath
            ? 'data:' . (mime_content_type($logoPath) ?: 'image/png') . ';base64,' . base64_encode(file_get_contents($logoPath))
            : null;
    }

    private function userReportLogoPath(): ?string
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

    private function userExcelPath($users): string
    {
        $path = tempnam(sys_get_temp_dir(), 'user-list-');
        $xlsxPath = $path . '.xlsx';
        rename($path, $xlsxPath);

        $logoPath = $this->userReportLogoPath();
        $logoExtension = $logoPath ? $this->xlsxImageExtension($logoPath) : null;
        $entries = [
            '[Content_Types].xml' => $this->xlsxContentTypes($logoExtension),
            '_rels/.rels' => $this->xlsxRootRels(),
            'xl/workbook.xml' => $this->xlsxWorkbook(),
            'xl/_rels/workbook.xml.rels' => $this->xlsxWorkbookRels(),
            'xl/styles.xml' => $this->xlsxStyles(),
            'xl/worksheets/sheet1.xml' => $this->userWorksheetXml($users, (bool) $logoPath),
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

    private function userWorksheetXml($users, bool $hasLogo): string
    {
        $lastRow = $users->count() + 9;
        $rows = [
            $this->xlsxRow(1, [], 28),
            $this->xlsxRow(2, [], 28),
            $this->xlsxRow(3, [], 28),
            $this->xlsxRow(4, [], 10),
            $this->xlsxRow(5, [['A', 'តារាងអ្នកប្រើប្រាស់', 1]], 30),
            $this->xlsxRow(6, [['A', 'User List', 2]], 26),
            $this->xlsxRow(7, [['A', 'Generated: ' . now('Asia/Phnom_Penh')->format('d-M-Y h:i A'), 6]], 20),
            $this->xlsxRow(8, [], 8),
            $this->xlsxRow(9, [
                ['A', 'No.', 3],
                ['B', 'Staff Full Name', 3],
                ['C', 'Username', 3],
                ['D', 'Email', 3],
                ['E', 'Phone Number', 3],
                ['F', 'Position', 3],
                ['G', 'Campus Assignment', 3],
                ['H', 'Role', 3],
                ['I', 'Login', 3],
                ['J', 'Status', 3],
            ], 24),
        ];

        foreach ($users as $index => $user) {
            $row = $index + 10;
            $rows[] = $this->xlsxRow($row, [
                ['A', (string) ($index + 1), 5],
                ['B', $user->name ?: '-', 5],
                ['C', $user->username ?: '-', 5],
                ['D', $user->email ?: '-', 5],
                ['E', $user->phone ?: '-', 5],
                ['F', $user->position?->name ?: '-', 5],
                ['G', $this->campusLabel($user), 5],
                ['H', $user->roles->pluck('name')->unique()->join(', ') ?: '-', 5],
                ['I', $user->login_identifier === 'both' ? 'Username / Email' : ucfirst((string) $user->login_identifier), 5],
                ['J', $user->status ? 'Active' : 'Inactive', 5],
            ], 22);
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:J' . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="9" topLeftCell="A10" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="26" customWidth="1"/><col min="3" max="5" width="20" customWidth="1"/><col min="6" max="6" width="22" customWidth="1"/><col min="7" max="8" width="28" customWidth="1"/><col min="9" max="10" width="16" customWidth="1"/></cols>'
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . '<mergeCells count="5"><mergeCell ref="A1:J3"/><mergeCell ref="A5:J5"/><mergeCell ref="A6:J6"/><mergeCell ref="A7:J7"/><mergeCell ref="A8:J8"/></mergeCells>'
            . ($hasLogo ? '<drawing r:id="rId1"/>' : '')
            . '</worksheet>';
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
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function xlsxWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="User List" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function xlsxWorkbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
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
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            . '<xdr:oneCellAnchor><xdr:from><xdr:col>0</xdr:col><xdr:colOff>320000</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>120000</xdr:rowOff></xdr:from><xdr:ext cx="2500000" cy="850000"/>'
            . '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="1" name="School Logo 1"/><xdr:cNvPicPr/></xdr:nvPicPr><xdr:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill><xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor>'
            . '</xdr:wsDr>';
    }

    private function xlsxLogoDrawingRels(string $logoExtension): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/report-logo.' . $logoExtension . '"/>'
            . '</Relationships>';
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
