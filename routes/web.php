<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SchoolInfoController;
use App\Http\Controllers\EducationLevelController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\SchoolGroupController;
use App\Http\Controllers\StudentEnrollmentController;
use App\Http\Controllers\EnrollmentWorkflowController;
use App\Http\Controllers\GraduationController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\LocationController;
use App\Services\CampusContext;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\FamilyMemberController;
use App\Http\Controllers\OccupationController;
use App\Http\Controllers\NationalityController;
use App\Http\Controllers\BrandingSettingController;
use App\Http\Controllers\AccessManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\DepartmentManagementController;
use App\Http\Controllers\PositionManagementController;
use App\Http\Controllers\RoleManagementController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\StudentSearchController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\StudentDataTransferController;
use App\Http\Controllers\WithdrawalReasonController;
use App\Http\Controllers\StudentReentryController;
use App\Http\Controllers\StudentDocumentController;
use App\Http\Controllers\StudentDocumentTypeController;
use App\Http\Controllers\SummerSchoolController;
use App\Http\Controllers\DatabaseBackupController;
use App\Http\Controllers\AcademicTrackController;
use App\Http\Controllers\DashboardTemplateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportsController;

Route::get('/app-icon.svg', function () {
    $branding = \App\Models\BrandingSetting::current();
    $iconPath = $branding->shortcut_icon_path ?: $branding->login_logo_path ?: $branding->favicon_path ?: $branding->sidebar_logo_path;
    $fallback = public_path('pwa-icon.svg');
    $absolutePath = $iconPath ? storage_path('app/public/' . ltrim($iconPath, '/')) : null;

    if (!$absolutePath || !is_file($absolutePath)) {
        $absolutePath = is_file($fallback) ? $fallback : null;
    }

    $mime = $absolutePath ? (mime_content_type($absolutePath) ?: 'image/png') : 'image/svg+xml';
    $data = $absolutePath ? base64_encode(file_get_contents($absolutePath)) : '';
    $href = $data ? "data:{$mime};base64,{$data}" : '';

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
    <rect width="512" height="512" rx="112" fill="#ffffff"/>
    <image href="{$href}" x="86" y="86" width="340" height="340" preserveAspectRatio="xMidYMid meet"/>
</svg>
SVG;

    return response($svg, 200, [
        'Content-Type' => 'image/svg+xml',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
})->name('app.icon');

Route::get('/app.webmanifest', function () {
    $branding = \App\Models\BrandingSetting::current();
    $iconPath = $branding->shortcut_icon_path ?: $branding->login_logo_path ?: $branding->favicon_path ?: $branding->sidebar_logo_path;
    $iconVersion = $iconPath ? substr(md5($iconPath), 0, 10) : 'default';
    $iconUrl = route('app.icon', ['v' => $iconVersion]);

    return response()->json([
        'name' => 'Western International School System',
        'short_name' => 'WIS School',
        'description' => 'Western International School management system',
        'start_url' => '/',
        'scope' => '/',
        'display' => 'standalone',
        'background_color' => '#f4f7fb',
        'theme_color' => '#206bc4',
        'orientation' => 'portrait',
        'icons' => [
            [
                'src' => $iconUrl,
                'sizes' => 'any',
                'type' => 'image/svg+xml',
                'purpose' => 'any maskable',
            ],
        ],
    ], 200, ['Content-Type' => 'application/manifest+json']);
})->name('app.manifest');

Route::middleware('guest')->group(function () {
    Route::get('/setup/admin', [AuthController::class, 'setupForm'])->name('setup.admin');
    Route::post('/setup/admin', [AuthController::class, 'setupAdmin']);
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/forgot-password', [AuthController::class, 'forgotForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'forgot'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->name('password.update');
});

Route::get('/staff-card/{token}', [AuthController::class, 'publicStaffCard'])->name('staff-card.public');
Route::get('/staff-card/{token}/qr.svg', [AuthController::class, 'staffCardQr'])->name('staff-card.qr');
Route::get('/staff-card/{token}/vcard', [AuthController::class, 'staffCardVcard'])->name('staff-card.vcard');

Route::middleware(['auth', 'active.user'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::middleware('auth')->group(function () {
    Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
    Route::post('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
    Route::patch('/profile/name-card', [AuthController::class, 'updateNameCard'])->name('profile.name-card.update');
    Route::post('/profile/name-card/regenerate', [AuthController::class, 'regenerateNameCard'])->name('profile.name-card.regenerate');
    Route::get('/profile/status', [AuthController::class, 'status'])->name('profile.status');
    Route::get('/feedback', [AuthController::class, 'feedbackForm'])->name('feedback');
    Route::post('/feedback', [AuthController::class, 'feedback'])->name('feedback.save');
    Route::get('/settings/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('/settings/users/print', [UserManagementController::class, 'print'])->name('users.print');
    Route::get('/settings/users/excel', [UserManagementController::class, 'exportExcel'])->name('users.excel');
    Route::post('/settings/users', [UserManagementController::class, 'save'])->name('users.save');
    Route::delete('/settings/users/{user}', [UserManagementController::class, 'delete'])->name('users.delete');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread', [NotificationController::class, 'unread'])->name('notifications.unread');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::get('/push/vapid-public-key', [PushSubscriptionController::class, 'publicKey'])->name('push.public-key');
    Route::post('/push/subscriptions', [PushSubscriptionController::class, 'store'])->name('push.subscriptions.store');
    Route::delete('/push/subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push.subscriptions.destroy');
    Route::get('/communication/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/communication/chat/users', [ChatController::class, 'users'])->name('chat.users');
    Route::get('/communication/chat/conversations', [ChatController::class, 'conversations'])->name('chat.conversations');
    Route::get('/communication/chat/unread', [ChatController::class, 'unread'])->name('chat.unread');
    Route::post('/communication/chat', [ChatController::class, 'create'])->name('chat.create');
    Route::get('/communication/chat/messages/{message}/download', [ChatController::class, 'download'])->name('chat.messages.download');
    Route::delete('/communication/chat/messages/{message}', [ChatController::class, 'deleteMessage'])->name('chat.messages.delete');
    Route::patch('/communication/chat/{conversation}', [ChatController::class, 'update'])->name('chat.update');
    Route::post('/communication/chat/{conversation}/members', [ChatController::class, 'addMembers'])->name('chat.members.add');
    Route::post('/communication/chat/{conversation}/admins', [ChatController::class, 'setAdmins'])->name('chat.admins.set');
    Route::post('/communication/chat/{conversation}/photo', [ChatController::class, 'updatePhoto'])->name('chat.photo.update');
    Route::get('/communication/chat/{conversation}/messages', [ChatController::class, 'messages'])->name('chat.messages');
    Route::post('/communication/chat/{conversation}/messages', [ChatController::class, 'send'])->name('chat.messages.send');
    Route::post('/communication/chat/{conversation}/voice', [ChatController::class, 'sendVoice'])->name('chat.voice.send');
    Route::post('/communication/chat/{conversation}/call', [ChatController::class, 'callStart'])->name('chat.call.start');
    Route::get('/communication/chat/calls/pending', [ChatController::class, 'pendingCalls'])->name('chat.calls.pending');
    Route::get('/communication/chat/calls/{call}', [ChatController::class, 'callShow'])->name('chat.calls.show');
    Route::get('/communication/chat/calls/{call}/signals', [ChatController::class, 'callSignals'])->name('chat.calls.signals');
    Route::post('/communication/chat/calls/{call}/signals', [ChatController::class, 'callSignal'])->name('chat.calls.signals.store');
    Route::post('/communication/chat/heartbeat', [ChatController::class, 'heartbeat'])->name('chat.heartbeat');
    Route::get('/settings/notifications/send', [NotificationController::class, 'sendForm'])->name('notifications.send');
    Route::post('/settings/notifications/send', [NotificationController::class, 'send'])->name('notifications.send.save');
    Route::post('/settings/notifications/upload-image', [NotificationController::class, 'uploadImage'])->name('notifications.upload-image');
    Route::get('/settings/notifications', [NotificationController::class, 'manage'])->name('notifications.manage');
    Route::post('/settings/notifications/{notification}', [NotificationController::class, 'update'])->name('notifications.update');
    Route::delete('/settings/notifications/{notification}', [NotificationController::class, 'delete'])->name('notifications.delete');
    Route::get('/settings/departments', [DepartmentManagementController::class, 'index'])->name('departments.index');
    Route::get('/settings/departments/print', [DepartmentManagementController::class, 'print'])->name('departments.print');
    Route::get('/settings/departments/excel', [DepartmentManagementController::class, 'exportExcel'])->name('departments.excel');
    Route::post('/settings/departments', [DepartmentManagementController::class, 'save'])->name('departments.save');
    Route::delete('/settings/departments/{department}', [DepartmentManagementController::class, 'delete'])->name('departments.delete');
    Route::get('/settings/positions', [PositionManagementController::class, 'index'])->name('positions.index');
    Route::get('/settings/positions/print', [PositionManagementController::class, 'print'])->name('positions.print');
    Route::get('/settings/positions/excel', [PositionManagementController::class, 'exportExcel'])->name('positions.excel');
    Route::post('/settings/positions', [PositionManagementController::class, 'save'])->name('positions.save');
    Route::delete('/settings/positions/{position}', [PositionManagementController::class, 'delete'])->name('positions.delete');
    Route::get('/settings/roles', [RoleManagementController::class, 'index'])->name('roles.index');
    Route::get('/settings/roles/print', [RoleManagementController::class, 'print'])->name('roles.print');
    Route::get('/settings/roles/excel', [RoleManagementController::class, 'exportExcel'])->name('roles.excel');
    Route::post('/settings/roles', [RoleManagementController::class, 'save'])->name('roles.save');
    Route::delete('/settings/roles/{role}', [RoleManagementController::class, 'delete'])->name('roles.delete');
    Route::get('/settings/dashboard-templates', [DashboardTemplateController::class, 'index'])->name('dashboard-templates.index');
    Route::post('/settings/dashboard-templates', [DashboardTemplateController::class, 'save'])->name('dashboard-templates.save');
    Route::delete('/settings/dashboard-templates/{dashboardTemplate}', [DashboardTemplateController::class, 'delete'])->name('dashboard-templates.delete');
});

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Dashboard Route
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/customize', [DashboardController::class, 'customize'])->name('dashboard.customize');
Route::post('/dashboard/customize', [DashboardController::class, 'saveCustomization'])->name('dashboard.customize.save');
Route::delete('/dashboard/customize', [DashboardController::class, 'resetCustomization'])->name('dashboard.customize.reset');

Route::middleware(['auth', 'active.user'])->group(function () {
    Route::view('/attendance', 'academic-module-placeholder', [
        'title' => 'Attendance',
        'pretitle' => 'Student Attendance',
        'icon' => 'ti-calendar-check',
        'description' => 'Use this module for student attendance records.',
    ])->middleware('permission:attendance.view')->name('attendance.index');

    Route::view('/schedules', 'academic-module-placeholder', [
        'title' => 'Schedules',
        'pretitle' => 'Student and Teacher Schedules',
        'icon' => 'ti-calendar-time',
        'description' => 'Use this module to create student and teacher schedules.',
    ])->middleware('permission:schedules.view')->name('schedules.index');

    Route::view('/grading-system', 'academic-module-placeholder', [
        'title' => 'Grading System',
        'pretitle' => 'Student Grading',
        'icon' => 'ti-report',
        'description' => 'Use this module for teachers to enter student scores and grading records.',
    ])->middleware('permission:grading-system.view')->name('grading-system.index');

    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('/reports/{type}', [ReportsController::class, 'show'])->name('reports.show');
    Route::get('/reports/{type}/excel', [ReportsController::class, 'excel'])->name('reports.excel');
    Route::get('/reports/{type}/pdf', [ReportsController::class, 'pdf'])->name('reports.pdf');
});

Route::middleware(['auth', 'campus.context', 'campus.access'])->prefix('access')->group(function () {
    Route::get('/campuses', function (\Illuminate\Http\Request $request) {
        return response()->json([
            'data' => $request->user()->accessibleCampuses()->get(['tb_school_info.id', 'campus_name_en', 'campus_name_kh']),
            'active_campus_id' => $request->attributes->get('campus_id'),
        ]);
    })->name('access.campuses');

    Route::post('/active-campus/{campus}', function (\Illuminate\Http\Request $request, int $campus, CampusContext $context) {
        $activeCampus = $context->set($request, $request->user(), $campus);

        return response()->json(['data' => $activeCampus, 'message' => 'Active campus changed successfully.']);
    })->name('access.active-campus');
});

Route::post('/status/toggle', [StatusController::class, 'toggle'])->name('status.toggle');
Route::get('/settings/locations', [LocationController::class, 'index'])->name('locations.index');
Route::get('/settings/branding', [BrandingSettingController::class, 'index'])->name('branding-settings.index');
Route::post('/settings/branding', [BrandingSettingController::class, 'save'])->name('branding-settings.save');
Route::get('/settings/database-backups', [DatabaseBackupController::class, 'index'])->name('database-backups.index');
Route::post('/settings/database-backups', [DatabaseBackupController::class, 'create'])->name('database-backups.create');
Route::get('/settings/database-backups/{filename}/download', [DatabaseBackupController::class, 'download'])->where('filename', '[A-Za-z0-9_.-]+')->name('database-backups.download');
Route::delete('/settings/database-backups/{filename}', [DatabaseBackupController::class, 'delete'])->where('filename', '[A-Za-z0-9_.-]+')->name('database-backups.delete');
Route::get('/settings/access', [AccessManagementController::class, 'index'])->name('access-management.index');
Route::get('/settings/access/reports/{type}/print', [AccessManagementController::class, 'printReport'])->where('type', 'permissions|departments|roles|users')->name('access-management.reports.print');
Route::get('/settings/access/reports/{type}/excel', [AccessManagementController::class, 'exportReportExcel'])->where('type', 'permissions|departments|roles|users')->name('access-management.reports.excel');
Route::post('/settings/access/roles', [AccessManagementController::class, 'saveRole'])->name('access-management.roles.save');
Route::post('/settings/access/roles/create', [AccessManagementController::class, 'createRole'])->name('access-management.roles.create');
Route::post('/settings/access/departments', [AccessManagementController::class, 'saveDepartment'])->name('access-management.departments.save');
Route::post('/settings/access/permissions', [AccessManagementController::class, 'savePermission'])->name('access-management.permissions.save');
Route::delete('/settings/access/permissions/{permission}', [AccessManagementController::class, 'deletePermission'])->name('access-management.permissions.delete');
Route::post('/settings/access/departments/permissions', [AccessManagementController::class, 'saveDepartmentPermissions'])->name('access-management.departments.permissions.save');
Route::post('/settings/access/staff', [AccessManagementController::class, 'saveStaff'])->name('access-management.staff.save');
Route::get('/locations/options', [LocationController::class, 'options'])->name('locations.options');
Route::get('/locations/fetch', [LocationController::class, 'fetch'])->name('locations.fetch');
Route::get('/locations/print', [LocationController::class, 'print'])->name('locations.print');
Route::get('/locations/excel', [LocationController::class, 'exportExcel'])->name('locations.excel');
Route::post('/locations/save', [LocationController::class, 'save'])->name('locations.save');
Route::delete('/locations/delete/{id}', [LocationController::class, 'delete'])->name('locations.delete');

// Student search
Route::middleware('auth')->group(function () {
    Route::get('/students/search', [StudentSearchController::class, 'index'])->name('searchStudent.index');
    Route::get('/students/search/options', [StudentSearchController::class, 'options'])->name('searchStudent.options');
    Route::get('/students/search/fetch', [StudentSearchController::class, 'fetch'])->name('searchStudent.fetch');
});

Route::get('/students/enrollment', [StudentEnrollmentController::class, 'index'])->name('studentEnrollment.index');
Route::get('/summer-school', [SummerSchoolController::class, 'index'])->name('summer-school.index');
Route::get('/summer-school/options', [SummerSchoolController::class, 'options'])->name('summer-school.options');
Route::get('/summer-school/student-options', [SummerSchoolController::class, 'studentOptions'])->name('summer-school.student-options');
Route::get('/summer-school/western-filter-options', [SummerSchoolController::class, 'westernFilterOptions'])->name('summer-school.western-filter-options');
Route::get('/summer-school/western-grade-options', [SummerSchoolController::class, 'westernGradeOptions'])->name('summer-school.western-grade-options');
Route::get('/summer-school/family-options', [SummerSchoolController::class, 'familyOptions'])->name('summer-school.family-options');
Route::get('/summer-school/period-options', [SummerSchoolController::class, 'periodOptions'])->name('summer-school.period-options');
Route::get('/summer-school/location-options', [SummerSchoolController::class, 'locationOptions'])->name('summer-school.location-options');
Route::get('/summer-school/fetch', [SummerSchoolController::class, 'fetch'])->name('summer-school.fetch');
Route::get('/summer-school/{enrollment}/details', [SummerSchoolController::class, 'details'])->name('summer-school.details');
Route::post('/summer-school/save', [SummerSchoolController::class, 'save'])->name('summer-school.save');
Route::delete('/summer-school/{enrollment}', [SummerSchoolController::class, 'destroy'])->name('summer-school.destroy');
Route::post('/summer-school/{enrollment}/convert-to-western', [SummerSchoolController::class, 'convertToWestern'])->name('summer-school.convert-to-western');
Route::get('/student-enrollments/list-options', [StudentEnrollmentController::class, 'listOptions'])->name('student-enrollments.list-options');
Route::get('/student-enrollments/quick-options', [StudentEnrollmentController::class, 'quickOptions'])->name('student-enrollments.quick-options');
Route::get('/student-enrollments/options', [StudentEnrollmentController::class, 'options'])->name('student-enrollments.options');
Route::get('/student-enrollments/family-details', [StudentEnrollmentController::class, 'familyDetails'])->name('student-enrollments.family-details');
Route::get('/student-enrollments/filter-options', [StudentEnrollmentController::class, 'filterOptions'])->name('student-enrollments.filter-options');
Route::get('/student-enrollments/stats', [StudentEnrollmentController::class, 'stats'])->name('student-enrollments.stats');
Route::get('/student-enrollments/fetch', [StudentEnrollmentController::class, 'fetchData'])->name('student-enrollments.fetch');
Route::get('/student-enrollments/student/{student}/academic-years', [StudentEnrollmentController::class, 'studentAcademicYears'])->name('student-enrollments.student-academic-years');
Route::get('/student-enrollments/student/{student}/siblings', [StudentEnrollmentController::class, 'siblings'])->name('student-enrollments.siblings');
Route::get('/student-enrollments/{enrollment}/history', [StudentEnrollmentController::class, 'history'])->name('student-enrollments.history');
Route::post('/student-enrollments/save', [StudentEnrollmentController::class, 'save'])->name('student-enrollments.save');
Route::delete('/student-enrollments/delete/{id}', [StudentEnrollmentController::class, 'delete'])->name('student-enrollments.delete');
Route::get('/students/enrollment/workflows', fn () => redirect()->route('studentPromotion.index'))->name('student-enrollment-workflows.index');
Route::get('/student-enrollment-workflows/options', [EnrollmentWorkflowController::class, 'options'])->name('student-enrollment-workflows.options');
Route::get('/student-enrollment-workflows/enrollments', [EnrollmentWorkflowController::class, 'enrollmentOptions'])->name('student-enrollment-workflows.enrollments');
Route::get('/student-enrollment-workflows/fetch', [EnrollmentWorkflowController::class, 'fetch'])->name('student-enrollment-workflows.fetch');
Route::post('/student-enrollment-workflows/promote', [EnrollmentWorkflowController::class, 'promote'])->name('student-enrollment-workflows.promote');
Route::post('/student-enrollment-workflows/{workflow}/cancel-promotion', [EnrollmentWorkflowController::class, 'cancelPromotion'])->name('student-enrollment-workflows.cancel-promotion');
Route::post('/student-enrollment-workflows/{workflow}/repromote', [EnrollmentWorkflowController::class, 'repromote'])->name('student-enrollment-workflows.repromote');
Route::post('/student-enrollment-workflows/{workflow}/reverse-transfer', [EnrollmentWorkflowController::class, 'reverseTransfer'])->name('student-enrollment-workflows.reverse-transfer');
Route::post('/student-enrollment-workflows/transfer', [EnrollmentWorkflowController::class, 'transfer'])->name('student-enrollment-workflows.transfer');
Route::post('/student-enrollment-workflows/class-promote', [EnrollmentWorkflowController::class, 'promoteClass'])->name('student-enrollment-workflows.class-promote');
Route::post('/student-enrollment-workflows/selected-promote', [EnrollmentWorkflowController::class, 'promoteSelected'])->name('student-enrollment-workflows.selected-promote');
Route::post('/student-enrollment-workflows/selected-transfer', [EnrollmentWorkflowController::class, 'transferSelected'])->name('student-enrollment-workflows.selected-transfer');
Route::post('/student-enrollment-workflows/class-transfer', [EnrollmentWorkflowController::class, 'transferClass'])->name('student-enrollment-workflows.class-transfer');

Route::get('/families', [FamilyController::class, 'index'])->name('families.index');
Route::get('/families/fetch', [FamilyController::class, 'fetchData'])->name('families.fetch');
Route::get('/families/{family}', [FamilyController::class, 'show'])->name('families.show');
Route::post('/families/save', [FamilyController::class, 'save'])->name('families.save');
Route::post('/families/change-student-family', [FamilyController::class, 'changeStudentFamily'])->name('families.change-student-family');
Route::delete('/families/{family}', [FamilyController::class, 'delete'])->name('families.delete');
Route::get('/families/{family}/members', [FamilyMemberController::class, 'index'])->name('families.members.index');
Route::post('/families/{family}/members/save', [FamilyMemberController::class, 'save'])->name('families.members.save');
Route::delete('/families/{family}/members/{member}', [FamilyMemberController::class, 'delete'])->name('families.members.delete');

Route::get('/settings/occupations', [OccupationController::class, 'index'])->name('occupations.index');
Route::get('/settings/academic-tracks', [AcademicTrackController::class, 'index'])->name('academic-tracks.index');
Route::get('/academic-tracks/fetch', [AcademicTrackController::class, 'fetchData'])->name('academic-tracks.fetch');
Route::get('/academic-tracks/print', [AcademicTrackController::class, 'print'])->name('academic-tracks.print');
Route::get('/academic-tracks/excel', [AcademicTrackController::class, 'exportExcel'])->name('academic-tracks.excel');
Route::post('/academic-tracks/save', [AcademicTrackController::class, 'save'])->name('academic-tracks.save');
Route::delete('/academic-tracks/{academicTrack}', [AcademicTrackController::class, 'delete'])->name('academic-tracks.delete');
Route::get('/settings/withdrawal-reasons', [WithdrawalReasonController::class, 'index'])->middleware('auth')->name('withdrawal-reasons.index');
Route::get('/withdrawal-reasons/print', [WithdrawalReasonController::class, 'print'])->middleware('auth')->name('withdrawal-reasons.print');
Route::get('/withdrawal-reasons/excel', [WithdrawalReasonController::class, 'exportExcel'])->middleware('auth')->name('withdrawal-reasons.excel');
Route::post('/settings/withdrawal-reasons', [WithdrawalReasonController::class, 'save'])->middleware('auth')->name('withdrawal-reasons.save');
Route::delete('/settings/withdrawal-reasons/{withdrawalReason}', [WithdrawalReasonController::class, 'delete'])->middleware('auth')->name('withdrawal-reasons.delete');
Route::get('/settings/student-document-types', [StudentDocumentTypeController::class, 'index'])->middleware('auth')->name('student-document-types.index');
Route::post('/settings/student-document-types', [StudentDocumentTypeController::class, 'save'])->middleware('auth')->name('student-document-types.save');
Route::delete('/settings/student-document-types/{studentDocumentType}', [StudentDocumentTypeController::class, 'delete'])->middleware('auth')->name('student-document-types.delete');
Route::get('/occupations/fetch', [OccupationController::class, 'fetchData'])->name('occupations.fetch');
Route::get('/occupations/print', [OccupationController::class, 'print'])->name('occupations.print');
Route::get('/occupations/excel', [OccupationController::class, 'exportExcel'])->name('occupations.excel');
Route::post('/occupations/save', [OccupationController::class, 'save'])->name('occupations.save');
Route::delete('/occupations/{occupation}', [OccupationController::class, 'delete'])->name('occupations.delete');

Route::get('/settings/nationalities', [NationalityController::class, 'index'])->name('nationalities.index');
Route::get('/nationalities/fetch', [NationalityController::class, 'fetchData'])->name('nationalities.fetch');
Route::get('/nationalities/options', [NationalityController::class, 'options'])->name('nationalities.options');
Route::post('/nationalities/save', [NationalityController::class, 'save'])->name('nationalities.save');
Route::delete('/nationalities/{nationality}', [NationalityController::class, 'delete'])->name('nationalities.delete');

Route::get('/students/promotion', fn () => app(\App\Http\Controllers\EnrollmentWorkflowController::class)->index('promotion'))->name('studentPromotion.index');

Route::get('/students/graduation', function () {
    return app(GraduationController::class)->index();
})->name('studentGraduation.index');
Route::get('/student-graduations/options', [GraduationController::class, 'options'])->name('student-graduations.options');
Route::get('/student-graduations/fetch', [GraduationController::class, 'fetch'])->name('student-graduations.fetch');
Route::post('/student-graduations/graduate', [GraduationController::class, 'graduate'])->name('student-graduations.graduate');
Route::post('/student-graduations/graduate-batch', [GraduationController::class, 'graduateBatch'])->name('student-graduations.graduate-batch');
Route::post('/student-graduations/{graduation}/cancel', [GraduationController::class, 'cancel'])->name('student-graduations.cancel');

Route::get('/students/update', function () {
    return view('dashboard');
})->name('updateStudent.index');

Route::get('/students/transfer', fn () => app(\App\Http\Controllers\EnrollmentWorkflowController::class)->index('transfer'))->name('studentTransfer.index');
Route::get('/students/documents', [StudentDocumentController::class, 'index'])->middleware(['auth', 'permission:student-documents.view'])->name('student-documents.index');
Route::get('/student-documents/options', [StudentDocumentController::class, 'options'])->middleware(['auth', 'permission:student-documents.view'])->name('student-documents.options');
Route::get('/student-documents/fetch/{student}', [StudentDocumentController::class, 'fetch'])->middleware(['auth', 'permission:student-documents.view'])->name('student-documents.fetch');
Route::post('/student-documents', [StudentDocumentController::class, 'save'])->middleware(['auth', 'permission:student-documents.create'])->name('student-documents.save');
Route::get('/student-documents/{document}/view', [StudentDocumentController::class, 'view'])->middleware(['auth', 'permission:student-documents.preview'])->name('student-documents.view');
Route::get('/student-documents/{document}/download', [StudentDocumentController::class, 'download'])->middleware(['auth', 'permission:student-documents.download'])->name('student-documents.download');
Route::delete('/student-documents/{document}', [StudentDocumentController::class, 'delete'])->middleware(['auth', 'permission:student-documents.delete'])->name('student-documents.delete');

Route::get('/students/withdraw', [\App\Http\Controllers\StudentWithdrawalController::class, 'index'])->name('withdrawStudent.index');
Route::get('/student-withdrawals/options', [\App\Http\Controllers\StudentWithdrawalController::class, 'options'])->name('student-withdrawals.options');
Route::get('/student-withdrawals/history-options', [\App\Http\Controllers\StudentWithdrawalController::class, 'historyOptions'])->name('student-withdrawals.history-options');
Route::get('/student-withdrawals/students', [\App\Http\Controllers\StudentWithdrawalController::class, 'students'])->name('student-withdrawals.students');
Route::get('/student-withdrawals/fetch', [\App\Http\Controllers\StudentWithdrawalController::class, 'fetch'])->name('student-withdrawals.fetch');
Route::get('/student-withdrawals/enrollments/{enrollment}/family', [\App\Http\Controllers\StudentWithdrawalController::class, 'enrollmentFamily'])->name('student-withdrawals.enrollment-family');
Route::get('/student-withdrawals/{history}', [\App\Http\Controllers\StudentWithdrawalController::class, 'show'])->name('student-withdrawals.show');
Route::patch('/student-withdrawals/{history}', [\App\Http\Controllers\StudentWithdrawalController::class, 'update'])->name('student-withdrawals.update');
Route::post('/student-withdrawals/{history}/principal-approved', [\App\Http\Controllers\StudentWithdrawalController::class, 'markPrincipalApproved'])->name('student-withdrawals.principal-approved');
Route::post('/student-withdrawals/{history}/approve', [\App\Http\Controllers\StudentWithdrawalController::class, 'approve'])->name('student-withdrawals.approve');
Route::post('/student-withdrawals/{history}/reject', [\App\Http\Controllers\StudentWithdrawalController::class, 'reject'])->name('student-withdrawals.reject');
Route::post('/student-withdrawals/{history}/cancel', [\App\Http\Controllers\StudentWithdrawalController::class, 'cancel'])->name('student-withdrawals.cancel');
Route::get('/student-withdrawals/{history}/form', [\App\Http\Controllers\StudentWithdrawalController::class, 'form'])->name('student-withdrawals.form');
Route::post('/student-withdrawals/withdraw', [\App\Http\Controllers\StudentWithdrawalController::class, 'withdraw'])->name('student-withdrawals.withdraw');
Route::post('/student-withdrawals/withdraw-selected', [\App\Http\Controllers\StudentWithdrawalController::class, 'withdrawSelected'])->name('student-withdrawals.withdraw-selected');
Route::post('/student-withdrawals/withdraw-class', [\App\Http\Controllers\StudentWithdrawalController::class, 'withdrawClass'])->name('student-withdrawals.withdraw-class');
Route::get('/students/re-entry', [StudentReentryController::class, 'index'])->middleware('auth')->name('student-reentry.index');
Route::get('/student-reentry/options', [StudentReentryController::class, 'options'])->middleware('auth')->name('student-reentry.options');
Route::post('/student-reentry', [StudentReentryController::class, 'reenter'])->middleware('auth')->name('student-reentry.save');
Route::get('/students/data-transfer', [StudentDataTransferController::class, 'index'])->name('student-data-transfer.index');
Route::get('/students/data-transfer/{type}/template', [StudentDataTransferController::class, 'template'])->name('student-data-transfer.template');
Route::get('/students/data-transfer/{type}/export', [StudentDataTransferController::class, 'export'])->name('student-data-transfer.export');
Route::post('/students/data-transfer/{type}/import', [StudentDataTransferController::class, 'import'])->name('student-data-transfer.import');

// Academic Year Routes
Route::get('/academic-years', [AcademicYearController::class, 'index'])->name('academic-years.index');
Route::get('/academic-years/fetch', [AcademicYearController::class, 'fetchData'])->name('academic-years.fetch');
Route::get('/academic-years/pdf', [AcademicYearController::class, 'exportPdf'])->name('academic-years.pdf');
Route::get('/academic-years/print', [AcademicYearController::class, 'print'])->name('academic-years.print');
Route::get('/academic-years/excel', [AcademicYearController::class, 'exportExcel'])->name('academic-years.excel');
Route::post('/academic-years/save', [AcademicYearController::class, 'save'])->name('academic-years.save');
Route::post('/academic-years/{academicYear}/create-next', [AcademicYearController::class, 'createNext'])->name('academic-years.create-next');
Route::post('/academic-years/{id}/restore', [AcademicYearController::class, 'restore'])->name('academic-years.restore');
Route::post('/academic-years/{academicYear}/set-current', [AcademicYearController::class, 'setCurrent'])->name('academic-years.set-current');
Route::delete('/academic-years/delete/{id}', [AcademicYearController::class, 'delete'])->name('academic-years.delete');

// Settings placeholder routes
Route::get('/settings/grades', [GradeController::class, 'index'])->name('grades.index');
Route::get('/grades/fetch', [GradeController::class, 'fetchData'])->name('grades.fetch');
Route::get('/grades/pdf', [GradeController::class, 'exportPdf'])->name('grades.pdf');
Route::get('/grades/print', [GradeController::class, 'print'])->name('grades.print');
Route::get('/grades/excel', [GradeController::class, 'exportExcel'])->name('grades.excel');
Route::post('/grades/save', [GradeController::class, 'save'])->name('grades.save');
Route::delete('/grades/delete/{id}', [GradeController::class, 'delete'])->name('grades.delete');

Route::get('/settings/classes', [SchoolClassController::class, 'index'])->name('classes.index');
Route::get('/classes/fetch', [SchoolClassController::class, 'fetchData'])->name('classes.fetch');
Route::get('/classes/pdf', [SchoolClassController::class, 'exportPdf'])->name('classes.pdf');
Route::get('/classes/print', [SchoolClassController::class, 'print'])->name('classes.print');
Route::get('/classes/excel', [SchoolClassController::class, 'exportExcel'])->name('classes.excel');
Route::post('/classes/save', [SchoolClassController::class, 'save'])->name('classes.save');
Route::delete('/classes/delete/{id}', [SchoolClassController::class, 'delete'])->name('classes.delete');

Route::get('/settings/sessions', [SessionController::class, 'index'])->name('sessions.index');
Route::get('/sessions/fetch', [SessionController::class, 'fetchData'])->name('sessions.fetch');
Route::get('/sessions/pdf', [SessionController::class, 'exportPdf'])->name('sessions.pdf');
Route::post('/sessions/save', [SessionController::class, 'save'])->name('sessions.save');
Route::delete('/sessions/delete/{id}', [SessionController::class, 'delete'])->name('sessions.delete');

Route::get('/settings/education-levels', [EducationLevelController::class, 'index'])->name('education-levels.index');
Route::get('/education-levels/fetch', [EducationLevelController::class, 'fetchData'])->name('education-levels.fetch');
Route::post('/education-levels/save', [EducationLevelController::class, 'save'])->name('education-levels.save');
Route::delete('/education-levels/delete/{id}', [EducationLevelController::class, 'delete'])->name('education-levels.delete');

Route::get('/settings/programs', [ProgramController::class, 'index'])->name('programs.index');
Route::get('/programs/options', [ProgramController::class, 'options'])->name('programs.options');
Route::get('/programs/fetch', [ProgramController::class, 'fetchData'])->name('programs.fetch');
Route::post('/programs/save', [ProgramController::class, 'save'])->name('programs.save');
Route::delete('/programs/delete/{id}', [ProgramController::class, 'delete'])->name('programs.delete');

Route::get('/settings/groups', [SchoolGroupController::class, 'index'])->name('groups.index');
Route::get('/groups/options', [SchoolGroupController::class, 'options'])->name('groups.options');
Route::get('/groups/fetch', [SchoolGroupController::class, 'fetchData'])->name('groups.fetch');
Route::post('/groups/save', [SchoolGroupController::class, 'save'])->name('groups.save');
Route::delete('/groups/delete/{id}', [SchoolGroupController::class, 'delete'])->name('groups.delete');

Route::get('/settings/terms', function () {
    return view('dashboard');
})->name('terms.index');

Route::get('/settings/school-info', [SchoolInfoController::class, 'index'])->name('schoolInfo.index');
Route::get('/school-info/fetch', [SchoolInfoController::class, 'fetchData'])->name('schoolInfo.fetch');
Route::get('/school-info/pdf', [SchoolInfoController::class, 'exportPdf'])->name('schoolInfo.pdf');
Route::get('/school-info/print', [SchoolInfoController::class, 'print'])->name('schoolInfo.print');
Route::get('/school-info/excel', [SchoolInfoController::class, 'exportExcel'])->name('schoolInfo.excel');
Route::post('/school-info/save', [SchoolInfoController::class, 'save'])->name('schoolInfo.save');
Route::delete('/school-info/delete/{id}', [SchoolInfoController::class, 'delete'])->name('schoolInfo.delete');

Route::get('/settings/campuses', function () {
    return view('dashboard');
})->name('campuses.index');

});
