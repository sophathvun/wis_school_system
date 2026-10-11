<?php

namespace App\Services;

use App\Models\SchoolInfo;
use App\Models\Grade;
use App\Models\SkippingGradeApprovalStep;
use App\Models\SkippingGradeCampusSetting;
use App\Models\SkippingGradeSetting;
use App\Models\StudentSkippingGrade;
use App\Models\User;
use App\Support\StudentSkippingGradePermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Builder;

class SkippingGradeWorkflow
{
    public function revision(): string
    {
        $settings = SkippingGradeSetting::current();
        return hash('sha256', json_encode([$settings->approval_flow_mode, $settings->central_approval_steps,
            SkippingGradeCampusSetting::orderBy('campus_id')->get(['campus_id', 'approval_steps'])->toArray()]));
    }

    public function saveSettings(User $admin, array $data): void
    {
        abort_unless($admin->isSuperAdmin(), 403);
        DB::transaction(function () use ($admin, $data) {
            $settings = SkippingGradeSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            if (!hash_equals($this->revision(), $data['revision'])) $this->invalid('revision', 'The approval flow settings changed. Reload this page before saving.');
            $campus = SchoolInfo::where('status', 1)->findOrFail($data['campus_id']);
            $campusSteps = $this->validateSteps($data['campus_steps'] ?? [], $campus->id, 'campus_steps');
            $centralSteps = $this->validateSteps($data['central_steps'] ?? [], null, 'central_steps');
            if (!empty($data['vice_president_id'])) {
                $vp = $this->reviewer((int)$data['vice_president_id'], null, 'vice_president_id');
                $centralSteps[] = ['title' => 'Vice President', 'user_id' => $vp->id, 'is_final' => true];
            }
            $campusSetting = SkippingGradeCampusSetting::firstOrNew(['campus_id' => $campus->id]);
            if (!$campusSetting->exists) $campusSetting->committee_names = array_fill(0, 4, '');
            $campusSetting->fill(['approval_steps' => $campusSteps, 'updated_by' => $admin->id])->save();
            if ($data['mode'] === 'sequential') {
                if (!$campusSteps) $this->invalid('campus_steps', 'Configure campus reviewers for '.$campus->campus_name_en.' before enabling the step-by-step flow.');
                $this->validateCentral($centralSteps);
                $this->validateSigner($settings->toArray());
            }
            $settings->update(['approval_flow_mode' => $data['mode'], 'central_approval_steps' => $centralSteps, 'updated_by' => $admin->id]);
        });
    }

    private function validateSteps(array $steps, ?int $campus, string $field): array
    {
        $result = [];
        foreach ($steps as $index => $step) {
            $title = trim($step['title'] ?? '');
            if (!$title || mb_strlen($title) > 120) $this->invalid($field, 'Each reviewer needs a position label of up to 120 characters.');
            $members = $this->membersOf($step);
            if (!$members || count($members) > 20) $this->invalid($field, 'Each approval step needs between 1 and 20 reviewers.');
            $reviewers = []; $seen = [];
            foreach ($members as $member) {
                $memberTitle=trim($member['title']??$title);
                if (!$memberTitle || mb_strlen($memberTitle)>120) $this->invalid($field, 'Select a position or role of up to 120 characters for each reviewer.');
                $user = $this->reviewer((int)($member['user_id'] ?? 0), $campus, $field);
                if (in_array($user->id, $seen, true)) $this->invalid($field, 'Assign each user only once within the same step.');
                $seen[] = $user->id;
                $scope = $member['grade_scope'] ?? 'all';
                if (!in_array($scope, ['all', 'selected'], true)) $this->invalid($field, 'Choose All Grades or Assigned Grades for each reviewer.');
                $ids = array_values(array_unique(array_map('intval', $member['grade_ids'] ?? [])));
                if ($scope === 'selected' && (!$ids || Grade::where('status', 1)->whereIn('id', $ids)->count() !== count($ids))) {
                    $this->invalid($field, 'Select at least one active grade for each reviewer using Assigned Grades.');
                }
                $reviewers[] = ['user_id' => $user->id, 'title' => $memberTitle, 'grade_scope' => $scope, 'grade_ids' => $scope === 'all' ? [] : $ids];
            }
            $result[] = ['title' => $title, 'user_id' => $reviewers[0]['user_id'], 'reviewers' => $reviewers, 'is_final' => false];
        }
        return $result;
    }

    private function validateCentral(array $steps): void
    {
        if (!$steps || !($steps[array_key_last($steps)]['is_final'] ?? false)) $this->invalid('vice_president_id', 'Select the Vice President who gives final approval.');
        $this->validateSteps($steps, null, 'central_steps');
    }

