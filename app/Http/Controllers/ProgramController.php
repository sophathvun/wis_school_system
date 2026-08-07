<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\EducationLevel;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Support\SettingsQuery;

class ProgramController
{
    public function index() { return view('program'); }

    public function options()
    {
        return response()->json(['academicYears' => AcademicYear::where('status', 1)->orderByDesc('id')->get(['id', 'academic_year']), 'levels' => EducationLevel::where('status', 1)->orderBy('level_order')->get(['id', 'level_name'])]);
    }

    public function fetchData(Request $request)
    {
        $search = SettingsQuery::search($request);
        $query = Program::with(['academicYear', 'educationLevel'])->when($search, fn ($q) => $q->where(fn ($sub) => $sub->where('program_name', 'like', "%{$search}%")->orWhere('program_code', 'like', "%{$search}%")));
        return response()->json($query->latest('id')->paginate(SettingsQuery::perPage($request)));
    }

    public function save(Request $request)
    {
        $id = $request->input('program_id');
        $validated = $request->validate([
            'academic_year_id' => ['nullable', 'exists:tb_academic_year,id'], 'education_level_id' => ['required', 'exists:tb_education_level,id'],
            'program_name' => ['required', 'string', 'max:100'], 'program_code' => ['required', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:100'], 'status' => ['required', 'boolean'],
        ]);
        $duplicate = Program::where('education_level_id', $validated['education_level_id'])->where('academic_year_id', $validated['academic_year_id'] ?? null)->when($id, fn ($q) => $q->where('id', '!=', $id))->where(fn ($q) => $q->where('program_name', $validated['program_name'])->orWhere('program_code', $validated['program_code']))->first();
        if ($duplicate) return response()->json(['status' => 'error', 'message' => "Unable to save Program. Program name or code already existed."], 422);
        $program = $id ? Program::findOrFail($id) : new Program();
        $program->fill($validated)->save();
        return response()->json(['status' => 'success', 'message' => $id ? 'Program updated successfully.' : 'Program created successfully.', 'data' => $program], $id ? 200 : 201);
    }

    public function delete($id) { $program = Program::find($id); if (!$program) return response()->json(['status' => 'error', 'message' => 'Program not found.'], 404); $program->delete(); return response()->json(['status' => 'success', 'message' => 'Program deleted successfully.']); }
}
