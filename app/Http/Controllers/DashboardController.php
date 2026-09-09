<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AcademicTrack;
use App\Models\DashboardAssignment;
use App\Models\DashboardTemplate;
use App\Models\DashboardUserPreference;
use App\Models\DashboardWidget;
use App\Models\Grade;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGraduation;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DashboardController
{
    public function index(Request $request)
    {
        $user = $request->user()->load('roles');
        $periodType = in_array($request->query('period_type'), ['regular', 'summer'], true)
            ? $request->query('period_type')
            : 'regular';
        $academicYears = AcademicYear::where('period_type', $periodType)
            ->orderByDesc('academic_year')
            ->get(['id', 'academic_year', 'period_type', 'lifecycle_status']);
        $activeAcademicYear = $academicYears->firstWhere('lifecycle_status', 'started') ?? $academicYears->first();
        $selectedAcademicYearId = $request->integer('academic_year_id') ?: $activeAcademicYear?->id;

        if ($selectedAcademicYearId && !$academicYears->contains('id', $selectedAcademicYearId)) {
            $selectedAcademicYearId = $activeAcademicYear?->id;
        }

        $campuses = $user->accessibleCampuses()
            ->orderBy('campus_name_en')
            ->get(['tb_school_info.id', 'campus_name_en', 'campus_name_kh']);
        $campusIds = $campuses->pluck('id')->all();
        $selectedCampusId = $request->integer('campus_id') ?: null;

        if ($selectedCampusId && !in_array($selectedCampusId, $campusIds, true)) {
            $selectedCampusId = null;
        }

        $template = $this->resolveTemplate($user);
        $templateWidgets = $template?->widgets
            ->filter(fn ($widget) => (bool) $widget->status && (bool) $widget->pivot->status)
            ->values() ?? collect();
        $preference = DashboardUserPreference::where('user_id', $user->id)->first();
        $widgets = $this->personalizedWidgets($templateWidgets, $preference);
        $dashboardSections = $this->dashboardSections($template, $preference);

        $dashboardFilters = [
            'academic_year_id' => $selectedAcademicYearId,
            'campus_id' => $selectedCampusId,
            'period_type' => $periodType,
        ];
        $permissionCampusId = $this->permissionCampusId($user, $selectedCampusId, $campusIds);

        return view('dashboard', [
            'template' => $template,
            'widgets' => $widgets,
            'metrics' => Cache::remember(
                'dashboard.metrics.' . $user->id . '.' . ($user->updated_at?->timestamp ?? 0) . '.' . md5(json_encode($dashboardFilters) . '|' . implode(',', $campusIds)),
                now()->addSeconds(30),
                fn () => $this->metrics($user, $selectedAcademicYearId, $selectedCampusId, $campusIds, $dashboardFilters)
            ),
            'academicYears' => $academicYears,
            'campuses' => $campuses,
            'selectedAcademicYearId' => $selectedAcademicYearId,
            'selectedCampusId' => $selectedCampusId,
            'selectedPeriodType' => $periodType,
            'canSelectCampus' => $user->isSuperAdmin() || $campuses->count() > 1,
            'dashboardSections' => $dashboardSections,
            'isPersonalizedDashboard' => (bool) $preference,
            'canCustomizeDashboard' => $user->hasPermission('dashboard.customize', $permissionCampusId),
        ]);
    }

    public function customize(Request $request)
    {
        $user = $request->user()->load('roles');
        $this->authorizeDashboardPermission($user, 'dashboard.customize');

        $template = $this->resolveTemplate($user);
        $templateWidgets = $template?->widgets
            ->filter(fn ($widget) => (bool) $widget->status && (bool) $widget->pivot->status)
            ->values() ?? collect();
        $preference = DashboardUserPreference::where('user_id', $user->id)->first();
        $selectedWidgets = $this->personalizedWidgets($templateWidgets, $preference);

        return view('dashboard-customize', [
            'template' => $template,
            'availableWidgetPayload' => $this->widgetPayload($templateWidgets),
            'selectedWidgetPayload' => $this->widgetPayload($selectedWidgets),
            'dashboardSections' => $this->dashboardSections($template, $preference),
            'canResetDashboard' => $user->hasPermission('dashboard.reset', $this->permissionCampusId($user)),
        ]);
    }

    public function saveCustomization(Request $request)
    {
        $user = $request->user()->load('roles');
        $this->authorizeDashboardPermission($user, 'dashboard.customize');

        $template = $this->resolveTemplate($user);
        $allowedWidgetIds = ($template?->widgets ?? collect())
            ->filter(fn ($widget) => (bool) $widget->status && (bool) $widget->pivot->status)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $data = $request->validate([
            'sections_payload' => ['nullable', 'string'],
            'widget_ids' => ['nullable', 'array'],
            'widget_ids.*' => ['integer', 'exists:dashboard_widgets,id'],
            'widget_widths' => ['nullable', 'array'],
            'widget_widths.*' => ['nullable', 'in:small,medium,large,full,col-1,col-2,col-3,col-4,col-5,col-6'],
            'widget_sections' => ['nullable', 'array'],
            'widget_sections.*' => ['nullable', 'string', 'max:80'],
            'widget_chart_types' => ['nullable', 'array'],
            'widget_chart_types.*' => ['nullable', 'in:standard,donut,vertical_bar,grouped_bar,horizontal_bar,compact_list'],
        ]);

        $widgets = [];
        foreach (array_values($data['widget_ids'] ?? []) as $index => $widgetId) {
            $widgetId = (int) $widgetId;
            if (!in_array($widgetId, $allowedWidgetIds, true)) {
                continue;
            }

            $widgets[] = [
                'id' => $widgetId,
                'display_order' => $index + 1,
                'width' => $data['widget_widths'][$widgetId] ?? 'medium',
                'section_id' => $data['widget_sections'][$widgetId] ?? 'section-1',
                'chart_type' => $data['widget_chart_types'][$widgetId] ?? 'standard',
            ];
        }

        DashboardUserPreference::updateOrCreate(
            ['user_id' => $user->id],
            [
                'dashboard_template_id' => $template?->id,
                'settings' => [
                    'sections' => $this->normalizedSections($request->input('sections_payload')),
                    'widgets' => $widgets,
                ],
            ]
        );

        return redirect()->route('dashboard')->with('success', 'Your dashboard layout was saved.');
    }

    public function resetCustomization(Request $request)
    {
        $this->authorizeDashboardPermission($request->user(), 'dashboard.reset');

        DashboardUserPreference::where('user_id', $request->user()->id)->delete();

        return redirect()->route('dashboard')->with('success', 'Your dashboard layout was reset to the assigned template.');
    }

    private function authorizeDashboardPermission(User $user, string $permission): void
    {
        abort_unless($user->hasPermission($permission, $this->permissionCampusId($user)), 403);
    }

    private function permissionCampusId(User $user, ?int $selectedCampusId = null, ?array $campusIds = null): ?int
    {
        if ($selectedCampusId) {
            return $selectedCampusId;
        }

        if ($user->active_campus_id) {
            return (int) $user->active_campus_id;
        }

        if ($campusIds !== null) {
            return $campusIds[0] ?? null;
        }

        return $user->accessibleCampuses()->value('tb_school_info.id');
    }

    private function resolveTemplate(User $user): ?DashboardTemplate
    {
        $roleIds = $user->roles->pluck('id')->all();

        $assignment = DashboardAssignment::where('status', 1)
            ->where(function ($query) use ($user, $roleIds) {
                $query->where(function ($userQuery) use ($user) {
                    $userQuery->where('assignment_type', 'user')
                        ->where('assignment_id', $user->id);
                });

                if ($roleIds) {
                    $query->orWhere(function ($roleQuery) use ($roleIds) {
                        $roleQuery->where('assignment_type', 'role')
                            ->whereIn('assignment_id', $roleIds);
                    });
                }

                if ($user->department_id) {
                    $query->orWhere(function ($departmentQuery) use ($user) {
                        $departmentQuery->where('assignment_type', 'department')
                            ->where('assignment_id', $user->department_id);
                    });
                }
            })
            ->whereHas('template', fn ($query) => $query->where('status', 1))
            ->orderBy('priority')
            ->latest('id')
            ->first();

        if ($assignment) {
            return DashboardTemplate::with('widgets')->find($assignment->dashboard_template_id);
        }

        return DashboardTemplate::with('widgets')
            ->where('status', 1)
            ->where('is_default', 1)
            ->orderBy('display_order')
            ->first();
    }

    private function metrics(User $user, ?int $academicYearId, ?int $campusId, array $campusIds, array $dashboardFilters): array
    {
        $activeAcademicYear = AcademicYear::where('period_type', $dashboardFilters['period_type'] ?? 'regular')
            ->where('lifecycle_status', 'started')
            ->orderByDesc('start_date')
            ->first();

        $enrollmentScope = StudentEnrollment::query()
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->when($campusId, fn ($query) => $query->where('campus_id', $campusId))
            ->when(!$campusId, fn ($query) => $query->whereIn('campus_id', $campusIds));

        $recentNotifications = UserNotification::where('user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get();
        $studentFilters = array_filter($dashboardFilters);
        $unreadMessages = $this->unreadChatMessages((int) $user->id);
        $upcomingStaffBirthdays = $this->upcomingStaffBirthdays();
        $studentsByGradeChart = $this->studentsByGradeChart($enrollmentScope);
        $studentStatisticsByCampusGrade = $this->studentStatisticsByCampusGrade($enrollmentScope);
        $studentsByTrackChart = $this->studentsByTrackChart($enrollmentScope);
        $graduatedStudentGenderCounts = $this->graduatedStudentsGenderCounts($academicYearId, $campusId, $campusIds);
        $totalStudentGenderCounts = (clone $enrollmentScope)
            ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
            ->selectRaw("COUNT(DISTINCT CASE WHEN LOWER(COALESCE(tb_student.gender, '')) IN ('male', 'm') OR COALESCE(tb_student.gender_kh, '') LIKE '%ប្រុស%' THEN tb_student_enrollment.student_id END) as male")
            ->selectRaw("COUNT(DISTINCT CASE WHEN LOWER(COALESCE(tb_student.gender, '')) IN ('female', 'f') OR COALESCE(tb_student.gender_kh, '') LIKE '%ស្រី%' THEN tb_student_enrollment.student_id END) as female")
            ->first();
        $newStudentGenderCounts = (clone $enrollmentScope)
            ->where('tb_student_enrollment.student_type', 'new')
            ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
            ->selectRaw("COUNT(DISTINCT CASE WHEN LOWER(COALESCE(tb_student.gender, '')) IN ('male', 'm') OR COALESCE(tb_student.gender_kh, '') LIKE '%ប្រុស%' THEN tb_student_enrollment.student_id END) as male")
            ->selectRaw("COUNT(DISTINCT CASE WHEN LOWER(COALESCE(tb_student.gender, '')) IN ('female', 'f') OR COALESCE(tb_student.gender_kh, '') LIKE '%ស្រី%' THEN tb_student_enrollment.student_id END) as female")
            ->first();
        $withdrawnStudentGenderCounts = (clone $enrollmentScope)
            ->where('tb_student_enrollment.enrollment_status', 'withdrawn')
            ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
            ->selectRaw("COUNT(DISTINCT CASE WHEN LOWER(COALESCE(tb_student.gender, '')) IN ('male', 'm') OR COALESCE(tb_student.gender_kh, '') LIKE '%ប្រុស%' THEN tb_student_enrollment.student_id END) as male")
            ->selectRaw("COUNT(DISTINCT CASE WHEN LOWER(COALESCE(tb_student.gender, '')) IN ('female', 'f') OR COALESCE(tb_student.gender_kh, '') LIKE '%ស្រី%' THEN tb_student_enrollment.student_id END) as female")
            ->first();
        $todayEnrollmentGenderCounts = (clone $enrollmentScope)
            ->whereDate('tb_student_enrollment.created_at', today())
            ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
            ->selectRaw("COUNT(DISTINCT CASE WHEN LOWER(COALESCE(tb_student.gender, '')) IN ('male', 'm') OR COALESCE(tb_student.gender_kh, '') LIKE '%ប្រុស%' THEN tb_student_enrollment.student_id END) as male")
            ->selectRaw("COUNT(DISTINCT CASE WHEN LOWER(COALESCE(tb_student.gender, '')) IN ('female', 'f') OR COALESCE(tb_student.gender_kh, '') LIKE '%ស្រី%' THEN tb_student_enrollment.student_id END) as female")
            ->first();

        // Consolidate the repeated enrollment counters into one aggregate query.
        // These cards previously each executed a separate full-table count.
        $summary = (clone $enrollmentScope)
            ->selectRaw('COUNT(DISTINCT student_id) as total_students')
            ->selectRaw("COUNT(DISTINCT CASE WHEN enrollment_status = 'active' THEN student_id END) as active_students")
            ->selectRaw("SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) as new_enrollments", [today()->toDateString()])
            ->selectRaw("COUNT(DISTINCT CASE WHEN student_type = 'new' THEN student_id END) as new_students")
            ->selectRaw("SUM(CASE WHEN enrollment_status = 'withdrawn' THEN 1 ELSE 0 END) as withdrawn_students")
            ->selectRaw("COUNT(DISTINCT CASE WHEN enrollment_status = 'active' AND class_id IS NOT NULL THEN class_id END) as classes_count")
            ->selectRaw("SUM(CASE WHEN enrollment_status = 'active' THEN 1 ELSE 0 END) as active_status")
            ->selectRaw("SUM(CASE WHEN enrollment_status = 'withdrawn' THEN 1 ELSE 0 END) as withdrawn_status")
            ->selectRaw("SUM(CASE WHEN enrollment_status IN ('completed', 'finished') THEN 1 ELSE 0 END) as completed_status")
            ->first();

        return [
            'total_students' => [
                'value' => number_format((int) ($summary->total_students ?? 0)),
                'subtitle' => 'Students in selected scope',
                'male' => (int) ($totalStudentGenderCounts->male ?? 0),
                'female' => (int) ($totalStudentGenderCounts->female ?? 0),
                'url' => route('searchStudent.index', $studentFilters),
            ],
            'active_students' => [
                'value' => number_format((int) ($summary->active_students ?? 0)),
                'subtitle' => 'Active in selected scope',
                'url' => route('searchStudent.index', $studentFilters),
            ],
            'new_enrollments' => [
                'value' => number_format((int) ($summary->new_enrollments ?? 0)),
                'subtitle' => 'New enrolments today',
                'male' => (int) ($todayEnrollmentGenderCounts->male ?? 0),
                'female' => (int) ($todayEnrollmentGenderCounts->female ?? 0),
                'badge' => today()->format('d M Y'),
                'url' => route('studentEnrollment.index', $studentFilters),
            ],
            'new_students' => [
                'value' => number_format((int) ($summary->new_students ?? 0)),
                'subtitle' => 'New students in selected scope',
                'male' => (int) ($newStudentGenderCounts->male ?? 0),
                'female' => (int) ($newStudentGenderCounts->female ?? 0),
                'url' => route('searchStudent.index', $studentFilters),
            ],
            'withdrawn_students' => [
                'value' => number_format((int) ($summary->withdrawn_students ?? 0)),
                'subtitle' => 'Withdrawn in selected scope',
                'male' => (int) ($withdrawnStudentGenderCounts->male ?? 0),
                'female' => (int) ($withdrawnStudentGenderCounts->female ?? 0),
                'url' => route('studentEnrollment.index', $studentFilters),
            ],
            'graduated_students' => [
                'value' => number_format($this->graduatedStudentsCount($academicYearId, $campusId, $campusIds)),
                'subtitle' => 'Graduated students in selected scope',
                'male' => (int) ($graduatedStudentGenderCounts->male ?? 0),
                'female' => (int) ($graduatedStudentGenderCounts->female ?? 0),
                'url' => route('studentGraduation.index', $studentFilters),
            ],
            'classes_count' => [
                'value' => number_format((int) ($summary->classes_count ?? 0)),
                'subtitle' => 'Active classes in selected scope',
                'url' => route('classes.index'),
            ],
            'dashboard_filters' => [
                'value' => 'Filter',
                'subtitle' => 'Academic year and campus',
                'url' => route('dashboard'),
            ],
            'student_status_chart' => [
                'value' => 'Chart',
                'subtitle' => 'Student status in selected scope',
                'chart' => [
                    ['label' => 'Active', 'value' => (int) ($summary->active_status ?? 0), 'color' => '#2fb344'],
                    ['label' => 'Withdrawn', 'value' => (int) ($summary->withdrawn_status ?? 0), 'color' => '#d63939'],
                    ['label' => 'Completed', 'value' => (int) ($summary->completed_status ?? 0), 'color' => '#206bc4'],
                ],
                'url' => route('studentEnrollment.index', $studentFilters),
            ],
            'enrollment_trend_chart' => [
                'value' => 'Chart',
                'subtitle' => 'Monthly enrollment trend',
                'chart' => collect(range(5, 0))->map(function ($monthsAgo) use ($enrollmentScope) {
                    $date = now()->subMonths($monthsAgo);
                    return [
                        'label' => $date->format('M'),
                        'value' => (clone $enrollmentScope)->whereMonth('created_at', $date->month)->whereYear('created_at', $date->year)->count(),
                        'color' => '#0ca678',
                    ];
                })->all(),
                'url' => route('studentEnrollment.index', $studentFilters),
            ],
            'students_by_grade_chart' => [
                'value' => number_format(collect($studentsByGradeChart)->where('label', 'Total')->first()['value'] ?? 0),
                'subtitle' => 'Students by grade in selected scope',
                'chart' => $studentsByGradeChart,
                'url' => route('studentEnrollment.index', $studentFilters),
            ],
            'students_by_track' => [
                'value' => number_format(collect($studentsByTrackChart)->where('label', 'Total')->first()['value'] ?? 0),
                'subtitle' => 'Students by academic track',
                'chart' => $studentsByTrackChart,
                'url' => route('studentEnrollment.index', $studentFilters),
            ],
            'student_statistics_by_campus_grade' => [
                'value' => number_format($studentStatisticsByCampusGrade['grand_total']['total'] ?? 0),
                'subtitle' => 'Total / Female by campus and grade',
                'table' => $studentStatisticsByCampusGrade,
                'url' => route('studentEnrollment.index', $studentFilters),
            ],
            'dashboard_hero' => [
                'value' => auth()->user()?->name ?: 'My Profile',
                'subtitle' => 'Your staff profile',
                'profiles' => $this->staffProfiles($user->id),
                'url' => route('profile'),
            ],
            'active_academic_year' => [
                'value' => $activeAcademicYear?->academic_year ?? '-',
                'subtitle' => $activeAcademicYear ? 'Started academic year' : 'No started academic year',
                'url' => route('academic-years.index'),
            ],
            'total_staff_users' => [
                'value' => number_format(User::where('status', 1)->count()),
                'subtitle' => 'Active users',
                'url' => route('users.index'),
            ],
            'notifications' => [
                'value' => number_format($recentNotifications->count()),
                'subtitle' => 'Latest notifications',
                'items' => $recentNotifications,
                'url' => route('notifications.index'),
            ],
            'premium_chat' => [
                'value' => number_format($unreadMessages),
                'subtitle' => $unreadMessages ? 'Unread messages' : 'Open staff chat',
                'url' => route('chat.index'),
            ],
            'staff_birthdays' => [
                'value' => number_format($upcomingStaffBirthdays->count()),
                'subtitle' => 'Upcoming staff birthdays',
                'items' => $upcomingStaffBirthdays,
                'url' => route('users.index'),
            ],
            'recent_activities' => [
                'value' => 'Live',
                'subtitle' => 'Recent system activity',
                'items' => $this->recentActivities(),
                'url' => route('dashboard'),
            ],
        ];
    }

    private function recentActivities()
    {
        $student = Student::latest()->first();
        $enrollment = StudentEnrollment::with('student')->latest()->first();
        $user = User::latest()->first();

        return collect([
            $student?->full_name_en ? 'New student: ' . $student->full_name_en : null,
            $enrollment?->student?->full_name_en ? 'Enrollment updated: ' . $enrollment->student->full_name_en : null,
            $user?->name ? 'User updated: ' . $user->name : null,
        ])->filter()->values();
    }

    private function unreadChatMessages(int $userId): int
    {
        return DB::table('chat_messages as messages')
            ->join('chat_conversation_users as readers', function ($join) use ($userId) {
                $join->on('readers.conversation_id', '=', 'messages.conversation_id')
                    ->where('readers.user_id', '=', $userId);
            })
            ->where('messages.user_id', '!=', $userId)
            ->whereNull('messages.deleted_at')
            ->where(function ($query) {
                $query->whereNull('readers.last_read_at')
                    ->orWhereColumn('messages.created_at', '>', 'readers.last_read_at');
            })
            ->count('messages.id');
    }

    private function upcomingStaffBirthdays()
    {
        $today = now()->startOfDay();

        return User::where('status', 1)
            ->whereNotNull('date_of_birth')
            ->get(['name', 'date_of_birth'])
            ->map(function ($user) use ($today) {
                $birthday = $user->date_of_birth->copy()->year((int) $today->year)->startOfDay();

                if ($birthday->lt($today)) {
                    $birthday->addYear();
                }

                return [
                    'label' => $user->name . ' · ' . $birthday->format('d-M'),
                    'days' => $today->diffInDays($birthday),
                ];
            })
            ->sortBy('days')
            ->take(5)
            ->map(fn ($birthday) => $birthday['label'])
            ->values();
    }

    private function studentsByGradeChart($enrollmentScope): array
    {
        $counts = (clone $enrollmentScope)
            ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
            ->select(
                'tb_student_enrollment.grade_id',
                DB::raw('COUNT(DISTINCT tb_student_enrollment.student_id) as total'),
                DB::raw("COUNT(DISTINCT CASE WHEN LOWER(COALESCE(tb_student.gender, '')) IN ('female', 'f') THEN tb_student_enrollment.student_id END) as female")
            )
            ->groupBy('tb_student_enrollment.grade_id')
            ->get()
            ->keyBy('grade_id');

        $rows = Grade::where('status', 1)
            ->orderByRaw('CAST(grade_order AS UNSIGNED)')
            ->orderBy('grade')
            ->get(['id', 'grade', 'grade_short_name'])
            ->map(function ($grade) use ($counts) {
                $item = $counts->get($grade->id);

                return [
                    'label' => $grade->grade_short_name ?: $grade->grade,
                    'value' => (int) ($item->total ?? 0),
                    'secondary_label' => 'Female',
                    'secondary_value' => (int) ($item->female ?? 0),
                    'color' => '#4263eb',
                    'secondary_color' => '#f06595',
                ];
            })
            ->values();

        $rows->push([
            'label' => 'Total',
            'value' => (int) $rows->sum('value'),
            'secondary_label' => 'Female',
            'secondary_value' => (int) $rows->sum('secondary_value'),
            'color' => '#0ca678',
            'secondary_color' => '#f06595',
        ]);

        return $rows->all();
    }

    private function studentsByTrackChart($enrollmentScope): array
    {
        $counts = (clone $enrollmentScope)
            ->where('tb_student_enrollment.enrollment_status', 'active')
            ->whereNotNull('tb_student_enrollment.academic_track_id')
            ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
            ->join('tb_academic_track', 'tb_academic_track.id', '=', 'tb_student_enrollment.academic_track_id')
            ->select(
                'tb_academic_track.stream_type',
                'tb_academic_track.language',
                DB::raw('COUNT(DISTINCT tb_student_enrollment.student_id) as total'),
                DB::raw("COUNT(DISTINCT CASE WHEN LOWER(COALESCE(tb_student.gender, '')) IN ('female', 'f') THEN tb_student_enrollment.student_id END) as female")
            )
            ->groupBy('tb_academic_track.stream_type', 'tb_academic_track.language')
            ->get()
            ->keyBy(fn ($row) => "{$row->stream_type}:{$row->language}");

        $tracks = AcademicTrack::where('status', 1)
            ->select('stream_type', 'language', DB::raw('MIN(name_en) as name_en'))
            ->groupBy('stream_type', 'language')
            ->orderByRaw("FIELD(stream_type, 'science', 'social_science')")
            ->orderByRaw("FIELD(language, 'khmer', 'english')")
            ->get();

        $colors = ['#206bc4', '#4263eb', '#0ca678', '#ae3ec9'];
        $rows = $tracks->map(function ($track, $index) use ($counts, $colors) {
            $key = "{$track->stream_type}:{$track->language}";

            return [
                'label' => $track->name_en ?: $this->trackLabel($track->stream_type, $track->language),
                'value' => (int) ($counts->get($key)->total ?? 0),
                'secondary_label' => 'Female',
                'secondary_value' => (int) ($counts->get($key)->female ?? 0),
                'color' => $colors[$index % count($colors)],
                'secondary_color' => '#f06595',
            ];
        })->values();

        if ($rows->isEmpty()) {
            $rows = collect([
                ['label' => 'Science - Khmer', 'value' => 0, 'secondary_label' => 'Female', 'secondary_value' => 0, 'color' => '#206bc4', 'secondary_color' => '#f06595'],
                ['label' => 'Science - English', 'value' => 0, 'secondary_label' => 'Female', 'secondary_value' => 0, 'color' => '#4263eb', 'secondary_color' => '#f06595'],
                ['label' => 'Social Science - Khmer', 'value' => 0, 'secondary_label' => 'Female', 'secondary_value' => 0, 'color' => '#0ca678', 'secondary_color' => '#f06595'],
                ['label' => 'Social Science - English', 'value' => 0, 'secondary_label' => 'Female', 'secondary_value' => 0, 'color' => '#ae3ec9', 'secondary_color' => '#f06595'],
            ]);
        }

        $rows->push([
            'label' => 'Total',
            'value' => (int) $rows->sum('value'),
            'secondary_label' => 'Female',
            'secondary_value' => (int) $rows->sum('secondary_value'),
            'color' => '#0ca678',
            'secondary_color' => '#f06595',
        ]);

        return $rows->all();
    }

    private function trackLabel(?string $streamType, ?string $language): string
    {
        $stream = $streamType === 'social_science' ? 'Social Science' : 'Science';
        $trackLanguage = $language === 'english' ? 'English' : 'Khmer';

        return "{$stream} - {$trackLanguage}";
    }

    private function graduatedStudentsCount(?int $academicYearId, ?int $campusId, array $campusIds): int
    {
        return StudentGraduation::query()
            ->where('status', 'completed')
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->when($campusId, fn ($query) => $query->where('campus_id', $campusId))
            ->when(!$campusId, fn ($query) => $query->whereIn('campus_id', $campusIds))
            ->distinct('student_id')
            ->count('student_id');
    }

    private function graduatedStudentsGenderCounts(?int $academicYearId, ?int $campusId, array $campusIds): object
    {
        return StudentGraduation::query()
            ->where('tb_student_graduation.status', 'completed')
            ->when($academicYearId, fn ($query) => $query->where('tb_student_graduation.academic_year_id', $academicYearId))
            ->when($campusId, fn ($query) => $query->where('tb_student_graduation.campus_id', $campusId))
            ->when(!$campusId, fn ($query) => $query->whereIn('tb_student_graduation.campus_id', $campusIds))
            ->join('tb_student', 'tb_student.id', '=', 'tb_student_graduation.student_id')
            ->selectRaw("COUNT(DISTINCT CASE WHEN LOWER(COALESCE(tb_student.gender, '')) IN ('male', 'm') THEN tb_student_graduation.student_id END) as male")
            ->selectRaw("COUNT(DISTINCT CASE WHEN LOWER(COALESCE(tb_student.gender, '')) IN ('female', 'f') THEN tb_student_graduation.student_id END) as female")
            ->first();
    }

    private function studentStatisticsByCampusGrade($enrollmentScope): array
    {
        $grades = Grade::where('status', 1)
            ->orderByRaw('CAST(grade_order AS UNSIGNED)')
            ->orderBy('grade')
            ->get(['id', 'grade', 'grade_short_name']);

        $stats = (clone $enrollmentScope)
            ->join('tb_student', 'tb_student.id', '=', 'tb_student_enrollment.student_id')
            ->join('tb_school_info', 'tb_school_info.id', '=', 'tb_student_enrollment.campus_id')
            ->select(
                'tb_student_enrollment.campus_id',
                'tb_school_info.campus_name_en',
                'tb_student_enrollment.grade_id',
                DB::raw('COUNT(DISTINCT tb_student_enrollment.student_id) as total'),
                DB::raw("COUNT(DISTINCT CASE WHEN LOWER(COALESCE(tb_student.gender, '')) IN ('female', 'f') OR COALESCE(tb_student.gender_kh, '') LIKE '%ស្រី%' THEN tb_student_enrollment.student_id END) as female")
            )
            ->groupBy('tb_student_enrollment.campus_id', 'tb_school_info.campus_name_en', 'tb_student_enrollment.grade_id')
            ->orderBy('tb_school_info.campus_name_en')
            ->get();

        $campusStats = $stats->groupBy('campus_id');
        $gradeHeaders = $grades
            ->map(fn ($grade) => [
                'id' => $grade->id,
                'label' => $grade->grade_short_name ?: $grade->grade,
            ])
            ->values();

        $rows = $campusStats->map(function ($items) use ($gradeHeaders) {
            $first = $items->first();
            $byGrade = $items->keyBy('grade_id');
            $rowTotal = 0;
            $rowFemale = 0;

            $cells = $gradeHeaders->map(function ($grade) use ($byGrade, &$rowTotal, &$rowFemale) {
                $item = $byGrade->get($grade['id']);
                $total = (int) ($item->total ?? 0);
                $female = (int) ($item->female ?? 0);
                $rowTotal += $total;
                $rowFemale += $female;

                return [
                    'total' => $total,
                    'female' => $female,
                ];
            })->all();

            return [
                'campus' => $first->campus_name_en ?: 'Campus',
                'grades' => $cells,
                'total' => $rowTotal,
                'female' => $rowFemale,
            ];
        })->values();

        $grandGrades = $gradeHeaders->map(function ($grade, $index) use ($rows) {
            return [
                'total' => $rows->sum(fn ($row) => $row['grades'][$index]['total'] ?? 0),
                'female' => $rows->sum(fn ($row) => $row['grades'][$index]['female'] ?? 0),
            ];
        })->all();

        return [
            'headers' => $gradeHeaders->pluck('label')->all(),
            'rows' => $rows->all(),
            'grand_grades' => $grandGrades,
            'grand_total' => [
                'total' => $rows->sum('total'),
                'female' => $rows->sum('female'),
            ],
        ];
    }

    private function personalizedWidgets($templateWidgets, ?DashboardUserPreference $preference)
    {
        $settings = $preference?->settings ?? [];
        $savedWidgets = collect($settings['widgets'] ?? []);

        if ($savedWidgets->isEmpty()) {
            return $templateWidgets;
        }

        $widgetsById = $templateWidgets->keyBy('id');

        return $savedWidgets
            ->sortBy('display_order')
            ->map(function ($savedWidget) use ($widgetsById) {
                $widget = $widgetsById->get((int) ($savedWidget['id'] ?? 0));

                if (!$widget) {
                    return null;
                }

                $clone = clone $widget;
                $pivot = new Pivot();
                $pivot->setRawAttributes([
                    'display_order' => $savedWidget['display_order'] ?? 0,
                    'width' => $savedWidget['width'] ?? $widget->pivot->width ?? 'medium',
                    'status' => true,
                    'settings' => json_encode([
                        'section_id' => $savedWidget['section_id'] ?? 'section-1',
                        'column' => (int) ($savedWidget['column'] ?? 1),
                        'chart_type' => $savedWidget['chart_type'] ?? 'standard',
                    ]),
                ], true);
                $clone->setRelation('pivot', $pivot);

                return $clone;
            })
            ->filter()
            ->values();
    }

    private function widgetPayload($widgets): array
    {
        return $widgets->map(function ($widget) {
            $settings = json_decode($widget->pivot->settings ?? '{}', true);

            return [
                'id' => $widget->id,
                'name' => $widget->name,
                'code' => $widget->code,
                'type' => $widget->type,
                'category' => $this->widgetCategory($widget),
                'icon' => $widget->icon ?: 'ti-chart-bar',
                'color' => $widget->color ?: 'blue',
                'description' => $widget->description ?: $widget->code,
                'width' => $widget->pivot->width ?? 'medium',
                'section_id' => $settings['section_id'] ?? 'section-1',
                'column' => (int) ($settings['column'] ?? 1),
                'chart_type' => $settings['chart_type'] ?? 'standard',
            ];
        })->values()->all();
    }

    private function staffProfiles(?int $userId)
    {
        if (!$userId) {
            return collect();
        }

        return User::with(['position:id,name', 'department:id,name'])
            ->whereKey($userId)
            ->get(['id', 'name', 'photo_path', 'position_id', 'department_id', 'last_seen_at'])
            ->map(fn ($staff) => [
                'name' => $staff->name,
                'photo' => $staff->photo_path ? asset('storage/' . $staff->photo_path) : null,
                'initial' => strtoupper(substr((string) ($staff->name ?: 'S'), 0, 1)),
                'position' => $staff->position?->name ?: 'Staff',
                'department' => $staff->department?->name ?: 'General',
                'status' => $staff->last_seen_at && $staff->last_seen_at->gt(now()->subMinutes(5)) ? 'Online' : 'Active',
            ])
            ->values();
    }

    private function widgetCategory(DashboardWidget $widget): string
    {
        if ($widget->type === 'filter') return 'Filters';
        if (str_contains($widget->code, 'student') || str_contains($widget->code, 'enrollment') || str_contains($widget->code, 'class')) return 'Students';
        if (str_contains($widget->code, 'staff') || str_contains($widget->code, 'user')) return 'Staff';
        if ($widget->type === 'chat' || str_contains($widget->code, 'chat')) return 'Chats';
        if (str_contains($widget->code, 'notification')) return 'Communication';

        return 'System';
    }

    private function normalizedSections(?string $payload): array
    {
        $sections = json_decode($payload ?: '[]', true);

        if (!is_array($sections) || !$sections) {
            $sections = [
                ['id' => 'section-1', 'title' => 'Section 1', 'columns' => '4'],
            ];
        }

        return collect($sections)
            ->filter(fn ($section) => is_array($section))
            ->values()
            ->map(function ($section, $index) {
                return [
                    'id' => preg_replace('/[^A-Za-z0-9_-]/', '', $section['id'] ?? 'section-' . ($index + 1)) ?: 'section-' . ($index + 1),
                    'title' => trim((string) ($section['title'] ?? 'Section ' . ($index + 1))) ?: 'Section ' . ($index + 1),
                    'columns' => in_array(($section['columns'] ?? '4'), ['1', '6', '5', '4', '3', '2', '8-4', '4-8', '7-5', '5-7'], true)
                        ? $section['columns']
                        : '4',
                ];
            })
            ->all();
    }

    private function dashboardSections(?DashboardTemplate $template, ?DashboardUserPreference $preference = null): array
    {
        $sections = $preference?->settings['sections'] ?? $template?->settings['sections'] ?? null;

        if (!is_array($sections) || !$sections) {
            return [
                ['id' => 'section-1', 'title' => 'Dashboard', 'columns' => '4'],
            ];
        }

        return $sections;
    }
}
