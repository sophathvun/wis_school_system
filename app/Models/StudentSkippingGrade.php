<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentSkippingGrade extends Model
{
    protected $table = 'tb_student_skipping_grade';
    protected $guarded = ['id'];
    protected $casts = [
        'student_snapshot'=>'array','criteria'=>'array','committee_names'=>'array','approval_snapshot'=>'array',
        'application_date'=>'date','approval_date'=>'date','received_date'=>'date','review_date'=>'date','effective_date'=>'date',
        'parent_signed_at'=>'datetime','submitted_at'=>'datetime','approved_at'=>'datetime','rejected_at'=>'datetime',
    ];
    public function student() { return $this->belongsTo(Student::class); }
    public function enrollment() { return $this->belongsTo(StudentEnrollment::class); }
    public function campus() { return $this->belongsTo(SchoolInfo::class); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function targetGrade() { return $this->belongsTo(Grade::class,'target_grade_id'); }
    public function targetAcademicYear() { return $this->belongsTo(AcademicYear::class,'target_academic_year_id'); }
    public function targetEnrollment() { return $this->belongsTo(StudentEnrollment::class,'target_enrollment_id'); }
    public function targetClass() { return $this->belongsTo(SchoolClass::class,'target_class_id'); }
    public function creator() { return $this->belongsTo(User::class,'created_by'); }
    public function approver() { return $this->belongsTo(User::class,'approved_by'); }
}
