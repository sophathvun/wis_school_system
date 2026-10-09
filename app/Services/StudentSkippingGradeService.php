<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Session;
use App\Models\SkippingGradeSetting;
use App\Models\SkippingGradeCampusSetting;
use App\Models\StudentEnrollment;
use App\Models\StudentEnrollmentHistory;
use App\Models\StudentSkippingGrade;
use App\Models\User;
use App\Support\StudentSkippingGradePermissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentSkippingGradeService
{
    public function authorize(User $user, string $action, ?int $campus = null): void
    {
        abort_unless(StudentSkippingGradePermissions::allows($user,$action,$campus),403);
    }

    public function saveDraft(User $user, array $data, ?StudentSkippingGrade $record = null): StudentSkippingGrade
    {
        if ($record) $this->authorizeEdit($user,$record);
        else $this->authorize($user,'create');
        return DB::transaction(function () use ($user,$data,$record) {
            if ($record) {
                $record=StudentSkippingGrade::whereKey($record->id)->lockForUpdate()->firstOrFail();
                $this->authorizeEdit($user,$record);
                if ((int)$data['enrollment_id'] !== $record->enrollment_id) $this->invalid('enrollment_id','The student cannot be changed on an existing request.');
            }
            $enrollment=StudentEnrollment::with(['student','campus','academicYear','grade','schoolClass','session'])->whereKey($data['enrollment_id'])->lockForUpdate()->firstOrFail();
            if (!$record) $this->authorize($user,'create',$enrollment->campus_id);
            $this->activeSource($enrollment);
            if ($record) $this->unchangedSource($record,$enrollment);
            if (StudentSkippingGrade::where('enrollment_id',$enrollment->id)->whereIn('status',['draft','pending'])->when($record,fn($q)=>$q->where('id','!=',$record->id))->exists()) {
                $this->invalid('enrollment_id','This student already has a draft or submitted grade skipping request.');
            }
            [$grade,$class,$year]=$this->target($enrollment,$data);
            $this->noDuplicateTargetEnrollment($enrollment,$year);
            if (isset($data['average_score']) && $data['average_score'] > $data['average_scale']) $this->invalid('average_score','The average cannot exceed the selected score scale.');
            $snapshot=[
                'name_en'=>$enrollment->student->full_name_en,'name_kh'=>$enrollment->student->full_name_kh,
                'student_id'=>$enrollment->student->student_id,'date_of_birth'=>$enrollment->student->date_of_birth?->format('Y-m-d'),
                'gender'=>$enrollment->student->gender,'campus_en'=>$enrollment->campus->campus_name_en,'campus_kh'=>$enrollment->campus->campus_name_kh,
                'academic_year'=>$enrollment->academicYear->academic_year,
                'target_academic_year'=>$year->academic_year,
                'source_grade'=>$enrollment->grade->grade_short_name ?: $enrollment->grade->grade,
                'source_class'=>$enrollment->schoolClass->class_name,
                'target_grade'=>$grade->grade_short_name ?: $grade->grade,'target_class'=>$class->class_name,
            ];
            $values=collect($data)->only(['target_grade_id','target_class_id','target_session_id','application_date','parent_name','parent_phone','reason','criteria','average_score','average_scale','committee_names'])->all();
            $values['target_academic_year_id']=$year->id;
            $values['parent_name']=Str::upper($values['parent_name']);
            $values['committee_names']=SkippingGradeSetting::current()->requestCommitteeNames(
                $data['committee_names']??$record?->committee_names??SkippingGradeCampusSetting::namesFor($enrollment->campus_id));
            $values['criteria']['custom_options']=app(SkippingGradeTemplateService::class)->captureCheckboxOptions(
                SkippingGradeSetting::current()->request_template??[],$data['custom_options']??[]);
            $values+=['student_snapshot'=>$snapshot,'updated_by'=>$user->id];
            if (!$record) {
                $values+=['enrollment_id'=>$enrollment->id,'student_id'=>$enrollment->student_id,'campus_id'=>$enrollment->campus_id,
                    'academic_year_id'=>$enrollment->academic_year_id,'source_grade_id'=>$enrollment->grade_id,'source_class_id'=>$enrollment->class_id,
                    'source_session_id'=>$enrollment->session_id,'created_by'=>$user->id,'status'=>'draft'];
                return StudentSkippingGrade::create($values);
            }
            $record->update($values);
            return $record->fresh();
        });
    }

    public function authorizeEdit(User $user, StudentSkippingGrade $record): void
    {
        abort_unless(StudentSkippingGradePermissions::canEdit($user,$record),403,'This request cannot be edited with your current permissions or status.');
    }

    public function submit(User $user, StudentSkippingGrade $record, string $signedDate, ?UploadedFile $file = null): StudentSkippingGrade
    {
        $this->authorize($user,'submit',$record->campus_id);
        $path=null;
        try {
            return DB::transaction(function () use ($user,$record,$signedDate,$file,&$path) {
                $record=StudentSkippingGrade::whereKey($record->id)->lockForUpdate()->firstOrFail();
                $this->requireStatus($record,'draft');
                $enrollment=StudentEnrollment::whereKey($record->enrollment_id)->lockForUpdate()->firstOrFail();
                $this->activeSource($enrollment); $this->unchangedSource($record,$enrollment);
                [,,$year]=$this->target($enrollment,$record->toArray());
                $this->noDuplicateTargetEnrollment($enrollment,$year);
                if ($file) {
                    $path=$file->store('grade-skipping/signed-requests','local');
                    if (!$path) $this->invalid('signed_request','Unable to store the signed request. Please try again.');
                }
                $record->update(['status'=>'pending','parent_signed_at'=>$signedDate,'signed_request_path'=>$path,
                    'committee_names'=>SkippingGradeSetting::current()->requestCommitteeNames($record->committee_names??[]),
                    'submitted_at'=>now(),'submitted_by'=>$user->id,'updated_by'=>$user->id]);
                return $record->fresh();
            });
        } catch (\Throwable $e) {
            if ($path) Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function approve(User $user, StudentSkippingGrade $record, array $data): StudentSkippingGrade
    {
        $this->authorize($user,'approve',$record->campus_id);
        $files=[];
        try {
            return DB::transaction(function () use ($user,$record,$data,&$files) {
                $record=StudentSkippingGrade::whereKey($record->id)->lockForUpdate()->firstOrFail();
                $this->requireStatus($record,'pending');
                if (!$record->parent_signed_at) $this->invalid('status','The parent-signed request has not been submitted.');
                if (empty($data['vp_signed'])) $this->invalid('vp_signed','Confirm that the VP has signed the final request.');
                $enrollment=StudentEnrollment::with(['student','academicYear','grade'])->whereKey($record->enrollment_id)->lockForUpdate()->firstOrFail();
                $this->activeSource($enrollment); $this->unchangedSource($record,$enrollment);
                [$targetGrade,,$targetYear]=$this->target($enrollment,$record->toArray());
                $this->noDuplicateTargetEnrollment($enrollment,$targetYear);
                $yearCode=trim((string)$enrollment->academicYear->ay_code);
                if ($yearCode==='') $this->invalid('settings','Set the AY Code for the current academic year in Academic Year settings before approving.');
                $settings=SkippingGradeSetting::whereKey(1)->lockForUpdate()->firstOrFail();
                $customOptions=app(SkippingGradeTemplateService::class)->captureCheckboxOptions($settings->approval_template??[],$data['custom_options']??[]);
                if (!$settings->signer_name_kh) $this->invalid('settings','Save the VP name, digital signature, and stamp in Approval Settings first.');
                $snapshot=$settings->only(['signer_name_kh','signer_name_en','signer_title_kh','signer_title_en']);
                foreach (['signature_path','stamp_path'] as $key) {
                    $source=$settings->{$key};
                    if (!$source || !Storage::disk('local')->exists($source)) $this->invalid('settings','Upload the VP digital signature and approval stamp in Approval Settings first.');
                    $copy='grade-skipping/approvals/'.$record->id.'/'.Str::uuid().'.'.pathinfo($source,PATHINFO_EXTENSION);
                    $files[]=$copy;
                    if (!Storage::disk('local')->copy($source,$copy)) $this->invalid('settings','Unable to prepare the approval signature and stamp. Please try again.');
                    $snapshot[$key]=$copy;
                }
                $snapshot['notes']=$data['approval_notes'] ?? '';
                $snapshot['age_decision']=$data['age_decision'] ?? 'standard';
                $snapshot['school_decision']=$data['school_decision'] ?? 'internal';
                $snapshot['obligations']=array_map(fn($value)=>(bool)$value,$data['obligations'] ?? []);
                $snapshot['custom_options']=$customOptions;
                $reference=$settings->number_prefix.$yearCode.'-'.str_pad((string)$record->id,3,'0',STR_PAD_LEFT);
                $history=[
                    'enrollment_id'=>$enrollment->id,'student_id'=>$enrollment->student_id,'campus_id'=>$enrollment->campus_id,
                    'academic_year_id'=>$enrollment->academic_year_id,'grade_id'=>$enrollment->grade_id,'class_id'=>$enrollment->class_id,
                    'academic_track_id'=>$enrollment->academic_track_id,'session_id'=>$enrollment->session_id,
                    'enrollment_status'=>$enrollment->enrollment_status,'student_type'=>$enrollment->student_type,
                    'effective_on'=>$data['effective_date'],'reason'=>'Approved grade skipping: '.$reference,'notes'=>$record->reason,'changed_by'=>$user->id,
                ];
                $before=StudentEnrollmentHistory::create($history+['action_type'=>'grade_skipping_from']);
                $targetValues=[
                    'grade_id'=>$record->target_grade_id,'class_id'=>$record->target_class_id,'session_id'=>$record->target_session_id,'group_id'=>null,
                    'academic_track_id'=>$enrollment->grade->education_level_id == $targetGrade->education_level_id ? $enrollment->academic_track_id : null,
                ];
                if ($targetYear->id == $enrollment->academic_year_id) {
                    $enrollment->update($targetValues);
                    $targetEnrollment=$enrollment;
                } else {
                    $targetEnrollment=StudentEnrollment::create($targetValues+[
                        'student_id'=>$enrollment->student_id,'campus_id'=>$enrollment->campus_id,'academic_year_id'=>$targetYear->id,
                        'status'=>true,'student_type'=>'old','enrollment_status'=>$targetYear->isCurrent()?'active':'pending','enrolled_on'=>$data['effective_date'],
                    ]);
                }
                StudentEnrollmentHistory::create(array_replace($history,[
                    'action_type'=>'grade_skipping','source_history_id'=>$before->id,'grade_id'=>$record->target_grade_id,
                    'enrollment_id'=>$targetEnrollment->id,'academic_year_id'=>$targetYear->id,'enrollment_status'=>$targetEnrollment->enrollment_status,'student_type'=>$targetEnrollment->student_type,
                    'class_id'=>$record->target_class_id,'session_id'=>$record->target_session_id,'academic_track_id'=>$targetEnrollment->academic_track_id,
                ]));
                $record->update([
                    'status'=>'approved','reference_number'=>$reference,'approval_snapshot'=>$snapshot,
                    'target_enrollment_id'=>$targetEnrollment->id,
                    'approved_by'=>$user->id,'approved_at'=>now(),'updated_by'=>$user->id,
                    'approval_date'=>$data['approval_date'],'received_date'=>$data['received_date'],
                    'review_date'=>$data['review_date'],'effective_date'=>$data['effective_date'],
                ]);
                return $record->fresh();
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($files);
            throw $e;
        }
    }

    public function reject(User $user, StudentSkippingGrade $record, string $reason): void
    {
        $this->authorize($user,'reject',$record->campus_id);
        DB::transaction(function () use ($user,$record,$reason) {
            $record=StudentSkippingGrade::whereKey($record->id)->lockForUpdate()->firstOrFail();
            $this->requireStatus($record,'pending');
            $record->update(['status'=>'rejected','rejection_reason'=>$reason,'rejected_by'=>$user->id,'rejected_at'=>now(),'updated_by'=>$user->id]);
        });
    }

    private function requireStatus(StudentSkippingGrade $record, string $status): void
    {
        if ($record->status !== $status) $this->invalid('status','This request is '.$record->status.'. Refresh the form before continuing.');
    }

    private function activeSource(StudentEnrollment $enrollment): void
    {
        if (!$enrollment->status || $enrollment->enrollment_status !== 'active' || !$enrollment->student?->status) $this->invalid('enrollment_id','This student is no longer active in the selected enrollment.');
        $year=$enrollment->academicYear;
        if (!$year || $year->isSummer() || !in_array($year->lifecycle_status,['pending','started'],true)) $this->invalid('enrollment_id','Select an active student in an operational regular academic year.');
    }

    private function unchangedSource(StudentSkippingGrade $record, StudentEnrollment $enrollment): void
    {
        foreach (['student_id'=>'student_id','campus_id'=>'campus_id','academic_year_id'=>'academic_year_id','source_grade_id'=>'grade_id','source_class_id'=>'class_id','source_session_id'=>'session_id'] as $from=>$to) {
            if ((string)$record->{$from} !== (string)$enrollment->{$to}) $this->invalid('enrollment_id','The student enrollment changed after this request was created. Create a new request using the current enrollment.');
        }
    }

    private function target(StudentEnrollment $enrollment, array $data): array
    {
        $year=$this->targetYear($enrollment,(int)($data['target_academic_year_id']??$enrollment->academic_year_id));
        $grade=Grade::where('status',1)->find($data['target_grade_id']);
        $class=SchoolClass::where('status',1)->find($data['target_class_id']);
        if (!$grade || (int)$grade->grade_order <= (int)$enrollment->grade->grade_order) $this->invalid('target_grade_id','The requested grade must be higher than the current grade.');
        if (!$class || ($class->grade_id && $class->grade_id != $grade->id) || ($class->academic_year_id && $class->academic_year_id != $year->id)) $this->invalid('target_class_id','Select a class available for the requested grade and requested academic year.');
        $session=$data['target_session_id'] ?? null;
        if ($session && !Session::where('status',1)->whereKey($session)->exists()) $this->invalid('target_session_id','Select an active group.');
        if ($class->session_id && $class->session_id != $session) $this->invalid('target_session_id','The selected group does not match the requested class.');
        return [$grade,$class,$year];
    }

    public function targetAcademicYears(AcademicYear $source): \Illuminate\Support\Collection
    {
        $range=fn($name)=>preg_match('/^(\d{4})\s*[-\/]\s*(\d{4})$/',trim($name),$matches)?[(int)$matches[1],(int)$matches[2]]:null;
        $sourceRange=$range($source->academic_year);
        return AcademicYear::regular()->operational()->orderBy('academic_year')->get()->filter(function ($year) use ($source,$sourceRange,$range) {
            if ($year->id == $source->id) return true;
            $targetRange=$range($year->academic_year);
            return $sourceRange && $sourceRange[1]===$sourceRange[0]+1 && $targetRange===[$sourceRange[0]+1,$sourceRange[1]+1];
        })->values();
    }

    public function targetYear(StudentEnrollment $enrollment, int $id): AcademicYear
    {
        $year=$this->targetAcademicYears($enrollment->academicYear)->firstWhere('id',$id);
        if (!$year) $this->invalid('target_academic_year_id','Select the current or next operational regular academic year.');
        return $year;
    }

    private function noDuplicateTargetEnrollment(StudentEnrollment $enrollment, AcademicYear $year): void
    {
        if ($year->id != $enrollment->academic_year_id && StudentEnrollment::where('student_id',$enrollment->student_id)->where('academic_year_id',$year->id)->lockForUpdate()->exists()) {
            $this->invalid('target_academic_year_id','This student already has an enrollment in the requested academic year.');
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field=>$message]);
    }
}
