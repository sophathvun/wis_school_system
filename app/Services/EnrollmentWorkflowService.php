<?php

namespace App\Services;

use App\Models\EnrollmentWorkflowAction;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\StudentEnrollment;
use App\Models\StudentEnrollmentHistory;
use App\Models\StudentSkippingGrade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentWorkflowService
{
    private const PROMOTION_ACTIONS = ['promotion', 'class_promotion', 'selected_promotion', 're_promotion'];
    private const TRANSFER_ACTIONS = ['transfer', 'class_transfer', 'selected_transfer'];

    public function promoteClass(array $data): array
    {
        return $this->promoteBatch($data, false);
    }

    public function promoteSelected(array $data): array
    {
        return $this->promoteBatch($data, true);
    }

    public function approvedGradeSkippingTargets(Collection $sources, int $yearId): Collection
    {
        if ($sources->isEmpty()) return collect();
        $sources=$sources->keyBy('id');
        return StudentSkippingGrade::with(['targetEnrollment.grade','targetEnrollment.schoolClass','targetEnrollment.academicYear'])
            ->whereIn('enrollment_id',$sources->keys())->where('status','approved')
            ->where('target_academic_year_id',$yearId)->get()->filter(function ($request) use ($sources,$yearId) {
                $source=$sources->get($request->enrollment_id);
                $target=$request->targetEnrollment;
                return $target && $target->grade && $target->schoolClass && $target->academicYear
                    && $target->status && in_array($target->enrollment_status,['active','pending'],true)
                    && (int)$source->academic_year_id !== $yearId
                    && (int)$request->academic_year_id === (int)$source->academic_year_id
                    && (int)$request->student_id === (int)$source->student_id
                    && (int)$target->student_id === (int)$source->student_id
                    && (int)$target->academic_year_id === $yearId
                    && (int)$target->campus_id === (int)$request->campus_id
                    && (int)$target->grade_id === (int)$request->target_grade_id
                    && (int)$target->class_id === (int)$request->target_class_id
                    && (string)$target->session_id === (string)$request->target_session_id;
            })->keyBy('enrollment_id');
    }

    public function gradeSkippingSummary(StudentSkippingGrade $request): array
    {
        $target=$request->targetEnrollment;
        return [
            'enrollment_id'=>$request->enrollment_id,
            'student_id'=>$request->student_snapshot['student_id']??'',
            'student_name'=>$request->student_snapshot['name_en']??'',
            'reference_number'=>$request->reference_number,
            'target_enrollment_id'=>$target->id,
            'academic_year'=>$target->academicYear->academic_year,
            'grade'=>$target->grade->grade_short_name ?: $target->grade->grade,
            'class'=>$target->schoolClass->class_name,
        ];
    }

    private function promoteBatch(array $data, bool $selected): array
    {
        return DB::transaction(function () use ($data,$selected) {
            $field=$selected?'enrollment_ids':'from_class_id';
            $sources=StudentEnrollment::where('campus_id',$data['from_campus_id'])
                ->where('academic_year_id',$data['from_academic_year_id'])
                ->where('grade_id',$data['from_grade_id'])->where('class_id',$data['from_class_id'])
                ->when($selected,fn($query)=>$query->whereIn('id',$data['enrollment_ids']))
                ->orderBy('id')->lockForUpdate()->get();
            $alreadyPromoted=EnrollmentWorkflowAction::whereIn('student_id',$sources->pluck('student_id'))
                ->where('from_campus_id',$data['from_campus_id'])->where('from_academic_year_id',$data['from_academic_year_id'])
                ->where('from_grade_id',$data['from_grade_id'])->where('from_class_id',$data['from_class_id'])
                ->where('to_academic_year_id',$data['to_academic_year_id'])->whereIn('action_type',self::PROMOTION_ACTIONS)
                ->where('status','!=','cancelled')->exists();
            if ($alreadyPromoted) throw ValidationException::withMessages([$field=>'Students in this class have already been promoted to the selected academic year.']);
            $sources=$sources->filter(fn($source)=>$source->status && $source->enrollment_status==='active')->values();
            if ($selected && $sources->count()!==count(array_unique($data['enrollment_ids']))) {
                throw ValidationException::withMessages([$field=>'One or more selected students are not active in the selected class.']);
            }
            if ($sources->isEmpty()) throw ValidationException::withMessages([$field=>'No active students were found in the selected class.']);
            $skipping=$this->approvedGradeSkippingTargets($sources,(int)$data['to_academic_year_id']);
            $eligible=$sources->reject(fn($source)=>$skipping->has($source->id));
            if (StudentEnrollment::whereIn('student_id',$eligible->pluck('student_id'))->where('academic_year_id',$data['to_academic_year_id'])->exists()) {
                throw ValidationException::withMessages([$field=>'A student already has an enrollment in the target academic year without a matching approved grade-skipping placement. Review the existing enrollment before promoting.']);
            }
            foreach ($eligible as $source) {
                $this->promote($source,[
                    'to_campus_id'=>$data['to_campus_id']??$data['from_campus_id'],
                    'to_academic_year_id'=>$data['to_academic_year_id'],'to_grade_id'=>$data['to_grade_id'],
                    'to_class_id'=>$data['to_class_id'],'to_session_id'=>$data['to_session_id']??null,
                    'effective_on'=>$data['effective_on'],'reason'=>$data['reason']??($selected?'Selected student promotion':'Class promotion'),
                    'notes'=>$data['notes']??null,'action_type'=>$selected?'selected_promotion':'class_promotion',
                ]);
            }
            return ['count'=>$eligible->count(),'skipped_count'=>$skipping->count(),
                'skipped_students'=>$skipping->values()->map(fn($request)=>$this->gradeSkippingSummary($request))->all()];
        });
    }

    public function transferSelected(array $data): int
    {
        return DB::transaction(function () use ($data) {
            $enrollments = StudentEnrollment::whereIn('id', $data['enrollment_ids'])
                ->where('campus_id', $data['from_campus_id'])
                ->where('academic_year_id', $data['from_academic_year_id'])
                ->where('grade_id', $data['from_grade_id'])
                ->where('class_id', $data['from_class_id'])
                ->where('status', 1)
                ->where('enrollment_status', 'active')
                ->get();

            if ($enrollments->count() !== count(array_unique($data['enrollment_ids']))) {
                throw ValidationException::withMessages(['enrollment_ids' => 'One or more selected students are not active in the selected class.']);
            }

            foreach ($enrollments as $enrollment) {
                $this->transfer($enrollment, [
                    'to_campus_id' => $data['to_campus_id'],
                    'to_grade_id' => $data['to_grade_id'] ?? null,
                    'to_class_id' => $data['to_class_id'] ?? null,
                    'to_session_id' => $data['to_session_id'] ?? null,
                    'effective_on' => $data['effective_on'],
                    'reason' => $data['reason'] ?? 'Selected student transfer',
                    'notes' => $data['notes'] ?? null,
                    'action_type' => 'selected_transfer',
                ]);
            }

            return $enrollments->count();
        });
    }

    public function transferClass(array $data): int
    {
        return DB::transaction(function () use ($data) {
            $enrollments = StudentEnrollment::where('campus_id', $data['from_campus_id'])
                ->where('academic_year_id', $data['from_academic_year_id'])
                ->where('grade_id', $data['from_grade_id'])
                ->where('class_id', $data['from_class_id'])
                ->where('enrollment_status', 'active')
                ->get();

            if ($enrollments->isEmpty()) {
                throw ValidationException::withMessages(['from_class_id' => 'No active students were found in the selected class.']);
            }

            foreach ($enrollments as $enrollment) {
                $this->transfer($enrollment, [
                    'to_campus_id' => $data['to_campus_id'],
                    'to_grade_id' => $data['to_grade_id'] ?? null,
                    'to_class_id' => $data['to_class_id'] ?? null,
                    'to_session_id' => $data['to_session_id'] ?? null,
                    'effective_on' => $data['effective_on'],
                    'reason' => $data['reason'] ?? 'Class transfer',
                    'notes' => $data['notes'] ?? null,
                    'action_type' => 'class_transfer',
                ]);
            }

            return $enrollments->count();
        });
    }

    public function promote(StudentEnrollment $source, array $data): StudentEnrollment
    {
        return DB::transaction(function () use ($source, $data) {
            $source=StudentEnrollment::lockForUpdate()->findOrFail($source->id);
            $skipping=$this->approvedGradeSkippingTargets(collect([$source]),(int)$data['to_academic_year_id'])->first();
            if ($skipping) return $skipping->targetEnrollment->setAttribute('promotion_skipped',true);
            $cancelledPromotion = EnrollmentWorkflowAction::query()
                ->where('source_enrollment_id', $source->id)
                ->where('to_academic_year_id', $data['to_academic_year_id'])
                ->where('status', 'cancelled')
                ->whereIn('action_type', self::PROMOTION_ACTIONS)
                ->latest('id')
                ->first();
            if ($cancelledPromotion) {
                return $this->repromote($cancelledPromotion, [
                    'effective_on' => $data['effective_on'],
                    'reason' => $data['reason'] ?? 'Student returned and was promoted again',
                    'notes' => $data['notes'] ?? null,
                ]);
            }

            $this->assertNextGradePromotion($source, (int) $data['to_grade_id']);

            $duplicate = StudentEnrollment::where('student_id', $source->student_id)
                ->where('academic_year_id', $data['to_academic_year_id'])
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['to_academic_year_id' => 'This student already has an enrollment in the target academic year.']);
            }

            $target = StudentEnrollment::create([
                'student_id' => $source->student_id,
                'campus_id' => $data['to_campus_id'] ?? $source->campus_id,
                'academic_year_id' => $data['to_academic_year_id'],
                'grade_id' => $data['to_grade_id'],
                'class_id' => $data['to_class_id'],
                'session_id' => $data['to_session_id'] ?? null,
                'group_id' => null,
                'status' => 1,
                'student_type' => 'old',
                'enrollment_status' => AcademicYear::findOrFail($data['to_academic_year_id'])->lifecycle_status === 'started' ? 'active' : 'pending',
                'enrolled_on' => $data['effective_on'],
            ]);

            $source->update(['status' => 1, 'enrollment_status' => 'completed', 'ended_on' => $data['effective_on'], 'exit_reason' => 'Promoted']);
            $this->record($source, $target, $data['action_type'] ?? 'promotion', $data);
            return $target;
        });
    }

    public function cancelPromotion(EnrollmentWorkflowAction $workflow, array $data): EnrollmentWorkflowAction
    {
        return DB::transaction(function () use ($workflow, $data) {
            $workflow = EnrollmentWorkflowAction::query()->lockForUpdate()->findOrFail($workflow->id);
            if (!in_array($workflow->action_type, self::PROMOTION_ACTIONS, true)) {
                throw ValidationException::withMessages(['workflow' => 'Only promotion records can be cancelled.']);
            }
            if ($workflow->status === 'cancelled') {
                throw ValidationException::withMessages(['workflow' => 'This promotion is already cancelled.']);
            }

            $target = StudentEnrollment::lockForUpdate()->find($workflow->target_enrollment_id);
            if (!$target) {
                throw ValidationException::withMessages(['workflow' => 'The promoted enrollment no longer exists.']);
            }

            if (StudentSkippingGrade::where('target_enrollment_id',$target->id)->where('status','approved')->exists()) {
                throw ValidationException::withMessages(['workflow'=>'This placement is linked to an approved grade-skipping request. Central Office must review it before the promotion can be cancelled.']);
            }

            $target->update([
                'enrollment_status' => 'promotion_cancelled',
                'ended_on' => $data['effective_on'],
                'exit_reason' => 'Promotion cancelled',
                'notes' => $data['notes'] ?? $target->notes,
            ]);
            $workflow->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancellation_reason' => $data['reason'] ?? 'Student will not study in the target academic year.',
                'notes' => $data['notes'] ?? $workflow->notes,
            ]);
            $this->recordEnrollmentHistory($target, 'promotion_cancelled', $data['effective_on'], $data['reason'] ?? 'Promotion cancelled', $data['notes'] ?? null);

            return $workflow->fresh();
        });
    }

    public function repromote(EnrollmentWorkflowAction $workflow, array $data): StudentEnrollment
    {
        return DB::transaction(function () use ($workflow, $data) {
            $workflow = EnrollmentWorkflowAction::query()->lockForUpdate()->findOrFail($workflow->id);
            if (!in_array($workflow->action_type, self::PROMOTION_ACTIONS, true) || $workflow->status !== 'cancelled') {
                throw ValidationException::withMessages(['workflow' => 'Only a cancelled promotion can be promoted again.']);
            }
            $target = StudentEnrollment::lockForUpdate()->find($workflow->target_enrollment_id);
            if (!$target) {
                throw ValidationException::withMessages(['workflow' => 'The original target enrollment no longer exists.']);
            }
            $activeDuplicate = StudentEnrollment::where('student_id', $workflow->student_id)
                ->where('academic_year_id', $workflow->to_academic_year_id)
                ->where('enrollment_status', 'active')
                ->where('id', '!=', $target->id)
                ->exists();
            if ($activeDuplicate) {
                throw ValidationException::withMessages(['workflow' => 'This student already has an active enrollment in the target academic year.']);
            }

            $targetYear = AcademicYear::findOrFail($workflow->to_academic_year_id);
            $target->update([
                'status' => 1,
                'enrollment_status' => $targetYear->lifecycle_status === 'started' ? 'active' : 'pending',
                'enrolled_on' => $data['effective_on'],
                'ended_on' => null,
                'exit_reason' => null,
                'notes' => $data['notes'] ?? $target->notes,
            ]);
            $this->record($target, $target, 're_promotion', [
                'effective_on' => $data['effective_on'],
                'reason' => $data['reason'] ?? 'Student returned and was promoted again',
                'notes' => $data['notes'] ?? null,
                'action_type' => 're_promotion',
                'parent_workflow_id' => $workflow->id,
            ], [
                'campus_id' => $workflow->from_campus_id,
                'academic_year_id' => $workflow->from_academic_year_id,
                'grade_id' => $workflow->from_grade_id,
                'class_id' => $workflow->from_class_id,
                'session_id' => $workflow->from_session_id,
            ]);

            return $target->fresh();
        });
    }

    private function assertNextGradePromotion(StudentEnrollment $source, int $targetGradeId): void
    {
        $grades = Grade::where('status', 1)
            ->orderByRaw('CAST(grade_order AS UNSIGNED)')
            ->get(['id', 'grade', 'grade_order'])
            ->values();

        $sourceIndex = $grades->search(fn ($grade) => (int) $grade->id === (int) $source->grade_id);
        if ($sourceIndex === false) {
            throw ValidationException::withMessages(['to_grade_id' => 'Unable to verify the current grade for this student.']);
        }

        $sourceGrade = $grades[$sourceIndex];
        $nextGrade = $grades->get($sourceIndex + 1);

        if (!$nextGrade) {
            throw ValidationException::withMessages([
                'to_grade_id' => "{$sourceGrade->grade} students should be processed from the Student Graduation page.",
            ]);
        }

        if ((int) $nextGrade->id !== $targetGradeId) {
            throw ValidationException::withMessages([
                'to_grade_id' => "Students from {$sourceGrade->grade} can only be promoted to {$nextGrade->grade}. Lower grades are not allowed.",
            ]);
        }
    }

    public function transfer(StudentEnrollment $source, array $data): StudentEnrollment
    {
        return DB::transaction(function () use ($source, $data) {
            $before = $source->only(['campus_id', 'academic_year_id', 'grade_id', 'class_id', 'session_id']);
            $targetAcademicYearId = $data['to_academic_year_id'] ?? $source->academic_year_id;
            $targetCampusId = $data['to_campus_id'] ?? $source->campus_id;
            $targetGradeId = $data['to_grade_id'] ?? $source->grade_id;
            $targetClassId = $data['to_class_id'] ?? $source->class_id;
            $targetSessionId = $data['to_session_id'] ?? $source->session_id;

            $source->update([
                'academic_year_id' => $targetAcademicYearId,
                'campus_id' => $targetCampusId,
                'grade_id' => $targetGradeId,
                'class_id' => $targetClassId,
                'session_id' => $targetSessionId,
                'status' => 1,
                'enrollment_status' => 'active',
                'ended_on' => null,
                'exit_reason' => null,
                'notes' => $data['notes'] ?? $source->notes,
            ]);

            // Keep transfer as one enrollment row. The workflow record stores
            // the campus/class movement for history and future accounting use.
            $this->record($source, $source, $data['action_type'] ?? 'transfer', $data, $before);

            return $source->fresh();
        });
    }

    public function reverseTransfer(EnrollmentWorkflowAction $workflow, array $data): StudentEnrollment
    {
        return DB::transaction(function () use ($workflow, $data) {
            $workflow = EnrollmentWorkflowAction::query()->lockForUpdate()->findOrFail($workflow->id);
            if (!in_array($workflow->action_type, self::TRANSFER_ACTIONS, true)) {
                throw ValidationException::withMessages(['workflow' => 'Only transfer records can be reversed.']);
            }
            if ($workflow->status === 'reversed') {
                throw ValidationException::withMessages(['workflow' => 'This transfer is already reversed.']);
            }

            $enrollment = StudentEnrollment::lockForUpdate()->find($workflow->target_enrollment_id ?: $workflow->source_enrollment_id);
            if (!$enrollment) {
                throw ValidationException::withMessages(['workflow' => 'The transferred enrollment no longer exists.']);
            }

            $enrollment->update([
                'academic_year_id' => $workflow->from_academic_year_id ?: $enrollment->academic_year_id,
                'campus_id' => $workflow->from_campus_id ?: $enrollment->campus_id,
                'grade_id' => $workflow->from_grade_id ?: $enrollment->grade_id,
                'class_id' => $workflow->from_class_id ?: $enrollment->class_id,
                'session_id' => $workflow->from_session_id ?: $enrollment->session_id,
                'status' => 1,
                'enrollment_status' => 'active',
                'ended_on' => null,
                'exit_reason' => null,
                'notes' => $data['notes'] ?? $enrollment->notes,
            ]);

            $workflow->update([
                'status' => 'reversed',
                'notes' => $data['notes'] ?? $workflow->notes,
            ]);

            $this->recordEnrollmentHistory($enrollment, 'reverse_transfer', $data['effective_on'], $data['reason'] ?? 'Transfer reversed', $data['notes'] ?? null);

            return $enrollment->fresh();
        });
    }

    private function record(StudentEnrollment $source, StudentEnrollment $target, string $action, array $data, array $before = []): void
    {
        $to = $target->only(['campus_id', 'academic_year_id', 'grade_id', 'class_id', 'session_id']);
        EnrollmentWorkflowAction::create([
            'student_id' => $source->student_id,
            'source_enrollment_id' => $source->id,
            'target_enrollment_id' => $target->id,
            'parent_workflow_id' => $data['parent_workflow_id'] ?? null,
            'action_type' => $action,
            'status' => 'completed',
            'from_campus_id' => $before['campus_id'] ?? $source->campus_id,
            'to_campus_id' => $to['campus_id'],
            'from_academic_year_id' => $before['academic_year_id'] ?? $source->academic_year_id,
            'to_academic_year_id' => $to['academic_year_id'],
            'from_grade_id' => $before['grade_id'] ?? $source->grade_id,
            'to_grade_id' => $to['grade_id'],
            'from_class_id' => $before['class_id'] ?? $source->class_id,
            'to_class_id' => $to['class_id'],
            'from_session_id' => $before['session_id'] ?? $source->session_id,
            'to_session_id' => $to['session_id'],
            'effective_on' => $data['effective_on'],
            'reason' => $data['reason'] ?? null,
            'notes' => $data['notes'] ?? null,
            'changed_by' => auth()->id(),
        ]);

        $this->recordEnrollmentHistory($target, $action, $data['effective_on'], $data['reason'] ?? null, $data['notes'] ?? null);
    }

    private function recordEnrollmentHistory(StudentEnrollment $target, string $action, $effectiveOn, ?string $reason, ?string $notes): void
    {
        StudentEnrollmentHistory::create([
            'enrollment_id' => $target->id,
            'student_id' => $target->student_id,
            'action_type' => $action,
            'campus_id' => $target->campus_id,
            'academic_year_id' => $target->academic_year_id,
            'grade_id' => $target->grade_id,
            'class_id' => $target->class_id,
            'session_id' => $target->session_id,
            'enrollment_status' => $target->enrollment_status,
            'student_type' => $target->student_type,
            'effective_on' => $effectiveOn,
            'reason' => $reason,
            'notes' => $notes,
            'changed_by' => auth()->id(),
        ]);
    }
}
