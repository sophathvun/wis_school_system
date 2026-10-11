<?php

namespace App\Services;

use App\Models\StudentSkippingGrade;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\StudentSkippingGradePermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class SkippingGradeNotifications
{
    public function pendingRequests(User $user): Builder
    {
        $query = StudentSkippingGrade::query()->where('status', 'pending');
        if ((int) $user->status !== 1 || !StudentSkippingGradePermissions::allows($user, 'view')) return $query->whereRaw('1 = 0');
        $campuses = $user->accessibleCampuses()->pluck('tb_school_info.id');
        $legacy = StudentSkippingGradePermissions::allows($user, 'approve') || StudentSkippingGradePermissions::allows($user, 'reject');
        return $query->where(function ($flows) use ($user, $campuses, $legacy) {
            $flows->where(function ($sequential) use ($user, $campuses) {
                $sequential->where('approval_flow_mode', 'sequential')->whereHas('approvalSteps', function ($steps) use ($user, $campuses) {
                    $steps->where('status', 'pending')->assignedTo($user->id);
                    if ($campuses !== null) $steps->where(fn($scope) => $scope->where('scope', '!=', 'campus')
                        ->orWhereHas('request', fn($record) => $record->whereIn('campus_id', $campuses)));
                });
            });
            if ($legacy) $flows->orWhere('approval_flow_mode', '!=', 'sequential');
        });
    }

    public function pendingCount(?User $user): int
    {
        if (!$user || !Schema::hasTable('tb_student_skipping_grade')) return 0;
        return $this->pendingRequests($user)->count();
    }

    // Called inside the request's locked transaction, so alerts roll back with a failed decision.
    public function awaiting(StudentSkippingGrade $record): void
    {
        $step = $record->usesSequentialFlow() ? app(SkippingGradeWorkflow::class)->currentStep($record) : null;
        $type = 'grade-skipping-awaiting-'.$record->id.'-'.($step ? $step->id.'-'.$step->revision : 'legacy');
        $users = User::where('status', 1);
        if ($step) $users->whereIn('id', $step->reviewers->isNotEmpty() ? $step->reviewers->pluck('user_id') : [$step->reviewer_id]);
        $eligible = $users->get()->filter(fn($user) => $this->pendingRequests($user)->whereKey($record->id)->exists());
        $this->closeAlerts($record, $type, $eligible->modelKeys());
        $name = $record->student_snapshot['name_en'] ?? 'Student';
        $studentId = $record->student_snapshot['student_id'] ?? '';
        $submitter = $record->student_snapshot['submitter']['name'] ?? $record->submitter?->name ?? $record->creator?->name;
        foreach ($eligible as $user) UserNotification::firstOrCreate(['user_id' => $user->id, 'type' => $type], [
            'title' => 'Grade Skipping: Awaiting Your Approval',
            'message' => e($name.' ('.$studentId.') — submitted by '.$submitter.' on '.$record->submitted_at?->format('d-M-Y H:i').'. Review the request and supporting documents.'),
            'action_url' => route('student-skipping-grade.show', $record, false),
        ]);
    }

    public function completed(StudentSkippingGrade $record): void
    {
        $this->closeAlerts($record);
        $approved = $record->status === 'approved';
        $decision = $approved ? 'Approved' : 'Rejected';
        $actor = User::find($approved ? $record->approved_by : $record->rejected_by)?->name;
        $message = ($record->student_snapshot['name_en'] ?? 'Student').' — request '.$record->id.' was '.strtolower($decision).' by '.$actor.'.';
        if (!$approved) $message .= ' Reason: '.$record->rejection_reason;
        foreach (array_unique(array_filter([$record->created_by, $record->submitted_by])) as $userId) UserNotification::firstOrCreate([
            'user_id' => $userId, 'type' => 'grade-skipping-'.$record->id.'-'.strtolower($decision),
        ], [
            'title' => 'Grade Skipping Request '.$decision,
            'message' => e($message),
            'action_url' => route('student-skipping-grade.show', $record, false),
        ]);
    }

    private function closeAlerts(StudentSkippingGrade $record, ?string $keepType = null, array $keepUsers = []): void
    {
        UserNotification::where('type', 'like', 'grade-skipping-awaiting-'.$record->id.'-%')->whereNull('read_at')
            ->when($keepType, fn($alerts) => $alerts->where(fn($old) => $old->where('type', '!=', $keepType)->orWhereNotIn('user_id', $keepUsers)))
            ->update(['read_at' => now()]);
    }
}
