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
use App\Support\FamilyPermissions;
use App\Services\FamilyHistoryRecorder;
use App\Services\FamilyStudentLinks;

class FamilyController
{
    public function index()
    {
        $this->authorize('view');
        return view('families', [
            'familyPermissions' => FamilyPermissions::current()->flags(),
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
        $this->authorize('view');
        if (SettingsQuery::search($request) !== '') $this->authorize('search');
        $families = $this->familyQuery($request)->paginate(SettingsQuery::perPage($request));
        $families->getCollection()->each(fn ($family) => $this->decorate($family));
        return response()->json($families);
    }

    private function familyQuery(Request $request)
    {
        $search = SettingsQuery::search($request);
        $sortBy = $request->query('sort', 'family_number');
        $sortDir = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        return Family::query()
            ->with([
                'members' => fn ($query) => $query->whereIn('relationship_type', FamilyMember::RELATIONSHIP_TYPES),
                'students:id,student_no,student_id,full_name_en,full_name_kh,photo_path,gender,gender_kh,date_of_birth,status',
                'students.enrollments.academicYear' => fn ($q) => $q->withTrashed(),
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
                ->orderBy('family_number', $sortDir));
    }

    public function show(Family $family)
    {
        $this->authorize('view');
        $family->load(['students.enrollments.academicYear','students.enrollments.campus','members']);
        $this->decorate($family);
        return response()->json(['data' => $family]);
    }

    public function save(Request $request)
    {
        $id = $request->integer('family_id') ?: null;
        $existing = $id ? Family::findOrFail($id) : null;
        $access = FamilyPermissions::current();
        abort_unless($existing ? $access->familyAction($existing, 'update') : $access->allows('create'), 403);
        if ($existing && !$request->exists('status')) $request->merge(['status' => $existing->status]);
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

        $family = DB::transaction(function () use ($id, $validated, $access) {
            $family = $id ? Family::lockForUpdate()->findOrFail($id) : new Family();
            if ($id) abort_unless($access->familyAction($family, 'update'), 403);
            if ($id && (bool) $validated['status'] !== (bool) $family->status) abort_unless($access->allows('status'), 403);
            if (!$id && !(bool) $validated['status']) abort_unless($access->allows('status'), 403);
            $audit = app(FamilyHistoryRecorder::class);
            $before = $id ? $audit->snapshot($family) : null;
            $oldNumber = $family->family_number;
            $family->fill(collect($validated)->except('members')->all())->save();
            if ($id && $oldNumber !== $family->family_number) $family->students()->update(['family_number' => $family->family_number]);

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
                $memberValues = [
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
                ];
                $memberKeys = array_keys($memberData);
                if (array_key_exists('occupation_id', $memberData)) $memberKeys = [...$memberKeys,'occupation_en','occupation_kh'];
                if (array_key_exists('nationality_country_id', $memberData)) $memberKeys = [...$memberKeys,'nationality_en','nationality_kh'];
                $member->fill(array_intersect_key($memberValues, array_flip($memberKeys)));
                if ($id && $member->isDirty()) abort_unless($access->familyAction($family, $member->exists ? 'members-update' : 'members-create'), 403);
                $member->save();
            }
            app(FamilyStudentLinks::class)->refreshFamilyMembers($family);
            $audit->record($family, $id ? 'family_updated' : 'family_created', $before, $audit->snapshot($family));
            return $family;
        });

        return response()->json(['status' => 'success', 'message' => $id ? 'Family updated successfully.' : 'Family created successfully.', 'data' => $family], $id ? 200 : 201);
    }

    public function delete(Family $family)
    {
        $this->authorize('delete');
        DB::transaction(function () use ($family) {
            $family = Family::lockForUpdate()->findOrFail($family->id);
            abort_if(app(FamilyStudentLinks::class)->hasStudents($family), 422, 'A family with linked students cannot be deleted.');
            $audit = app(FamilyHistoryRecorder::class);
            $before = $audit->snapshot($family);
            $family->delete();
            $audit->record($family, 'family_deleted', $before, null);
        });

        return response()->json(['status' => 'success', 'message' => 'Family deleted successfully.']);
    }

    public function changeStudentFamily(Request $request)
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:tb_student,id'],
            'family_id' => ['required', 'exists:tb_family,id'],
        ]);

        $family = DB::transaction(function () use ($validated) {
            $student = Student::lockForUpdate()->findOrFail($validated['student_id']);
            $access = FamilyPermissions::current();
            abort_unless($access->studentAction($student, 'link'), 403);
            $oldFamilies = $student->families()->get();
            $oldIds = $oldFamilies->modelKeys();
            if (collect($oldIds)->contains(fn ($id) => (int) $id !== (int) $validated['family_id'])) {
                abort_unless($access->studentAction($student, 'unlink'), 403);
            }
            $families = Family::whereIn('id', array_unique([...$oldIds, $validated['family_id']]))->orderBy('id')->lockForUpdate()->get();
            $family = $families->firstWhere('id', $validated['family_id']);
            abort_unless($family, 404);
            if (filled($student->family_number) && $student->family_number !== $family->family_number) {
                abort_unless($access->studentAction($student,'unlink'), 403);
            }
            if (count($oldIds) === 1 && (int) $oldIds[0] === (int) $family->id && $student->family_number === $family->family_number) return $family;
            $audit = app(FamilyHistoryRecorder::class);
            $before = $families->mapWithKeys(fn ($item) => [$item->id => $audit->snapshot($item)]);
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
            app(FamilyStudentLinks::class)->refreshStudentMembers($student);
            foreach ($families as $item) $audit->record($item, $item->id === $family->id ? 'student_linked' : 'student_unlinked',
                $before[$item->id], $audit->snapshot($item));
            return $family;
        });

