<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolInfo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\SettingsQuery;

class AcademicYearController
{
    public function index()
    {
        return view('academicYears');
    }

    // fetch all academic years
    public function fetchData(Request $request)
    {
        $perPage = SettingsQuery::perPage($request);
        $search = SettingsQuery::search($request);

        $academicYears = AcademicYear::query()
            ->when($search, function ($query) use ($search) {
                $query->where('academic_year', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })->paginate($perPage);

        return response()->json($academicYears);
    }

    public function exportPdf()
    {
        $academicYears = AcademicYear::orderBy('academic_year')->get();
        $school = SchoolInfo::latest('id')->first();
        $logoPath = $school?->logo_path
            ? storage_path('app/public/' . $school->logo_path)
            : storage_path('app/public/school_logo/wis_logo.png');

        $pdf = Pdf::loadView('academicYears-pdf', compact('academicYears', 'logoPath'));

        return $pdf->stream('academic-years.pdf');
    }

    public function save(Request $request)
    {
        $academicYearId = $request->input('academic_year_id');
        $academicYearValue = $request->input('academic_year');
        $ayCodeValue = $request->input('ay_code');

        $academicYearExists = filled($academicYearValue) && AcademicYear::query()
            ->where('academic_year', $academicYearValue)
            ->when($academicYearId, fn ($query) => $query->where('id', '!=', $academicYearId))
            ->exists();

        $ayCodeExists = filled($ayCodeValue) && AcademicYear::query()
            ->where('academic_year_code', $ayCodeValue)
            ->when($academicYearId, fn ($query) => $query->where('id', '!=', $academicYearId))
            ->exists();

        if ($academicYearExists || $ayCodeExists) {
            if ($academicYearExists && $ayCodeExists) {
                $message = "Unable to save Academic Year. Academic Year '{$academicYearValue}' and AY Code '{$ayCodeValue}' are already existed.";
            } elseif ($academicYearExists) {
                $message = "Unable to save Academic Year. Academic Year '{$academicYearValue}' already existed.";
            } else {
                $message = "Unable to save Academic Year. AY Code '{$ayCodeValue}' already existed.";
            }

            return response()->json(['status' => 'error', 'message' => $message], 422);
        }

        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'max:20', Rule::unique('tb_academic_year', 'academic_year')->ignore($academicYearId)],
            'ay_code' => ['nullable', 'string', 'max:20', Rule::unique('tb_academic_year', 'academic_year_code')->ignore($academicYearId)],
            'period_type' => ['required', Rule::in(['regular', 'summer'])],
            'parent_academic_year_id' => ['nullable', 'exists:tb_academic_year,id', 'different:academic_year_id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'boolean'],
        ]);

        if ($validated['period_type'] === 'summer') {
            if (!empty($validated['parent_academic_year_id'])) {
                $parent = AcademicYear::find($validated['parent_academic_year_id']);
                if (!$parent || $parent->period_type === 'summer' || ($academicYearId && (int) $parent->id === (int) $academicYearId)) {
                    throw ValidationException::withMessages(['parent_academic_year_id' => 'The optional parent school year must be a regular academic year.']);
                }
            }

        } else {
            $validated['parent_academic_year_id'] = null;
            $validated['start_date'] = null;
            $validated['end_date'] = null;
        }

        $validated['ay_code'] = filled($validated['ay_code'] ?? null)
            ? trim($validated['ay_code'])
            : null;

        $academicYear = $academicYearId
            ? AcademicYear::findOrFail($academicYearId)
            : new AcademicYear();

        $academicYear->fill($validated);
        $academicYear->save();

        return response()->json([
            'status' => 'success',
            'message' => $academicYearId
                ? 'Academic year updated successfully.'
                : 'Academic year created successfully.',
            'data' => $academicYear,
        ], $academicYearId ? 200 : 201);
    }

    public function delete($id)
    {
        $academicYear = AcademicYear::find($id);

        if (!$academicYear) {
            return response()->json([
                'status' => 'error',
                'message' => 'Academic year not found.',
            ], 404);
        }

        $academicYear->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Academic year deleted successfully.',
        ]);
    }

}