    public function membersOf(array $step): array
    {
        return $step['reviewers'] ?? (isset($step['user_id']) ? [['user_id' => $step['user_id'], 'grade_scope' => 'all', 'grade_ids' => []]] : []);
    }

    private function coversGrade(array $member, int $gradeId): bool
    {
        return ($member['grade_scope'] ?? 'all') === 'all' || in_array($gradeId, array_map('intval', $member['grade_ids'] ?? []), true);
    }

    public function reviewer(int $id, ?int $campus, string $field = 'reviewer_id'): User
    {
        $user = User::where('status', 1)->find($id);
        if (!$user) $this->invalid($field, 'Select an active user with access to Students > Skipping Grade.');
        if (!$this->reviewerBelongsToScope($user,$campus)) $this->invalid($field, $campus
            ? 'The selected reviewer must be assigned to this campus.'
            : 'The selected reviewer must be assigned to Central (CEN).');
        if (!StudentSkippingGradePermissions::hasReviewerAccess($user,$campus)) $this->invalid($field, 'The selected reviewer needs access to Students > Skipping Grade at the assigned campus.');
        return $user;
    }

    public function reviewerBelongsToScope(User $user, ?int $campus): bool
    {
        return $campus !== null ? StudentSkippingGradePermissions::assignedToCampus($user,$campus)
            : StudentSkippingGradePermissions::assignedToCentral($user);
    }

    public function reviewerIsEligible(User $user, ?int $campus): bool
    {
        return (int)$user->status===1 && $this->reviewerBelongsToScope($user,$campus)
            && StudentSkippingGradePermissions::hasReviewerAccess($user,$campus);
    }

    public function snapshot(User $user, string $title, string $scope, ?int $campus = null): array
    {
        $photo = null;
        $path = $user->photo_path;
        if ($path && Storage::disk('public')->exists($path)) {
            $bytes = Storage::disk('public')->get($path);
            if (strlen($bytes) <= 5 * 1024 * 1024 && @getimagesizefromstring($bytes)) {
                $source = @imagecreatefromstring($bytes);
                if ($source) {
                    $thumb = imagecreatetruecolor(96, 96);
                    imagecopyresampled($thumb, $source, 0, 0, 0, 0, 96, 96, imagesx($source), imagesy($source));
                    ob_start(); imagejpeg($thumb, null, 85); $photo = 'data:image/jpeg;base64,'.base64_encode(ob_get_clean());
                    imagedestroy($thumb); imagedestroy($source);
                }
            }
        }
        return ['user_id' => $user->id, 'name' => $user->name, 'title' => $title, 'scope' => $scope,
            'campus_id' => $campus, 'campus' => $campus ? SchoolInfo::find($campus)?->campus_name_en : 'Central Office', 'photo' => $photo];
    }

    public function capture(StudentSkippingGrade $record, array &$files): void
    {
        if (!$record->usesSequentialFlow()) return;
        $settings = SkippingGradeSetting::whereKey(1)->lockForUpdate()->firstOrFail();
        $campus = SkippingGradeCampusSetting::where('campus_id', $record->campus_id)->lockForUpdate()->first();
        $campusSteps = $campus?->approval_steps ?? [];
        if (!$campusSteps) $this->invalid('settings', 'Super Admin must configure the campus approval steps before submission.');
        $campusSteps = $this->validateSteps($campusSteps, $record->campus_id, 'settings');
        $centralSteps = $settings->central_approval_steps ?? [];
        $this->validateCentral($centralSteps);
        $centralSteps = array_map(function ($step) {
            $normalized = $this->validateSteps([$step], null, 'settings')[0];
            $normalized['is_final'] = $step['is_final'] ?? false;
            return $normalized;
        }, $centralSteps);
        $sequence = 0;
        foreach (['campus' => $campusSteps, 'central' => $centralSteps] as $scope => $steps) {
            foreach ($steps as $step) {
                $eligible = array_values(array_filter($this->membersOf($step), fn($member) => $this->coversGrade($member, (int)$record->source_grade_id)));
                if (!$eligible) $this->invalid('settings', 'No reviewer is assigned to the current grade for '.$step['title'].'. Super Admin must update the approval flow before submission.');
                $people = array_map(function ($member) use ($record, $step, $scope) {
                    $user = $this->reviewer((int)$member['user_id'], $scope === 'campus' ? $record->campus_id : null, 'settings');
                    $snapshot = $this->snapshot($user, $member['title']??$step['title'], $scope, $scope === 'campus' ? $record->campus_id : null);
                    $snapshot['grade_scope'] = $member['grade_scope'];
                    $snapshot['grade_ids'] = $member['grade_ids'];
                    $snapshot['grades'] = $member['grade_scope'] === 'all' ? ['All Grades'] : Grade::whereIn('id', $member['grade_ids'])->orderBy('grade_order')->get()->map(fn($grade) => $grade->grade_short_name ?: $grade->grade)->all();
                    return $snapshot;
                }, $eligible);
                $savedStep = $record->approvalSteps()->create([
                    'sequence' => ++$sequence, 'scope' => $scope, 'title' => $step['title'], 'is_final' => $step['is_final'] ?? false,
                    'status' => $sequence === 1 ? 'pending' : 'waiting', 'reviewer_id' => $eligible[0]['user_id'], 'reviewer_snapshot' => $people[0],
                ]);
                foreach ($eligible as $index => $member) $savedStep->reviewers()->create([
                    'user_id' => $member['user_id'], 'reviewer_snapshot' => $people[$index], 'grade_scope' => $member['grade_scope'], 'grade_ids' => $member['grade_ids'],
                ]);
            }
        }
        $record->update(['workflow_signer_snapshot' => $this->captureSigner($record, $settings, $files)]);
    }

