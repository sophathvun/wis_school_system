<?php

namespace App\Http\Controllers;

use App\Models\AcademicTrack;
use App\Models\Grade;
use App\Support\SettingsQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicTrackController
{
    public function index()
    {
        return view('academic-tracks', [
            'grades' => Grade::where('status', 1)
                ->orderByRaw('CAST(grade_order AS UNSIGNED)')
                ->orderBy('grade')
                ->get(['id', 'grade']),
        ]);
    }

    public function fetchData(Request $request)
    {
        $search = SettingsQuery::search($request);

        return response()->json(AcademicTrack::with('grade:id,grade')
            ->when($search, fn ($query) => $query->where(fn ($sub) => $sub
                ->where('name_en', 'like', "%{$search}%")
                ->orWhere('name_kh', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhereHas('grade', fn ($grade) => $grade->where('grade', 'like', "%{$search}%"))))
            ->orderBy('grade_id')
            ->orderBy('stream_type')
            ->orderBy('language')
            ->paginate(SettingsQuery::perPage($request)));
    }

    public function save(Request $request)
    {
        $id = $request->integer('academic_track_id') ?: null;
        $data = $request->validate([
            'grade_id' => ['nullable', 'exists:tb_grade,id'],
            'name_en' => ['required', 'string', 'max:120'],
            'name_kh' => ['nullable', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('tb_academic_track', 'code')->ignore($id)],
            'stream_type' => ['required', Rule::in(['science', 'social_science'])],
            'language' => ['required', Rule::in(['khmer', 'english'])],
            'status' => ['required', 'boolean'],
        ]);

        $track = $id ? AcademicTrack::findOrFail($id) : new AcademicTrack();
        $track->fill($data)->save();

        return response()->json([
            'status' => 'success',
            'message' => $id ? 'Academic track updated successfully.' : 'Academic track created successfully.',
            'data' => $track,
        ], $id ? 200 : 201);
    }

    public function delete(AcademicTrack $academicTrack)
    {
        abort_if($academicTrack->studentEnrollments()->exists(), 422, 'This academic track is already assigned to students and cannot be deleted. Deactivate it instead.');
        $academicTrack->delete();

        return response()->json(['status' => 'success', 'message' => 'Academic track deleted successfully.']);
    }
}
