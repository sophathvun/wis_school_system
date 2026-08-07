<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Models\Department;
use Illuminate\Http\Request;

class PositionManagementController
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

        $positions = Position::with('department')->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
            ->orderBy('name')->paginate($perPage)->withQueryString();
        return view('positions', [
            'positions' => $positions,
            'positionDepartments' => $positions->getCollection()->map(fn ($position) => $position->department?->name ?: '—')->values(),
            'editPosition' => $request->integer('edit') ? Position::find($request->integer('edit')) : null,
            'departments' => Department::where('status', 1)->orderBy('name')->get(),
        ]);
    }

    public function save(Request $request)
    {
        $this->authorizeAdmin($request);
        $id = $request->integer('position_id');
        if (!$id && !$request->has('status')) $request->merge(['status' => '1']);
        $data = $request->validate([
            'position_id' => ['nullable', 'exists:access_positions,id'],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'alpha_dash', 'max:80', 'unique:access_positions,code,'.$id],
            'department_id' => ['required', 'exists:access_departments,id'],
            'status' => ['required', 'in:0,1'],
        ]);
        Position::updateOrCreate(['id' => $id ?: null], collect($data)->except('position_id')->toArray());
        return redirect()->route('positions.index')->with('success', $id ? 'Position updated successfully.' : 'Position created successfully.');
    }

    public function delete(Request $request, Position $position)
    {
        $this->authorizeAdmin($request);
        if ($position->users()->exists()) return back()->withErrors(['position' => 'This position cannot be deleted because it is assigned to users.']);
        $position->delete();
        return back()->with('success', 'Position deleted successfully.');
    }
}
