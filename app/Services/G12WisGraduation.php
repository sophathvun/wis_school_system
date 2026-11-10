<?php

namespace App\Services;

use App\Models\{G12Certificate,StudentGraduation};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** One school-certificate identity per student/year; National Exam certificates are separate. */
class G12WisGraduation
{
    public function certificate(int $student, int $year): ?G12Certificate
    {
        return G12Certificate::where('student_id', $student)->whereIn('certificate_year_id',
            DB::table('tb_g12_certificate_year')->where('academic_year_id', $year)->select('id'))
            ->lockForUpdate()->first();
    }

    public function event(string $action, int $student, int $year, ?G12Certificate $certificate=null, ?StudentGraduation $graduation=null, array $details=[]): void
    {
        DB::table('tb_g12_wis_certificate_event')->insert([
            'student_id'=>$student, 'academic_year_id'=>$year, 'certificate_id'=>$certificate?->id,
            'graduation_id'=>$graduation?->id, 'user_id'=>auth()->id(), 'action'=>$action,
            'details'=>json_encode(['certificate_number'=>$certificate?->certificate_number ?? $graduation?->certificate_number]+$details, JSON_THROW_ON_ERROR),
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
    }

    public function numberForGraduation(int $student, int $year): ?string
    {
        $certificate=$this->certificate($student, $year);
        if ($certificate?->status==='voided') throw ValidationException::withMessages([
            'enrollment_ids'=>'This student has a voided WIS certificate for this academic year. Its number cannot be reused. Review the cancellation before assigning graduation again.',
        ]);
        return $certificate?->certificate_number ?: StudentGraduation::where('student_id',$student)->where('academic_year_id',$year)->value('certificate_number');
    }

    public function assigned(G12Certificate $certificate, int $year): void
    {
        $graduations=StudentGraduation::where('student_id',$certificate->student_id)->where('academic_year_id',$year)->get();
        foreach ($graduations as $graduation) {
            if (blank($graduation->certificate_number)) $graduation->update(['certificate_number'=>$certificate->certificate_number]);
        }
        $this->event('number_assigned',$certificate->student_id,$year,$certificate);
    }

    public function confirmPass(StudentGraduation $graduation): void
    {
        DB::transaction(function () use ($graduation) {
            \App\Models\AcademicYear::whereKey($graduation->academic_year_id)->lockForUpdate()->firstOrFail();
            $graduation=StudentGraduation::whereKey($graduation->id)->lockForUpdate()->firstOrFail();
            if ($graduation->status!=='pending_result') throw ValidationException::withMessages(['status'=>'Only a graduation pending its exam result can be confirmed.']);
            $certificate=$this->certificate($graduation->student_id,$graduation->academic_year_id);
            if ($certificate?->status==='voided') throw ValidationException::withMessages(['status'=>'A voided certificate cannot become valid again.']);
            $enrollment=\App\Models\StudentEnrollment::whereKey($graduation->enrollment_id)->lockForUpdate()->firstOrFail();
            if (!in_array($enrollment->enrollment_status,['active','completed'],true)) throw ValidationException::withMessages(['status'=>'The enrollment changed after graduation was prepared. Review it before confirming the exam result.']);
            $graduation->update(['status'=>'completed']);
            $enrollment->update(['enrollment_status'=>'graduated','ended_on'=>$graduation->graduation_date,'exit_reason'=>'Graduated']);
            \App\Models\StudentEnrollmentHistory::create([
                'enrollment_id'=>$enrollment->id,'student_id'=>$enrollment->student_id,'action_type'=>'graduated','campus_id'=>$enrollment->campus_id,
                'academic_year_id'=>$enrollment->academic_year_id,'grade_id'=>$enrollment->grade_id,'class_id'=>$enrollment->class_id,'session_id'=>$enrollment->session_id,
                'enrollment_status'=>'graduated','student_type'=>$enrollment->student_type,'effective_on'=>$graduation->graduation_date,'reason'=>'WIS exam pass confirmed','changed_by'=>auth()->id(),
            ]);
            if ($certificate) $certificate->update(['status'=>'valid']);
            $this->event('result_passed',$graduation->student_id,$graduation->academic_year_id,$certificate,$graduation);
        });
    }

    public function void(StudentGraduation $graduation, string $reason): void
    {
        $certificate=$this->certificate($graduation->student_id,$graduation->academic_year_id);
        if ($certificate && $certificate->status!=='voided') $certificate->update([
            'status'=>'voided','voided_at'=>now(),'voided_by'=>auth()->id(),'void_reason'=>$reason,
        ]);
        $this->event('graduation_cancelled',$graduation->student_id,$graduation->academic_year_id,$certificate,$graduation,['reason'=>$reason]);
    }

    public function track(G12Certificate $certificate, int $year, string $action): void
    {
        if ($certificate->status==='voided') throw ValidationException::withMessages(['certificate'=>'This certificate is voided and cannot be printed or released.']);
        if ($action==='release') {
            $passed=StudentGraduation::where('student_id',$certificate->student_id)->where('academic_year_id',$year)->where('status','completed')->exists();
            if ($certificate->status!=='valid'||!$passed) throw ValidationException::withMessages(['certificate'=>'Confirm the student passed before releasing this certificate.']);
            if (!$certificate->printed_at) throw ValidationException::withMessages(['certificate'=>'Record certificate printing before releasing it to the student.']);
            if ($certificate->released_at) return;
            $certificate->update(['released_at'=>now()]);
        } else {
            if ($certificate->printed_at) return;
            $certificate->update(['printed_at'=>now(),'status'=>$certificate->status==='pending_result'?'printed_pending':$certificate->status]);
        }
        $this->event($action==='release'?'released':'printed',$certificate->student_id,$year,$certificate);
    }
}
