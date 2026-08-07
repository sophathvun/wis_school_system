<?php

namespace App\Http\Controllers;

use App\Models\EducationLevel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Support\SettingsQuery;

class EducationLevelController
{
    public function index() { return view('education-level'); }

    public function fetchData(Request $request)
    {
        $perPage = SettingsQuery::perPage($request);
        $search = SettingsQuery::search($request);
        $sort = SettingsQuery::sort($request, ['level_name', 'level_short_name', 'level_order', 'description', 'status'], 'level_order', 'sort');
        $direction = SettingsQuery::direction($request, 'direction');
        $query = EducationLevel::query()->when($search, fn ($q) => $q->where(fn ($q) => $q->where('level_name', 'like', "%{$search}%")->orWhere('level_short_name', 'like', "%{$search}%")));
        $sort === 'level_order' ? $query->orderByRaw('CAST(level_order AS UNSIGNED) ' . $direction) : $query->orderBy($sort, $direction);
        return response()->json($query->orderBy('id')->paginate($perPage));
    }

    public function save(Request $request)
    {
        $id = $request->input('education_level_id');
        $validated = $request->validate([
            'level_name' => ['required', 'string', 'max:50', Rule::unique('tb_education_level', 'level_name')->ignore($id)],
            'level_short_name' => ['required', 'string', 'max:20', Rule::unique('tb_education_level', 'level_short_name')->ignore($id)],
            'level_order' => ['nullable', 'string', 'max:3'], 'description' => ['nullable', 'string', 'max:100'], 'status' => ['required', 'boolean'],
        ]);
        $level = $id ? EducationLevel::findOrFail($id) : new EducationLevel();
        $level->fill($validated)->save();
        return response()->json(['status' => 'success', 'message' => $id ? 'Education level updated successfully.' : 'Education level created successfully.', 'data' => $level], $id ? 200 : 201);
    }

    public function delete($id)
    {
        $level = EducationLevel::find($id);
        if (!$level) return response()->json(['status' => 'error', 'message' => 'Education level not found.'], 404);
        $level->delete();
        return response()->json(['status' => 'success', 'message' => 'Education level deleted successfully.']);
    }
}
