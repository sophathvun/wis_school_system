<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\StudentEnrollment;
use App\Models\SchoolInfo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\SettingsQuery;
use Illuminate\Support\Facades\DB;

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
                    ->orWhere('academic_year_code', 'like', "%{$search}%")
                    ->orWhere('lifecycle_status', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderByDesc('academic_year')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($academicYears);
    }

    public function exportPdf()
    {
        $academicYears = AcademicYear::orderByDesc('academic_year')->get();
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
            'lifecycle_status' => ['required', Rule::in(['draft', 'pending', 'started', 'finished', 'archived'])],
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

        $validated['status'] = in_array($validated['lifecycle_status'], ['draft', 'pending', 'started'], true) ? 1 : 0;

        $academicYear = DB::transaction(function () use ($academicYearId, $validated) {
            if ($validated['lifecycle_status'] === 'started') {
                AcademicYear::where('period_type', $validated['period_type'])
                    ->where('lifecycle_status', 'started')
                    ->when($academicYearId, fn ($query) => $query->where('id', '!=', $academicYearId))
                    ->get()
                    ->each(function (AcademicYear $year) use ($validated) {
                        $status = $this->displacedLifecycleStatus($year->academic_year, $validated['academic_year']);
                        $year->update([
                            'lifecycle_status' => $status,
                            'status' => $status === 'pending' ? 1 : 0,
                        ]);
                        $this->syncEnrollmentStatuses($year);
                    });
            }

            $academicYear = $academicYearId
                ? AcademicYear::findOrFail($academicYearId)
                : new AcademicYear();

            $academicYear->fill($validated);
            $academicYear->save();
            $this->syncEnrollmentStatuses($academicYear);

            return $academicYear;
        });

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

    public function setCurrent(AcademicYear $academicYear)
    {
        DB::transaction(function () use ($academicYear) {
            AcademicYear::where('period_type', $academicYear->period_type)
                ->where('lifecycle_status', 'started')
                ->where('id', '!=', $academicYear->id)
                ->get()
                ->each(function (AcademicYear $year) use ($academicYear) {
                    $status = $this->displacedLifecycleStatus($year->academic_year, $academicYear->academic_year);
                    $year->update([
                        'lifecycle_status' => $status,
                        'status' => $status === 'pending' ? 1 : 0,
                    ]);
                    $this->syncEnrollmentStatuses($year);
                });

            $academicYear->update([
                'lifecycle_status' => 'started',
                'status' => 1,
            ]);
            $this->syncEnrollmentStatuses($academicYear);
        });

        return response()->json([
            'status' => 'success',
            'message' => $academicYear->period_type === 'summer'
                ? 'Summer School set as started. Previous started Summer School was finished automatically.'
                : 'Academic year set as started. Previous started regular year was finished automatically.',
            'data' => $academicYear->fresh(),
        ]);
    }

    private function displacedLifecycleStatus(string $displacedYear, string $newStartedYear): string
    {
        return $this->academicYearSortValue($displacedYear) > $this->academicYearSortValue($newStartedYear)
            ? 'pending'
            : 'finished';
    }

    private function academicYearSortValue(string $academicYear): int
    {
        preg_match_all('/\d{4}/', $academicYear, $matches);
        $years = array_map('intval', $matches[0] ?? []);

        return $years ? max($years) : 0;
    }

    private function syncEnrollmentStatuses(AcademicYear $academicYear): void
    {
        $enrollmentStatus = match ($academicYear->lifecycle_status) {
            'started' => 'active',
            'pending' => 'pending',
            'finished' => 'completed',
            default => null,
        };

        if ($enrollmentStatus) {
            $query = StudentEnrollment::where('academic_year_id', $academicYear->id);

            $query->whereIn('enrollment_status', ['active', 'pending', 'completed'])
                ->update(['enrollment_status' => $enrollmentStatus]);
        }
    }

}
