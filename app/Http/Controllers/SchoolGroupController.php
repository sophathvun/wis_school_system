<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\SchoolGroup;
use Illuminate\Http\Request;
use App\Support\SettingsQuery;

class SchoolGroupController
{
    public function index() { return view('group'); }

    public function options() { return response()->json(['classes' => SchoolClass::orderByRaw('CAST(class_order AS UNSIGNED)')->orderBy('class_name')->get(['id', 'class_name'])]); }

    public function fetchData(Request $request)
    {
        $search = SettingsQuery::search($request);
        $query = SchoolGroup::with('schoolClass')->when($search, fn ($q) => $q->where('group_name', 'like', "%{$search}%"));
        return response()->json($query->orderByRaw('CAST(group_order AS UNSIGNED)')->orderBy('id')->paginate(SettingsQuery::perPage($request)));
    }

    public function save(Request $request)
    {
        $id = $request->input('group_id');
        $validated = $request->validate(['class_id' => ['required', 'exists:tb_class,id'], 'group_name' => ['required', 'string', 'max:20'], 'group_order' => ['nullable', 'string', 'max:3'], 'status' => ['required', 'boolean']]);
        $exists = SchoolGroup::where('class_id', $validated['class_id'])->where('group_name', $validated['group_name'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists();
        if ($exists) return response()->json(['status' => 'error', 'message' => "Unable to save Group. Group '{$validated['group_name']}' already existed."], 422);
        $group = $id ? SchoolGroup::findOrFail($id) : new SchoolGroup();
        $group->fill($validated)->save();
        return response()->json(['status' => 'success', 'message' => $id ? 'Group updated successfully.' : 'Group created successfully.', 'data' => $group], $id ? 200 : 201);
    }

    public function delete($id) { $group = SchoolGroup::find($id); if (!$group) return response()->json(['status' => 'error', 'message' => 'Group not found.'], 404); $group->delete(); return response()->json(['status' => 'success', 'message' => 'Group deleted successfully.']); }
}