    private function validateSigner(array $signer): void
    {
        if (empty($signer['signer_name_kh'])) $this->invalid('settings', 'Save the VP name in Approval Settings first.');
        foreach (['signature_path', 'stamp_path'] as $key) {
            if (empty($signer[$key]) || !Storage::disk('local')->exists($signer[$key])) $this->invalid('settings', 'Upload the VP signature and stamp in Approval Settings first.');
        }
    }

    private function captureSigner(StudentSkippingGrade $record, SkippingGradeSetting $settings, array &$files): array
    {
        $this->validateSigner($settings->toArray());
        $snapshot = $settings->only(['signer_name_kh', 'signer_name_en', 'signer_title_kh', 'signer_title_en']);
        foreach (['signature_path', 'stamp_path'] as $key) {
            $source = $settings->{$key};
            $copy = 'grade-skipping/workflow-signers/'.$record->id.'/'.Str::uuid().'.'.pathinfo($source, PATHINFO_EXTENSION);
            $files[] = $copy;
            if (!Storage::disk('local')->copy($source, $copy)) $this->invalid('settings', 'Unable to save the approval signature and stamp.');
            $snapshot[$key] = $copy;
        }
        return $snapshot;
    }

    public function canView(User $user, StudentSkippingGrade $record): bool
    {
        if (!StudentSkippingGradePermissions::allows($user, 'view')) return false;
        if ($user->isSuperAdmin()) return true;
        if ($this->isReviewer($user, $record)) return true;
        if (in_array($user->id, [(int)$record->created_by, (int)$record->submitted_by, (int)$record->approved_by, (int)$record->rejected_by], true)) return true;
        if (StudentSkippingGradePermissions::assignedToCampus($user, $record->campus_id)
            && ((StudentSkippingGradePermissions::allows($user, 'requests') && !$this->isConfiguredReviewer($user))
                || $this->canSeeAssignedGrade($user, $record))) return true;
        if (!StudentSkippingGradePermissions::allows($user, 'requests')) return false;
        if (!$record->usesSequentialFlow() || !$this->isConfiguredReviewer($user)) return true;
        return $record->status === 'draft' && $this->canSeeAssignedGrade($user, $record);
    }

    public function isReviewer(User $user, StudentSkippingGrade $record): bool
    {
        return $record->usesSequentialFlow() && StudentSkippingGradePermissions::allows($user, 'view')
            && $record->approvalSteps()->where(fn($q) => $q->assignedTo($user->id)->orWhere('acted_by', $user->id))->exists();
    }

    private function configuredAssignments(User $user): array
    {
        $assignments = [];
        $sources = [['campus_id' => null, 'steps' => SkippingGradeSetting::current()->central_approval_steps ?? []]];
        foreach (SkippingGradeCampusSetting::get() as $campus) $sources[] = ['campus_id' => $campus->campus_id, 'steps' => $campus->approval_steps ?? []];
        foreach ($sources as $source) foreach ($source['steps'] as $step) foreach ($this->membersOf($step) as $member) {
            if ((int)$member['user_id'] === $user->id) $assignments[] = $member + ['campus_id' => $source['campus_id']];
        }
        return $assignments;
    }

