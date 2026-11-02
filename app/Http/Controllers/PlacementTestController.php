<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\BrandingSetting;
use App\Models\Grade;
use App\Models\PlacementTestApplicant;
use App\Models\PlacementTestAttempt;
use App\Models\PlacementTestEvent;
use App\Models\PlacementTestForm;
use App\Models\PlacementTestQuestion;
use App\Models\PlacementTestScan;
use App\Models\PlacementTestSetting;
use App\Models\PlacementTestSubject;
use App\Models\PlacementTestTopic;
use App\Models\SchoolInfo;
use App\Services\PlacementTests;
use App\Support\PhoneNumber;
use App\Support\PlacementTestPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlacementTestController
{
    public function __construct(private PlacementTests $tests) {}

    private function section(Request $request): string
    {
        return PlacementTestPermissions::section($request->user(), $request->route('section') ?? $request->query('section'));
    }

    private function campuses(Request $request, string $action = 'view')
    {
        return PlacementTestPermissions::campusIds($request->user(), $action);
    }

    public function index(Request $request, ?string $section = null)
    {
        $section = $this->section($request);
        $permissions = collect(array_keys(PlacementTestPermissions::catalog()))->mapWithKeys(fn ($action) => [$action => PlacementTestPermissions::allows($request->user(), $action)])->all();

        return view('placement-tests.index', compact('section', 'permissions'));
    }

    public function data(Request $request)
    {
        $section = $this->section($request);
        $campuses = $this->campuses($request, PlacementTestPermissions::SECTIONS[$section][2]);
        $search = trim((string) $request->query('search'));
        $base = PlacementTestApplicant::with(['academicYear', 'campus', 'targetGrade', 'recommendedGrade', 'attempts' => fn ($q) => $q->with('form')->latest('id')])->whereIn('campus_id', $campuses)
            ->when($request->filled('academic_year_id'), fn ($q) => $q->where('academic_year_id', $request->integer('academic_year_id')))->when($request->filled('campus_id'), fn ($q) => $q->where('campus_id', $request->integer('campus_id')))
            ->when($request->filled('grade_id'), fn ($q) => $q->where('target_grade_id', $request->integer('grade_id')))->when($search, fn ($q) => $q->where(fn ($x) => $x->where('application_code', 'like', "%{$search}%")->orWhere('full_name_en', 'like', "%{$search}%")->orWhere('full_name_kh', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")));
        $settingsCampus = $request->filled('campus_id') ? $request->integer('campus_id') : (int) $campuses->first();
        $meta = ['academic_years' => AcademicYear::regular()->orderByDesc('academic_year')->get(['id', 'academic_year', 'lifecycle_status']), 'campuses' => SchoolInfo::whereIn('id', $campuses)->orderBy('campus_name_en')->get(['id', 'campus_name_en']), 'grades' => Grade::where('status', 1)->orderByRaw('CAST(grade_order AS UNSIGNED)')->get(['id', 'grade', 'grade_short_name', 'grade_order']),
            'subjects' => PlacementTestSubject::with('grades:id,grade,grade_short_name,grade_order')->where('status', 'active')->orderBy('display_order')->orderBy('name_en')->get(), 'topics' => PlacementTestTopic::with('grades:id,grade,grade_short_name,grade_order')->where('status', 'active')->orderBy('display_order')->orderBy('name_en')->get(),
            'published_forms' => PlacementTestForm::with('grade')->where('status', 'published')->orderBy('grade_id')->orderBy('form_code')->get(), 'defaults' => PlacementTestSetting::effective($settingsCampus)];
        $summary = ['applicants' => (clone $base)->count(), 'testing' => (clone $base)->whereIn('status', ['invited', 'testing'])->count(), 'passed' => (clone $base)->whereIn('status', ['passed', 'enrollment_ready', 'enrolled'])->count(), 'enrollment_ready' => (clone $base)->where('status', 'enrollment_ready')->count()];
        $payload = match ($section) {
            'questions' => ['questions' => PlacementTestQuestion::with('grade')->withCount('forms')->when($request->filled('grade_id'), fn ($q) => $q->where('grade_id', $request->integer('grade_id')))->when($search, fn ($q) => $q->where(fn ($x) => $x->where('prompt', 'like', "%{$search}%")->orWhere('subject', 'like', "%{$search}%")->orWhere('topic', 'like', "%{$search}%")))->latest('id')->limit(500)->get()],
            'forms' => ['forms' => PlacementTestForm::with(['grade', 'questions'])->withCount('attempts')->when($request->filled('grade_id'), fn ($q) => $q->where('grade_id', $request->integer('grade_id')))->when($search, fn ($q) => $q->where(fn ($x) => $x->where('name', 'like', "%{$search}%")->orWhere('form_code', 'like', "%{$search}%")))->latest('id')->limit(300)->get(), 'questions' => PlacementTestQuestion::with('grade')->whereIn('status', ['approved', 'published'])->orderBy('grade_id')->orderBy('subject')->get()],
            'sessions' => ['attempts' => $this->attempts($request, ['assigned', 'in_progress', 'submitted', 'verified', 'invalidated'])],
            'marking' => ['attempts' => $this->attempts($request, ['assigned', 'in_progress', 'submitted', 'verified']), 'scans' => PlacementTestScan::with(['attempt.applicant', 'attempt.form.grade'])->whereHas('attempt.applicant', fn ($q) => $this->filterApplicants($q, $request, $campuses))->latest('id')->limit(300)->get()],
            'results' => ['attempts' => $this->attempts($request, ['verified'])],
            'enrollment' => ['applicants' => (clone $base)->whereIn('status', ['passed', 'enrollment_ready', 'enrolled'])->latest('id')->limit(500)->get()],
            'settings' => $this->settingsPayload($request, $campuses),
            default => ['applicants' => (clone $base)->latest('id')->limit(500)->get(), 'recent_attempts' => $this->attempts($request, ['assigned', 'in_progress', 'submitted', 'verified', 'invalidated'])->take(8)->values()],
        };

        return response()->json($payload + compact('meta', 'summary'));
    }

    private function settingsPayload(Request $request, $campuses): array
    {
        $campusId = $request->filled('campus_id') ? $request->integer('campus_id') : (int) $campuses->first();
        abort_unless($campuses->contains($campusId), 403);
        $activity = PlacementTestEvent::with('user:id,name')->where(fn ($q) => $q->whereHas('applicant', fn ($applicants) => $applicants->where('campus_id', $campusId))->orWhere('details->campus_id', $campusId))->latest('id')->limit(20)->get();

        return ['settings' => PlacementTestSetting::effective($campusId), 'subjects' => PlacementTestSubject::with(['grades:id,grade,grade_short_name,grade_order'])->withCount('topics')->orderBy('display_order')->orderBy('name_en')->get(), 'topics' => PlacementTestTopic::with(['subject:id,code,name_en,name_kh,status', 'grades:id,grade,grade_short_name,grade_order'])->orderBy('display_order')->orderBy('name_en')->get(), 'activity' => $activity];
    }

    private function filterApplicants($query, Request $request, $campuses)
    {
        $search = trim((string) $request->query('search'));

        return $query->whereIn('campus_id', $campuses)->when($request->filled('academic_year_id'), fn ($q) => $q->where('academic_year_id', $request->integer('academic_year_id')))->when($request->filled('campus_id'), fn ($q) => $q->where('campus_id', $request->integer('campus_id')))->when($request->filled('grade_id'), fn ($q) => $q->where('target_grade_id', $request->integer('grade_id')))->when($search, fn ($q) => $q->where(fn ($x) => $x->where('application_code', 'like', "%{$search}%")->orWhere('full_name_en', 'like', "%{$search}%")->orWhere('full_name_kh', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")));
    }

    private function attempts(Request $request, array $statuses)
    {
        $campuses = $this->campuses($request);

        return PlacementTestAttempt::with(['applicant.academicYear', 'applicant.campus', 'applicant.targetGrade', 'form.grade', 'answers', 'scans'])->whereIn('status', $statuses)->whereHas('applicant', fn ($q) => $this->filterApplicants($q, $request, $campuses))->latest('id')->limit(500)->get();
    }

    public function saveApplicant(Request $request, ?PlacementTestApplicant $applicant = null)
    {
        $data = $request->validate(['academic_year_id' => 'required|exists:tb_academic_year,id', 'campus_id' => 'required|exists:tb_school_info,id', 'target_grade_id' => 'required|exists:tb_grade,id', 'full_name_en' => 'required|string|max:160', 'full_name_kh' => 'nullable|string|max:160', 'gender' => 'nullable|in:Male,Female,male,female,ប,ស,ប្រុស,ស្រី', 'date_of_birth' => 'nullable|date|before:today', 'phone' => 'nullable|string|max:50', 'email' => 'nullable|email|max:150', 'previous_school' => 'nullable|string|max:200', 'parent_name' => 'nullable|string|max:160', 'parent_phone' => 'nullable|string|max:50', 'notes' => 'nullable|string|max:5000']);
        foreach (['phone', 'parent_phone'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = PhoneNumber::normalize($data[$field]);
            }
        }

return response()->json(['message' => $applicant ? 'Applicant updated.' : 'Applicant created.', 'applicant' => $this->tests->saveApplicant($request->user(), $data, $applicant)]);
    }

    public function archiveApplicant(Request $request, PlacementTestApplicant $applicant)
    {
        return response()->json(['message' => 'Applicant archived.', 'applicant' => $this->tests->archiveApplicant($request->user(), $applicant)]);
    }

    public function saveQuestion(Request $request, ?PlacementTestQuestion $question = null)
    {
        $data = $request->validate(['grade_id' => 'required|exists:tb_grade,id', 'subject_id' => 'required|exists:tb_placement_test_subject,id', 'topic_id' => 'required|exists:tb_placement_test_topic,id', 'difficulty' => 'required|in:easy,medium,difficult', 'type' => 'required|in:multiple_choice,true_false,fill_blank,short_answer,essay', 'prompt' => 'required|string|max:10000', 'option_label_style' => 'nullable|in:letters,numbers', 'options' => 'nullable|array|max:26', 'options.*' => 'nullable|string|max:1000', 'correct_answer' => 'nullable|string|max:10000', 'default_points' => 'required|numeric|min:0.01|max:1000', 'explanation' => 'nullable|string|max:5000', 'status' => 'required|in:draft,review,approved,archived']);

        return response()->json(['message' => 'Question saved.', 'question' => $this->tests->saveQuestion($request->user(), $data, $question)]);
    }

    public function duplicateQuestion(Request $request, PlacementTestQuestion $question)
    {
        return response()->json(['message' => 'Question duplicated as a draft.', 'question' => $this->tests->duplicateQuestion($request->user(), $question)]);
    }

    public function archiveQuestion(Request $request, PlacementTestQuestion $question)
    {
        return response()->json(['message' => 'Question archived.', 'question' => $this->tests->archiveQuestion($request->user(), $question)]);
    }

    public function saveForm(Request $request, ?PlacementTestForm $form = null)
    {
        $data = $request->validate(['grade_id' => 'required|exists:tb_grade,id', 'name' => 'required|string|max:160', 'form_code' => 'required|in:A,B,C,a,b,c', 'revision' => 'required|integer|min:1|max:999', 'duration_minutes' => 'required|integer|min:5|max:360', 'max_score' => 'required|numeric|min:1|max:1000', 'pass_score' => 'required|numeric|min:0|max:1000', 'subject_rules' => 'nullable|array', 'subject_rules.*.subject' => 'required|string|max:100', 'subject_rules.*.max_score' => 'required|numeric|min:0.01', 'subject_rules.*.pass_score' => 'required|numeric|min:0', 'subject_rules.*.required' => 'nullable|boolean', 'instructions' => 'nullable|string|max:10000']);
        $data['status'] = $form?->status ?? 'draft';

        return response()->json(['message' => 'Test form saved.', 'form' => $this->tests->saveForm($request->user(), $data, $form)]);
    }

    public function formQuestions(Request $request, PlacementTestForm $form)
    {
        $data = $request->validate(['questions' => 'required|array|min:1|max:100', 'questions.*.question_id' => 'required|integer|distinct|exists:tb_placement_test_question,id', 'questions.*.points' => 'required|numeric|min:0.01|max:1000']);

        return response()->json(['message' => 'Questions assigned to the test form.', 'form' => $this->tests->syncFormQuestions($request->user(), $form, $data['questions'])]);
    }

    public function formStatus(Request $request, PlacementTestForm $form)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'review', 'approved', 'published', 'archived'])]]);

        return response()->json(['message' => 'Test form status updated.', 'form' => $this->tests->changeFormStatus($request->user(), $form, $data['status'])]);
    }

    public function duplicateForm(Request $request, PlacementTestForm $form)
    {
        return response()->json(['message' => 'A new editable form revision was created.', 'form' => $this->tests->duplicateForm($request->user(), $form)]);
    }

    public function assign(Request $request, PlacementTestApplicant $applicant)
    {
        $data = $request->validate(['form_id' => 'required|exists:tb_placement_test_form,id', 'method' => 'required|in:online,paper,manual']);
        $attempt = $this->tests->assign($request->user(), $applicant, PlacementTestForm::findOrFail($data['form_id']), $data['method']);

        return response()->json(['message' => 'Placement test assigned.', 'attempt' => $attempt, 'online_url' => route('placement-test.take', $attempt->access_token), 'print_url' => route('placement-tests.attempt.print', [$attempt, 'document' => 'package'])]);
    }

    public function manualScore(Request $request, PlacementTestAttempt $attempt)
    {
        $this->tests->authorize($request->user(), 'score', $attempt->applicant->campus_id);
        $data = $request->validate(['responses' => 'required|array', 'scores' => 'nullable|array', 'scores.*' => 'nullable|numeric|min:0|max:1000']);

        return response()->json(['message' => 'Responses marked and scored.', 'attempt' => $this->tests->submit($attempt, $data['responses'], 'manual', $request->user(), $data['scores'] ?? [])]);
    }

    public function scan(Request $request, PlacementTestAttempt $attempt)
    {
        $request->validate(['image' => 'required|image|mimes:jpeg,jpg,png,webp|max:20480']);

        return response()->json(['message' => 'Answer sheet scanned. Review flagged answers before confirmation.', 'scan' => $this->tests->uploadScan($request->user(), $attempt, $request->file('image'))]);
    }

    public function verifyScan(Request $request, PlacementTestScan $scan)
    {
        $data = $request->validate(['responses' => 'required|array', 'reason' => 'nullable|string|max:1000']);

        return response()->json(['message' => 'Scanned answers verified and scored.', 'attempt' => $this->tests->verifyScan($request->user(), $scan, $data['responses'], $data['reason'] ?? '')]);
    }

    public function invalidateAttempt(Request $request, PlacementTestAttempt $attempt)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000']);

        return response()->json(['message' => 'Test session invalidated.', 'attempt' => $this->tests->invalidateAttempt($request->user(), $attempt, $data['reason'])]);
    }

    public function print(Request $request, PlacementTestAttempt $attempt)
    {
        $this->tests->authorize($request->user(), 'assign', $attempt->applicant->campus_id);
        $document = $request->query('document', 'package');
        abort_unless(in_array($document, ['package', 'questions', 'answer-sheet', 'answer-key'], true), 404);
        $attempt->load(['applicant.academicYear', 'applicant.campus', 'form.grade']);
        $branding = BrandingSetting::current();
        $path = $branding->report_logo_1_path ?: 'school_logo/student_profile_report_logo.png';
        $file = storage_path('app/public/'.$path);
        $logo = is_file($file) ? 'data:'.(mime_content_type($file) ?: 'image/png').';base64,'.base64_encode(file_get_contents($file)) : null;
        $svg = $this->tests->answerSheetSvg($attempt, $logo);

        return view('placement-tests.print', compact('attempt', 'document', 'svg', 'logo'));
    }

    public function prepareEnrollment(Request $request, PlacementTestApplicant $applicant)
    {
        $this->tests->authorize($request->user(), 'enroll', $applicant->campus_id);
        if (! PlacementTestSetting::effective($applicant->campus_id)['enrollment_handoff_enabled']) {
            return response()->json(['message' => 'Enrollment handoff is disabled for this campus.'], 422);
        }$attempt = $applicant->attempts()->where('result', 'passed')->where('status', 'verified')->latest('id')->firstOrFail();
        if ($applicant->enrolled_student_id) {
            return response()->json(['message' => 'This applicant is already linked to an enrolled student.'], 422);
        }$applicant->update(['status' => 'enrollment_ready']);
        $this->tests->event($request->user(), 'sent-to-enrollment', $applicant, $attempt);

        return response()->json(['message' => 'Applicant is ready for enrollment.', 'url' => route('studentEnrollment.index', ['placement_attempt' => $attempt->id])]);
    }

    public function saveSubject(Request $request, ?PlacementTestSubject $subject = null)
    {
        $this->tests->authorize($request->user(), 'settings');
        $creating = ! $subject;
        $data = $request->validate(['code' => ['required', 'string', 'max:40', Rule::unique('tb_placement_test_subject', 'code')->ignore($subject?->id)], 'name_en' => 'required|string|max:160', 'name_kh' => 'required|string|max:160', 'description' => 'nullable|string|max:5000', 'grade_ids' => 'required|array|min:1', 'grade_ids.*' => 'required|integer|distinct|exists:tb_grade,id', 'display_order' => 'required|integer|min:0|max:9999', 'status' => 'required|in:active,inactive']);
        $grades = collect($data['grade_ids'])->map(fn ($id) => (int) $id)->all();
        unset($data['grade_ids']);
        $data['code'] = mb_strtoupper(trim($data['code']));
        $data['updated_by'] = $request->user()->id;
        $data['created_by'] = $subject?->created_by ?? $request->user()->id;
        $subject = DB::transaction(function () use ($subject, $data, $grades) {
            $subject ??= new PlacementTestSubject;
            $subject->fill($data)->save();
            $subject->grades()->sync($grades);
            $subject->topics()->with('grades')->get()->each(fn ($topic) => $topic->grades()->sync($topic->grades->pluck('id')->intersect($grades)->all()));

            return $subject->fresh(['grades']);
        });

        return response()->json(['message' => $creating ? 'Subject created.' : 'Subject updated.', 'subject' => $subject]);
    }

    public function subjectStatus(Request $request, PlacementTestSubject $subject)
    {
        $this->tests->authorize($request->user(), 'settings');
        $data = $request->validate(['status' => 'required|in:active,inactive']);
        $subject->update(['status' => $data['status'], 'updated_by' => $request->user()->id]);

        return response()->json(['message' => 'Subject status updated.', 'subject' => $subject->fresh('grades')]);
    }

    public function saveTopic(Request $request, ?PlacementTestTopic $topic = null)
    {
        $this->tests->authorize($request->user(), 'settings');
        $creating = ! $topic;
        $data = $request->validate(['code' => ['required', 'string', 'max:50', Rule::unique('tb_placement_test_topic', 'code')->ignore($topic?->id)], 'name_en' => 'required|string|max:180', 'name_kh' => 'required|string|max:180', 'subject_id' => 'required|exists:tb_placement_test_subject,id', 'grade_ids' => 'required|array|min:1', 'grade_ids.*' => 'required|integer|distinct|exists:tb_grade,id', 'description' => 'nullable|string|max:5000', 'difficulty' => 'nullable|in:easy,medium,difficult', 'status' => 'required|in:active,inactive', 'display_order' => 'required|integer|min:0|max:9999']);
        $subject = PlacementTestSubject::with('grades:id')->findOrFail($data['subject_id']);
        $grades = collect($data['grade_ids'])->map(fn ($id) => (int) $id);
        if ($grades->diff($subject->grades->pluck('id'))->isNotEmpty()) {
            throw ValidationException::withMessages(['grade_ids' => 'Every topic grade must also be assigned to its subject.']);
        }unset($data['grade_ids']);
        $data['code'] = mb_strtoupper(trim($data['code']));
        $data['updated_by'] = $request->user()->id;
        $data['created_by'] = $topic?->created_by ?? $request->user()->id;
        $topic = DB::transaction(function () use ($topic, $data, $grades) {
            $topic ??= new PlacementTestTopic;
            $topic->fill($data)->save();
            $topic->grades()->sync($grades->all());

            return $topic->fresh(['subject', 'grades']);
        });

        return response()->json(['message' => $creating ? 'Curriculum topic created.' : 'Curriculum topic updated.', 'topic' => $topic]);
    }

    public function topicStatus(Request $request, PlacementTestTopic $topic)
    {
        $this->tests->authorize($request->user(), 'settings');
        $data = $request->validate(['status' => 'required|in:active,inactive']);
        $topic->update(['status' => $data['status'], 'updated_by' => $request->user()->id]);

        return response()->json(['message' => 'Curriculum topic status updated.', 'topic' => $topic->fresh(['subject', 'grades'])]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate(['campus_id' => 'required|exists:tb_school_info,id', 'online_enabled' => 'required|boolean', 'paper_enabled' => 'required|boolean', 'manual_enabled' => 'required|boolean', 'scan_enabled' => 'required|boolean', 'show_result_to_candidate' => 'required|boolean', 'enrollment_handoff_enabled' => 'required|boolean', 'max_attempts' => 'required|integer|min:1|max:10', 'default_duration_minutes' => 'required|integer|min:5|max:360', 'default_max_score' => 'required|numeric|min:1|max:1000', 'default_pass_percentage' => 'required|numeric|min:0|max:100', 'online_grace_minutes' => 'required|integer|min:0|max:30', 'candidate_instructions' => 'nullable|string|max:5000']);
        $this->tests->authorize($request->user(), 'settings', (int) $data['campus_id']);
        $setting = PlacementTestSetting::updateOrCreate(['campus_id' => $data['campus_id']], $data + ['updated_by' => $request->user()->id]);
        $this->tests->event($request->user(), 'settings-updated', null, null, ['campus_id' => $setting->campus_id]);

        return response()->json(['message' => 'Placement Test settings saved.', 'settings' => $setting]);
    }

    public function take(string $token)
    {
        $attempt = PlacementTestAttempt::with(['applicant', 'form.grade', 'answers'])->where('access_token', $token)->firstOrFail();
        abort_if($attempt->method !== 'online', 404);
        $public = $this->publicAttempt($attempt);

        return view('placement-tests.take', compact('public', 'token'));
    }

    public function startOnline(Request $request, string $token)
    {
        $attempt = PlacementTestAttempt::where('access_token', $token)->firstOrFail();
        abort_if($attempt->method !== 'online', 404);
        $attempt = $this->tests->start($attempt);

        return response()->json($this->publicAttempt($attempt));
    }

    public function submitOnline(Request $request, string $token)
    {
        $attempt = PlacementTestAttempt::where('access_token', $token)->firstOrFail();
        abort_if($attempt->method !== 'online', 404);
        $data = $request->validate(['responses' => 'required|array']);
        $attempt = $this->tests->submit($attempt, $data['responses'], 'online');
        $settings = PlacementTestSetting::effective($attempt->applicant->campus_id);
        $result = ['message' => 'Your placement test was submitted successfully.'];
        if ($settings['show_result_to_candidate']) {
            $result += ['result' => $attempt->result, 'score' => $attempt->score, 'max_score' => $attempt->max_score, 'percentage' => $attempt->percentage, 'subject_scores' => $attempt->subject_scores];
        }

return response()->json($result);
    }

    private function publicAttempt(PlacementTestAttempt $attempt): array
    {
        $snapshot = $attempt->snapshot;
        $snapshot['questions'] = collect($snapshot['questions'] ?? [])->map(fn ($q) => collect($q)->except(['correct_answer', 'explanation'])->all())->values()->all();

        return ['applicant' => ['name' => $attempt->applicant->full_name_en, 'code' => $attempt->applicant->application_code], 'form' => $snapshot, 'status' => $attempt->status, 'started_at' => $attempt->started_at?->toISOString(), 'expires_at' => $attempt->expires_at?->toISOString(), 'responses' => $attempt->answers->pluck('answer','question_order')];
    }
}
