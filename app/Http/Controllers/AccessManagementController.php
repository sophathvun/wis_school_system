<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Department;
use App\Models\SchoolInfo;
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
}