        return response()->json([
            'status' => 'success',
            'message' => "Student moved to family {$family->family_number}.",
        ]);
    }

    public function unlinkStudent(Family $family, Student $student)
    {
        DB::transaction(function () use ($family, $student) {
            $student = Student::lockForUpdate()->findOrFail($student->id);
            abort_unless(FamilyPermissions::current()->studentAction($student, 'unlink'), 403);
            $family = Family::lockForUpdate()->findOrFail($family->id);
            abort_unless($family->students()->whereKey($student->id)->exists(), 404);
            $audit = app(FamilyHistoryRecorder::class);
            $before = $audit->snapshot($family);
            $family->students()->detach($student->id);
            $remaining = $student->families()->orderBy('tb_family.id')->first();
            if ($student->family_number === $family->family_number) $student->update(['family_number' => $remaining?->family_number]);
            app(FamilyStudentLinks::class)->refreshStudentMembers($student);
            $audit->record($family, 'student_unlinked', $before, $audit->snapshot($family));
        });
        return response()->json(['status' => 'success','message' => 'Student unlinked from this family.']);
    }

    public function linkOptions()
    {
        $access = FamilyPermissions::current();
        abort_unless($access->any('link'), 403);
        $campuses = array_filter($access->campusIds(), fn ($campus) => $access->allows('link', $campus));
        $students = Student::with(['families:id,family_number','enrollments.academicYear','enrollments.campus'])
            ->when(!$access->isCentral(), fn ($q) => $q->whereHas('enrollments', fn ($e) => $e
                ->whereIn('campus_id', $campuses)->where('status',1)->where('enrollment_status','active')
                ->whereHas('academicYear', fn ($year) => $year->where('lifecycle_status','started'))))
            ->orderBy('full_name_en')->get(['id','student_id','student_no','full_name_en','full_name_kh','family_number']);
        return response()->json(['students' => $students->filter(fn ($student) => $access->studentAction($student,'link')
            && (($student->families->isEmpty() && blank($student->family_number)) || $access->studentAction($student,'unlink')))
            ->map(fn ($student) => $student->only(['id','student_id','student_no','full_name_en','full_name_kh']))->values()]);
    }

    public function history(Family $family)
    {
        $this->authorize('history');
        return response()->json(\App\Models\FamilyHistory::where('family_id',$family->id)->latest('id')->paginate(25));
    }

    public function allHistory()
    {
        $this->authorize('history');
        return response()->json(\App\Models\FamilyHistory::latest('id')->paginate(25));
    }

    public function setStatus(Request $request, Family $family)
    {
        $this->authorize('status');
        $data = $request->validate(['status' => ['required','boolean']]);
        DB::transaction(function () use ($family, $data) {
            $family = Family::lockForUpdate()->findOrFail($family->id);
            $audit = app(FamilyHistoryRecorder::class);
            $before = $audit->snapshot($family);
            $family->update(['status' => $data['status']]);
            $audit->record($family, 'status_changed', $before, $audit->snapshot($family));
        });
        return response()->json(['status' => 'success','message' => 'Family status updated.','active' => (bool) $data['status']]);
    }

    public function report(Request $request)
    {
        $this->authorize('print');
        if (SettingsQuery::search($request) !== '') $this->authorize('search');
        return view('families-report', ['families' => $this->familyQuery($request)->get()]);
    }

    public function export(Request $request)
    {
        $this->authorize('export');
        if (SettingsQuery::search($request) !== '') $this->authorize('search');
        return response()->streamDownload(function () use ($request) {
            $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Family Number','Mother','Mother Phone','Father','Father Phone','Students','Status'], ',', '"', '');
            foreach ($this->familyQuery($request)->lazy(200) as $family) {
                $mother = $family->members->firstWhere('relationship_type','mother');
                $father = $family->members->firstWhere('relationship_type','father');
                $row = [$family->family_number,$mother?->full_name_en,$mother?->phone,$father?->full_name_en,$father?->phone,
                    $family->students->map(fn ($s) => $s->student_id.' '.$s->full_name_en)->implode('; '), $family->status ? 'Active' : 'Inactive'];
                fputcsv($out, array_map(fn ($value) => preg_match('/^[=+@\-\t\r]/', (string) $value) ? "'".$value : ($value ?? ''), $row), ',', '"', '');
            }
            fclose($out);
        }, 'families-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function decorate(Family $family): void
    {
        $access = FamilyPermissions::current();
        foreach (['update','members-create','members-update','members-delete'] as $action) $family->setAttribute('can_'.str_replace('-','_',$action), $access->familyAction($family,$action));
        $family->setAttribute('can_delete', $access->allows('delete') && !app(FamilyStudentLinks::class)->hasStudents($family));
        $family->setAttribute('can_status', $access->allows('status'));
        $family->setAttribute('shared_info', $access->describe($family));
        foreach ($family->students as $student) {
            $student->setAttribute('can_link', $access->studentAction($student,'link'));
            $student->setAttribute('can_unlink', $access->studentAction($student,'unlink'));
        }
    }

    private function authorize(string $action): void
    {
        abort_unless(FamilyPermissions::current()->allows($action), 403);
    }
}
