<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnrollmentWorkflowAction extends Model
{
    protected $table = 'tb_student_enrollment_workflow';

    protected $fillable = [
        'student_id', 'source_enrollment_id', 'target_enrollment_id', 'action_type',
        'from_campus_id', 'to_campus_id', 'from_academic_year_id', 'to_academic_year_id',
        'from_grade_id', 'to_grade_id', 'from_class_id', 'to_class_id',
        'from_session_id', 'to_session_id', 'effective_on', 'reason', 'notes', 'changed_by',
    ];

    protected $casts = ['effective_on' => 'date'];

    public function student() { return $this->belongsTo(Student::class); }
    public function toCampus() { return $this->belongsTo(SchoolInfo::class, 'to_campus_id'); }
    public function toAcademicYear() { return $this->belongsTo(AcademicYear::class, 'to_academic_year_id'); }
    public function toGrade() { return $this->belongsTo(Grade::class, 'to_grade_id'); }
    public function toClass() { return $this->belongsTo(SchoolClass::class, 'to_class_id'); }
    public function toSession() { return $this->belongsTo(Session::class, 'to_session_id'); }
}
