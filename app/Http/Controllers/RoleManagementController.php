<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleManagementController
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $search = trim((string) $request->query('search'));
        $perPage = min(max($request->integer('per_page', 10), 10), 100);
        return view('roles', [
            'roles' => Role::with('department')->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                ->orderBy('name')->paginate($perPage)->withQueryString(),
            'editRole' => $request->integer('edit') ? Role::find($request->integer('edit')) : null,
            'departments' => Department::where('status', 1)->orderBy('name')->get(),
        ]);
    }

    public function save(Request $request)
    {
        $this->authorizeAdmin($request);
        $id = $request->integer('role_id');
        if (!$id && !$request->has('status')) $request->merge(['status' => '1']);
        $data = $request->validate([
            'role_id' => ['nullable', 'exists:access_roles,id'],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'alpha_dash', 'max:80', 'unique:access_roles,code,'.$id],
            'description' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:access_departments,id'],
            'is_global' => ['nullable', 'boolean'],
            'status' => ['required', 'in:0,1'],
        ]);
        $payload = collect($data)->except('role_id')->toArray();
        $payload['is_global'] = $request->boolean('is_global');
        Role::updateOrCreate(['id' => $id ?: null], $payload);
        return redirect()->route('roles.index')->with('success', $id ? 'Role updated successfully.' : 'Role created successfully.');
    }

    public function delete(Request $request, Role $role)
    {
        $this->authorizeAdmin($request);
        if ($role->code === 'super-admin') return back()->withErrors(['role' => 'The Super Administrator role cannot be deleted.']);
        if ($role->permissions()->exists() || $role->users()->exists()) {
            return back()->withErrors(['role' => 'This role cannot be deleted because it is assigned to users or permissions.']);
        }
        $role->delete();
        return back()->with('success', 'Role deleted successfully.');
    }
}