    public function isConfiguredReviewer(User $user): bool
    {
        return $this->configuredAssignments($user) !== [] || SkippingGradeApprovalStep::assignedTo($user->id)->exists();
    }

    private function canSeeAssignedGrade(User $user, StudentSkippingGrade $record): bool
    {
        foreach ($this->configuredAssignments($user) as $member) {
            if (($member['campus_id'] === null || ((int)$member['campus_id'] === (int)$record->campus_id && $user->canAccessCampus($record->campus_id)))
                && $this->coversGrade($member, (int)$record->source_grade_id)) return true;
        }
        return false;
    }

    public function campusRequests(Builder $query, User $user): Builder
    {
        $campuses=$user->isSuperAdmin()?SchoolInfo::where('status',1)->pluck('id')
            :$user->campuses()->where('tb_school_info.status',1)->pluck('tb_school_info.id');
        $query->whereIn('campus_id',$campuses);
        if ($user->isSuperAdmin() || (StudentSkippingGradePermissions::allows($user,'requests') && !$this->isConfiguredReviewer($user))) return $query;
        $assignments=$this->configuredAssignments($user);
        return $query->where(function ($visible) use ($user,$assignments) {
            $visible->where('created_by',$user->id)->orWhere('submitted_by',$user->id)
                ->orWhereHas('approvalSteps',fn($steps)=>$steps->where(fn($q)=>$q->assignedTo($user->id)->orWhere('acted_by',$user->id)));
            foreach ($assignments as $member) {
                $visible->orWhere(function ($grade) use ($member) {
                    if ($member['campus_id']!==null) $grade->where('campus_id',$member['campus_id']);
                    if (($member['grade_scope']??'all')!=='all') $grade->whereIn('source_grade_id',$member['grade_ids']??[]);
                    else $grade->whereRaw('1 = 1');
                });
            }
        });
    }

    public function visibleRequests(Builder $query, ?User $user): Builder
    {
        if (!$user || $user->isSuperAdmin() || !$this->isConfiguredReviewer($user)) return $query;
        $assignments = $this->configuredAssignments($user);
        return $query->where(fn($q) => $q->where('approval_flow_mode', '!=', 'sequential')->orWhere(function ($sequential) use ($user, $assignments) {
            $sequential->where('approval_flow_mode', 'sequential')->where(function ($visible) use ($user, $assignments) {
                $visible->where('created_by', $user->id)->orWhere('submitted_by', $user->id)->orWhereHas('approvalSteps', fn($steps) => $steps->where(fn($q) => $q->assignedTo($user->id)->orWhere('acted_by', $user->id)));
                if ($assignments) $visible->orWhere(function ($drafts) use ($user, $assignments) {
                    $drafts->where('status', 'draft')->where(function ($grades) use ($user, $assignments) {
                        $grades->whereRaw('1 = 0');
                        foreach ($assignments as $member) {
                            if ($member['campus_id'] !== null && !$user->canAccessCampus((int)$member['campus_id'])) continue;
                            $grades->orWhere(function ($scope) use ($member) {
                                if ($member['campus_id'] !== null) $scope->where('campus_id', $member['campus_id']);
                                if (($member['grade_scope'] ?? 'all') !== 'all') $scope->whereIn('source_grade_id', $member['grade_ids'] ?? []);
                                else $scope->whereRaw('1 = 1');
                            });
                        }
                    });
                });
            });
        }));
    }

    public function currentStep(StudentSkippingGrade $record): ?SkippingGradeApprovalStep
    {
        return $record->approvalSteps()->with('reviewers')->where('status', 'pending')->first();
    }

    public function canAct(User $user, StudentSkippingGrade $record): bool
    {
        $step = $this->currentStep($record);
        return $record->usesSequentialFlow() && $record->status === 'pending' && (int)$user->status === 1
            && StudentSkippingGradePermissions::allows($user, 'view') && $step && $record->approvalSteps()->whereKey($step->id)->assignedTo($user->id)->exists()
            && ($step->scope !== 'campus' || $user->canAccessCampus($record->campus_id));
    }

    // The request row is locked by every caller before checking the current step.
    public function decisionStep(User $user, StudentSkippingGrade $record, array $expected, ?bool $final = null): SkippingGradeApprovalStep
    {
        abort_unless($this->canAct($user, $record), 403, 'Only the current assigned reviewer can decide this request.');
        $step = $this->currentStep($record);
        if ((int)($expected['approval_step_id'] ?? 0) !== $step->id || (int)($expected['approval_step_revision'] ?? 0) !== $step->revision) {
            $this->invalid('status', 'The assigned approval step changed. Reload the request before continuing.');
        }
        if ($final !== null && $step->is_final !== $final) $this->invalid('status', $final ? 'The Vice President gives final approval after all earlier steps.' : 'Use the final approval form for the Vice President step.');
        return $step;
    }

