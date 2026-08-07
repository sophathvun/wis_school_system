<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\SchoolInfo;
use App\Models\User;
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
        $users = User::with(['department', 'position', 'roles', 'campuses'])->when($search, fn ($query) => $query->where(function ($query) use ($search) {
            $query->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
        }))->orderBy('name')->paginate($perPage)->withQueryString();
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
            'departments' => Department::where('status', 1)->orderBy('name')->get(),
            'positions' => Position::with('department')->where('status', 1)->orderBy('name')->get(),
            'roles' => Role::where('status', 1)->orderBy('name')->get(),
            'campuses' => SchoolInfo::where('status', 1)->orderBy('campus_name_en')->get(),
            'permissions' => Permission::orderBy('module')->orderBy('action')->get()->groupBy('module'),
            'staffDetails' => $staffDetails,
        ]);
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
}
