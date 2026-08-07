<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use App\Support\SettingsQuery;

class SchoolClassController
{
    public function index()
    {
        return view('class');
    }

    public function fetchData(Request $request)
    {
        $perPage = SettingsQuery::perPage($request);
        $search = SettingsQuery::search($request);
        $allowedSorts = ['class_name', 'class_order', 'status'];
        $sortBy = SettingsQuery::sort($request, $allowedSorts, 'class_order');
        $sortDir = SettingsQuery::direction($request);

        $classes = SchoolClass::query()
            ->when($search, fn ($query) => $query->where('class_name', 'like', "%{$search}%"))
            ->when($sortBy === 'class_order', fn ($query) => $query->orderByRaw("CAST(class_order AS UNSIGNED) {$sortDir}"))
            ->when($sortBy !== 'class_order', fn ($query) => $query->orderBy($sortBy, $sortDir))
            ->orderBy('id')->paginate($perPage);

        return response()->json($classes);
    }

    public function exportPdf()
    {
        $classes = SchoolClass::orderBy('class_order')->orderBy('id')->get();
        return Pdf::loadView('class-pdf', compact('classes'))->stream('classes.pdf');
    }

    public function save(Request $request)
    {
        $classId = $request->input('class_id');

        $validated = $request->validate([
            'class_name' => ['required', 'string', 'max:20', Rule::unique('tb_class', 'class_name')->ignore($classId)],
            'class_order' => ['nullable', 'string', 'max:3'],
            'status' => ['required', 'boolean'],
        ], [
            'class_name.unique' => "Unable to save class. Class ':input' is already existed.",
        ]);

        try {
            $class = $classId ? SchoolClass::findOrFail($classId) : new SchoolClass();
            $class->fill($validated);
            $class->save();
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23000') {
                return response()->json([
                    'message' => "Unable to save class. Class '{$request->input('class_name')}' is already existed.",
                    'errors' => ['class_name' => ["Unable to save class. Class '{$request->input('class_name')}' is already existed."]],
                ], 422);
            }

            throw $exception;
        }

        return response()->json([
            'status' => 'success',
            'message' => $classId ? 'Class updated successfully.' : 'Class created successfully.',
            'data' => $class,
        ], $classId ? 200 : 201);
    }

    public function delete($id)
    {
        $class = SchoolClass::find($id);
        if (!$class) return response()->json(['status' => 'error', 'message' => 'Class not found.'], 404);

        $class->delete();
        return response()->json(['status' => 'success', 'message' => 'Class deleted successfully.']);
    }
}
