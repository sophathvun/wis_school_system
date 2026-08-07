<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentManagementController
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
        return view('departments', [
            'departments' => Department::when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                ->orderBy('name')->paginate($perPage)->withQueryString(),
            'editDepartment' => $request->integer('edit') ? Department::find($request->integer('edit')) : null,
        ]);
    }

    public function save(Request $request)
    {
        $this->authorizeAdmin($request);
        $id = $request->integer('department_id');
        if (!$id && !$request->has('status')) $request->merge(['status' => '1']);
        $data = $request->validate([
            'department_id' => ['nullable', 'exists:access_departments,id'],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'alpha_dash', 'max:80', 'unique:access_departments,code,'.$id],
            'status' => ['required', 'in:0,1'],
        ]);
        Department::updateOrCreate(['id' => $id ?: null], collect($data)->except('department_id')->toArray());
        return redirect()->route('departments.index')->with('success', $id ? 'Department updated successfully.' : 'Department created successfully.');
    }

    public function delete(Request $request, Department $department)
    {
        $this->authorizeAdmin($request);
        $used = $department->permissions()->exists()
            || $department->roles()->exists()
            || $department->users()->exists();
        if ($used) return back()->withErrors(['department' => 'This department cannot be deleted because it is assigned to users, roles, or permissions.']);
        $department->delete();
        return back()->with('success', 'Department deleted successfully.');
    }
}
