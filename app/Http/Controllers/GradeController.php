<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use App\Support\SettingsQuery;

class GradeController
{
    public function index()
    {
        return view('grade');
    }

    public function fetchData(Request $request)
    {
        $perPage = SettingsQuery::perPage($request);
        $search = SettingsQuery::search($request);
        $allowedSorts = ['grade', 'grade_short_name', 'grade_order', 'description', 'status'];
        $sortBy = SettingsQuery::sort($request, $allowedSorts, 'grade_order');
        $sortDir = SettingsQuery::direction($request);

        $grades = Grade::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('grade', 'like', "%{$search}%")
                        ->orWhere('grade_short_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($sortBy === 'grade_order', fn ($query) => $query->orderByRaw("CAST(grade_order AS UNSIGNED) {$sortDir}"))
            ->when($sortBy !== 'grade_order', fn ($query) => $query->orderBy($sortBy, $sortDir))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json($grades);
    }

    public function exportPdf()
    {
        $grades = Grade::orderBy('grade_order')->orderBy('id')->get();

        return Pdf::loadView('grade-pdf', compact('grades'))
            ->stream('grades.pdf');
    }

    public function save(Request $request)
    {
        $gradeId = $request->input('grade_id');
        $gradeValue = $request->input('grade');
        $shortNameValue = $request->input('grade_short_name');

        $gradeExists = $gradeValue && Grade::query()
            ->where('grade', $gradeValue)
            ->when($gradeId, fn ($query) => $query->where('id', '!=', $gradeId))
            ->exists();
        $shortNameExists = $shortNameValue && Grade::query()
            ->where('grade_short_name', $shortNameValue)
            ->when($gradeId, fn ($query) => $query->where('id', '!=', $gradeId))
            ->exists();

        if ($gradeExists || $shortNameExists) {
            if ($gradeExists && $shortNameExists) {
                $message = "Unable to save Grade. Grade '{$gradeValue}' and Sort Name '{$shortNameValue}' are already existed.";
            } elseif ($gradeExists) {
                $message = "Unable to save Grade. Grade '{$gradeValue}' already existed.";
            } else {
                $message = "Unable to save Grade. Sort Name '{$shortNameValue}' already existed.";
            }

            return response()->json(['status' => 'error', 'message' => $message], 422);
        }

        $validated = $request->validate([
            'grade' => ['required', 'string', 'max:20', Rule::unique('tb_grade', 'grade')->ignore($gradeId)],
            'grade_short_name' => ['required', 'string', 'max:20', Rule::unique('tb_grade', 'grade_short_name')->ignore($gradeId)],
            'grade_order' => ['nullable', 'string', 'max:3'],
            'description' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'boolean'],
        ], [
            'grade.unique' => "Unable to save Grade. Grade ':input' already existed.",
            'grade_short_name.unique' => "Unable to save Grade. Sort Name ':input' already existed.",
        ]);

        try {
            $grade = $gradeId ? Grade::findOrFail($gradeId) : new Grade();
            $grade->fill($validated);
            $grade->save();
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23000') {
                $field = str_contains($exception->getMessage(), 'grade_short_name') ? 'grade_short_name' : 'grade';
                $label = $field === 'grade_short_name' ? 'Sort Name' : 'Grade';
                $value = $request->input($field);
                $message = "Unable to save Grade. {$label} '{$value}' already existed.";
                return response()->json(['message' => $message], 422);
            }
            throw $exception;
        }

        return response()->json([
            'status' => 'success',
            'message' => $gradeId ? 'Grade updated successfully.' : 'Grade created successfully.',
            'data' => $grade,
        ], $gradeId ? 200 : 201);
    }

    public function delete($id)
    {
        $grade = Grade::find($id);

        if (!$grade) {
            return response()->json(['status' => 'error', 'message' => 'Grade not found.'], 404);
        }

        $grade->delete();

        return response()->json(['status' => 'success', 'message' => 'Grade deleted successfully.']);
    }
}
