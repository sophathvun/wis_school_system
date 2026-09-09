<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Country;
use App\Models\Occupation;
use App\Models\Student;
use App\Support\SettingsQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FamilyController
{
    public function index()
    {
        return view('families', [
            'countries' => Country::where('status', 1)
                ->orderBy('country_name_en')
                ->get(['id', 'country_name_en', 'nationality_name_en', 'nationality_name_kh', 'flag_path']),
            'occupations' => Occupation::where('status', 1)
                ->orderBy('occupation_name_en')
                ->get(['id', 'occupation_name_en', 'occupation_name_kh']),
            'families' => Family::orderBy('family_number')->get(['id', 'family_number']),
        ]);
    }

    public function fetchData(Request $request)
    {
        $search = SettingsQuery::search($request);
        $sortBy = $request->query('sort', 'family_number');
        $sortDir = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $families = Family::query()
            ->with([
                'members' => fn ($query) => $query->whereIn('relationship_type', FamilyMember::RELATIONSHIP_TYPES),
                'students:id,student_no,student_id,full_name_en,full_name_kh,photo_path,gender,gender_kh,date_of_birth,status',
                'students.enrollments.academicYear',
                'students.enrollments.campus',
                'students.enrollments.grade',
                'students.enrollments.schoolClass',
                'students.enrollments.session',
            ])
            ->withCount('students')
            ->when($search, fn ($query) => $query->where(function ($sub) use ($search) {
                $sub->where('family_number', 'like', "%{$search}%")
                    ->orWhere('family_name', 'like', "%{$search}%")
                    ->orWhere('primary_phone', 'like', "%{$search}%")
                    ->orWhere('primary_email', 'like', "%{$search}%")
                    ->orWhereHas('members', fn ($member) => $member
                        ->whereIn('relationship_type', FamilyMember::RELATIONSHIP_TYPES)
                        ->where(function ($memberSearch) use ($search) {
                            $memberSearch->where('full_name_en', 'like', "%{$search}%")
                                ->orWhere('full_name_kh', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('occupation_en', 'like', "%{$search}%")
                                ->orWhere('nationality_en', 'like', "%{$search}%");
                        }));
            }))
            ->when($sortBy === 'father_en', fn ($query) => $query
                ->orderBy(FamilyMember::select('full_name_en')
                    ->whereColumn('tb_family_member.family_id', 'tb_family.id')
                    ->where('relationship_type', 'father')
                    ->limit(1), $sortDir)
                ->orderBy('family_number'))
            ->when($sortBy === 'mother_en', fn ($query) => $query
                ->orderBy(FamilyMember::select('full_name_en')
                    ->whereColumn('tb_family_member.family_id', 'tb_family.id')
                    ->where('relationship_type', 'mother')
                    ->limit(1), $sortDir)
                ->orderBy('family_number'))
            ->when(! in_array($sortBy, ['father_en', 'mother_en'], true), fn ($query) => $query
                ->orderBy('family_number', $sortDir))
            ->paginate(SettingsQuery::perPage($request));

        return response()->json($families);
    }

    public function show(Family $family)
    {
        return response()->json(['data' => $family->load(['students.enrollments.academicYear', 'members'])]);
    }

    public function save(Request $request)
    {
        $id = $request->integer('family_id') ?: null;
        $validated = $request->validate([
            'family_number' => ['required', 'string', 'max:30', Rule::unique('tb_family', 'family_number')->ignore($id)],
            'family_name' => ['nullable', 'string', 'max:120'],
            'family_name_kh' => ['nullable', 'string', 'max:120'],
            'primary_phone' => ['nullable', 'string', 'max:50'],
            'primary_email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'boolean'],
            'members' => ['nullable', 'array'],
            'members.*.full_name_en' => ['nullable', 'string', 'max:160'],
            'members.*.full_name_kh' => ['nullable', 'string', 'max:160'],
            'members.*.occupation_en' => ['nullable', 'string', 'max:120'],
            'members.*.occupation_kh' => ['nullable', 'string', 'max:120'],
            'members.*.occupation_id' => ['nullable', 'exists:tb_occupation,id'],
            'members.*.nationality_en' => ['nullable', 'string', 'max:80'],
            'members.*.nationality_kh' => ['nullable', 'string', 'max:80'],
            'members.*.nationality_country_id' => ['nullable', 'exists:tb_country,id'],
            'members.*.phone' => ['nullable', 'string', 'max:50'],
            'members.*.workplace' => ['nullable', 'string', 'max:160'],
            'members.*.email' => ['nullable', 'email', 'max:150'],
        ], [
            'family_number.unique' => 'This Family Number already exists. Please use another Family Number.',
        ]);

        $family = $id ? Family::findOrFail($id) : new Family();
        $family->fill($validated)->save();

        foreach (FamilyMember::RELATIONSHIP_TYPES as $relationship) {
            $memberData = $validated['members'][$relationship] ?? [];
            if (! collect($memberData)->except('status')->contains(fn ($value) => filled($value))) {
                continue;
            }
            $occupation = ! empty($memberData['occupation_id'])
                ? Occupation::find($memberData['occupation_id'])
                : null;
            $nationality = ! empty($memberData['nationality_country_id'])
                ? Country::find($memberData['nationality_country_id'])
                : null;
            $member = $family->members()->firstOrNew(['relationship_type' => $relationship]);
            $member->fill([
                'full_name_en' => $memberData['full_name_en'] ?? null,
                'full_name_kh' => $memberData['full_name_kh'] ?? null,
                'occupation_id' => $memberData['occupation_id'] ?? null,
                'occupation_en' => $occupation?->occupation_name_en ?? $memberData['occupation_en'] ?? null,
                'occupation_kh' => $occupation?->occupation_name_kh ?? $memberData['occupation_kh'] ?? null,
                'nationality_country_id' => $memberData['nationality_country_id'] ?? null,
                'nationality_en' => $nationality?->nationality_name_en ?? $nationality?->country_name_en ?? $memberData['nationality_en'] ?? null,
                'nationality_kh' => $nationality?->nationality_name_kh ?? $memberData['nationality_kh'] ?? null,
                'phone' => $memberData['phone'] ?? null,
                'workplace' => $memberData['workplace'] ?? null,
                'email' => $memberData['email'] ?? null,
            ])->save();
        }

        return response()->json(['status' => 'success', 'message' => $id ? 'Family updated successfully.' : 'Family created successfully.', 'data' => $family], $id ? 200 : 201);
    }

    public function delete(Family $family)
    {
        abort_if($family->students()->exists(), 422, 'A family with linked students cannot be deleted.');
        $family->delete();

        return response()->json(['status' => 'success', 'message' => 'Family deleted successfully.']);
    }

    public function changeStudentFamily(Request $request)
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:tb_student,id'],
            'family_id' => ['required', 'exists:tb_family,id'],
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $family = Family::findOrFail($validated['family_id']);

        DB::transaction(function () use ($student, $family) {
            DB::table('tb_family_student')->where('student_id', $student->id)->delete();
            $family->students()->attach($student->id, [
                'relationship_type' => 'parent',
                'is_primary_contact' => false,
                'has_pickup_authorization' => false,
                'has_portal_access' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $student->update(['family_number' => $family->family_number]);
        });

        return response()->json([
            'status' => 'success',
            'message' => "Student moved to family {$family->family_number}.",
        ]);
    }
}
