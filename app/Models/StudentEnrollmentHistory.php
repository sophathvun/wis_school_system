<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentEnrollmentHistory extends Model
{
    protected $table = 'tb_student_enrollment_history';

    protected $fillable = [
        'enrollment_id', 'source_history_id', 'student_id', 'action_type', 'campus_id',
        'academic_year_id', 'grade_id', 'class_id', 'session_id',
        'enrollment_status', 'student_type', 'effective_on', 'reason', 'reasons', 'reason_kh', 'other_reason_en', 'other_reason_kh', 'new_school', 'new_school_address', 'dropout_type', 'additional_comments', 'notes', 'changed_by',
    ];

    protected $casts = ['effective_on' => 'date', 'reasons' => 'array'];

    public function enrollment() { return $this->belongsTo(StudentEnrollment::class, 'enrollment_id'); }
    public function sourceHistory() { return $this->belongsTo(self::class, 'source_history_id'); }
    public function student() { return $this->belongsTo(Student::class); }
    public function campus() { return $this->belongsTo(SchoolInfo::class, 'campus_id'); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function grade() { return $this->belongsTo(Grade::class); }
    public function schoolClass() { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function session() { return $this->belongsTo(Session::class); }
    public function changedBy() { return $this->belongsTo(User::class, 'changed_by'); }
}
