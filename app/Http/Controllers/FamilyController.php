<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Support\SettingsQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FamilyController
{
    public function index()
    {
        return view('families');
    }

    public function fetchData(Request $request)
    {
        $search = SettingsQuery::search($request);
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
            ->orderBy('family_number')
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
            'members.*.nationality_en' => ['nullable', 'string', 'max:80'],
            'members.*.nationality_kh' => ['nullable', 'string', 'max:80'],
            'members.*.phone' => ['nullable', 'string', 'max:50'],
            'members.*.workplace' => ['nullable', 'string', 'max:160'],
            'members.*.email' => ['nullable', 'email', 'max:150'],
        ]);

        $family = $id ? Family::findOrFail($id) : new Family();
        $family->fill($validated)->save();

        foreach (FamilyMember::RELATIONSHIP_TYPES as $relationship) {
            $memberData = $validated['members'][$relationship] ?? [];
            if (! collect($memberData)->except('status')->contains(fn ($value) => filled($value))) {
                continue;
            }
            $member = $family->members()->firstOrNew(['relationship_type' => $relationship]);
            $member->fill([
                'full_name_en' => $memberData['full_name_en'] ?? null,
                'full_name_kh' => $memberData['full_name_kh'] ?? null,
                'occupation_en' => $memberData['occupation_en'] ?? null,
                'occupation_kh' => $memberData['occupation_kh'] ?? null,
                'nationality_en' => $memberData['nationality_en'] ?? null,
                'nationality_kh' => $memberData['nationality_kh'] ?? null,
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
}
