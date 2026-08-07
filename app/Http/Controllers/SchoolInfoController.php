<?php

namespace App\Http\Controllers;

use App\Models\SchoolInfo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Support\SettingsQuery;
use Illuminate\Support\Facades\Storage;

class SchoolInfoController
{
    public function index()
    {
        return view('school_profile');
    }

    public function fetchData(Request $request)
    {
        $perPage = SettingsQuery::perPage($request);
        $search = SettingsQuery::search($request);

        $schools = SchoolInfo::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('school_name_en', 'like', "%{$search}%")
                        ->orWhere('school_name_kh', 'like', "%{$search}%")
                        ->orWhere('campus_name_en', 'like', "%{$search}%")
                        ->orWhere('campus_name_kh', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate($perPage);

        return response()->json($schools);
    }

    public function exportPdf()
    {
        $schools = SchoolInfo::latest('id')->get();
        $logoPath = $schools->first()?->logo_path
            ? storage_path('app/public/' . $schools->first()->logo_path)
            : null;

        return Pdf::loadView('school-profile-pdf', compact('schools', 'logoPath'))->stream('school-profiles.pdf');
    }

    public function save(Request $request)
    {
        $schoolId = $request->input('school_id');
        $campusNameEnValue = $request->input('campus_name_en');
        $campusNameKhValue = $request->input('campus_name_kh');
        $phoneValue = $request->input('phone');

        $duplicateFields = [];

        if (filled($campusNameEnValue) && SchoolInfo::query()
            ->where('campus_name_en', $campusNameEnValue)
            ->when($schoolId, fn ($query) => $query->where('id', '!=', $schoolId))
            ->exists()) {
            $duplicateFields[] = "Campus Name in English '{$campusNameEnValue}'";
        }

        if (filled($campusNameKhValue) && SchoolInfo::query()
            ->where('campus_name_kh', $campusNameKhValue)
            ->when($schoolId, fn ($query) => $query->where('id', '!=', $schoolId))
            ->exists()) {
            $duplicateFields[] = "Campus Name in Khmer '{$campusNameKhValue}'";
        }

        if (filled($phoneValue) && SchoolInfo::query()
            ->where('phone', $phoneValue)
            ->when($schoolId, fn ($query) => $query->where('id', '!=', $schoolId))
            ->exists()) {
            $duplicateFields[] = "Phone Number '{$phoneValue}'";
        }

        if ($duplicateFields) {
            $message = count($duplicateFields) === 1
                ? "Unable to save School Profile. {$duplicateFields[0]} already existed."
                : 'Unable to save School Profile. ' . implode(' and ', $duplicateFields) . ' are already existed.';

            return response()->json(['status' => 'error', 'message' => $message], 422);
        }

        $validated = $request->validate([
            'school_name_en' => ['required', 'string', 'max:100'],
            'school_name_kh' => ['required', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'campus_name_en' => ['required', 'string', 'max:20', Rule::unique('tb_school_info', 'campus_name_en')->ignore($schoolId)],
            'campus_name_kh' => ['required', 'string', 'max:20', Rule::unique('tb_school_info', 'campus_name_kh')->ignore($schoolId)],
            'address' => ['nullable', 'string', 'max:250'],
            'phone' => ['nullable', 'string', 'max:50', Rule::unique('tb_school_info', 'phone')->ignore($schoolId)],
            'description' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'boolean'],
        ], [
            'school_name_en.required' => 'School name in English is required.',
            'school_name_kh.required' => 'School name in Khmer is required.',
            'campus_name_en.required' => 'Campus name in English is required.',
            'campus_name_kh.required' => 'Campus name in Khmer is required.',
        ]);

        $school = $schoolId ? SchoolInfo::findOrFail($schoolId) : new SchoolInfo();
        $school->fill($validated);

        if ($request->hasFile('logo')) {
            if ($school->logo_path) {
                Storage::disk('public')->delete($school->logo_path);
            }
            $school->logo_path = $request->file('logo')->store('school_logos', 'public');
        }

        $school->save();

        return response()->json([
            'status' => 'success',
            'message' => $schoolId ? 'School profile updated successfully.' : 'School profile created successfully.',
            'data' => $school,
        ], $schoolId ? 200 : 201);
    }

    public function delete($id)
    {
        $school = SchoolInfo::find($id);
        if (!$school) return response()->json(['status' => 'error', 'message' => 'School profile not found.'], 404);

        if ($school->logo_path) {
            Storage::disk('public')->delete($school->logo_path);
        }
        $school->delete();
        return response()->json(['status' => 'success', 'message' => 'School profile deleted successfully.']);
    }
}