    public function complete(SkippingGradeApprovalStep $step, User $user, string $status, ?string $comments = null): void
    {
        $title=$step->reviewers()->where('user_id',$user->id)->first()?->reviewer_snapshot['title']??$step->title;
        $step->update(['status' => $status, 'acted_by' => $user->id, 'acted_at' => now(), 'comments' => $comments,
            'actor_snapshot' => $this->snapshot($user, $title, $step->scope, $step->reviewer_snapshot['campus_id'] ?? null)]);
    }

    public function review(User $user, StudentSkippingGrade $record, array $data): void
    {
        DB::transaction(function () use ($user, $record, $data) {
            $record = StudentSkippingGrade::whereKey($record->id)->lockForUpdate()->firstOrFail();
            $step = $this->decisionStep($user, $record, $data, false);
            $this->complete($step, $user, 'approved', $data['comments'] ?? null);
            $next = $record->approvalSteps()->where('sequence', '>', $step->sequence)->where('status', 'waiting')->first();
            if (!$next) $this->invalid('status', 'The saved workflow has no final approval step. Contact Super Admin.');
            $next->update(['status' => 'pending']);
            $record->update(['updated_by' => $user->id]);
            app(SkippingGradeNotifications::class)->awaiting($record);
        });
    }

    public function reassign(User $admin, StudentSkippingGrade $record, int $stepId, array $data): void
    {
        abort_unless($admin->isSuperAdmin(), 403);
        $files = [];
        try {
            DB::transaction(function () use ($admin, $record, $stepId, $data, &$files) {
                $record = StudentSkippingGrade::whereKey($record->id)->lockForUpdate()->firstOrFail();
                $step = $record->approvalSteps()->whereKey($stepId)->firstOrFail();
                if (!$record->usesSequentialFlow() || $record->status !== 'pending' || !in_array($step->status, ['waiting', 'pending'], true)) $this->invalid('status', 'Only unfinished steps of a pending request can be reassigned.');
                if ((int)$data['revision'] !== $step->revision) $this->invalid('revision', 'This step changed. Reload the request before reassigning it.');
                $member = isset($data['member_id']) ? $step->reviewers()->whereKey($data['member_id'])->firstOrFail() : $step->reviewers()->first();
                if ($step->reviewers()->count() > 1 && !isset($data['member_id'])) $this->invalid('member_id', 'Choose which reviewer to replace in this step.');
                $previous = $member?->reviewer_snapshot ?? $step->reviewer_snapshot;
                $replacement = $this->reviewer((int)$data['reviewer_id'], $step->scope === 'campus' ? $record->campus_id : null);
                if ((int)($member?->user_id ?? $step->reviewer_id) === $replacement->id) $this->invalid('reviewer_id', 'Select a different reviewer.');
                if ($step->reviewers()->where('user_id', $replacement->id)->exists()) $this->invalid('reviewer_id', 'This user is already a reviewer for the step.');
                $snapshot = $this->snapshot($replacement, $previous['title']??$step->title, $step->scope, $step->scope === 'campus' ? $record->campus_id : null);
                foreach (['grade_scope', 'grade_ids', 'grades'] as $key) if (isset($previous[$key])) $snapshot[$key] = $previous[$key];
                $step->reassignments()->create(['previous_reviewer' => $previous, 'replacement_reviewer' => $snapshot,
                    'changed_by' => $admin->id, 'changed_by_name' => $admin->name, 'reason' => trim($data['reason']), 'created_at' => now()]);
                if ($member) $member->update(['user_id' => $replacement->id, 'reviewer_snapshot' => $snapshot]);
                $values = ['revision' => $step->revision + 1];
                if (!$member || $step->reviewers()->first()->id === $member->id) $values += ['reviewer_id' => $replacement->id, 'reviewer_snapshot' => $snapshot];
                $step->update($values);
                $values = ['updated_by' => $admin->id];
                if ($step->is_final) $values['workflow_signer_snapshot'] = $this->captureSigner($record, SkippingGradeSetting::whereKey(1)->lockForUpdate()->firstOrFail(), $files);
                $record->update($values);
                if ($step->status === 'pending') app(SkippingGradeNotifications::class)->awaiting($record);
            });
        } catch (\Throwable $e) { Storage::disk('local')->delete($files); throw $e; }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
