<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentEnrollmentHistory extends Model
{
    protected $table = 'tb_student_enrollment_history';

    protected $fillable = [
        'enrollment_id', 'source_history_id', 'student_id', 'action_type', 'withdrawal_status', 'paper_signed_at', 'paper_signed_by', 'signed_form_path', 'principal_approved_at', 'principal_approved_by', 'approved_at', 'approved_by', 'rejected_at', 'rejected_by', 'rejection_reason', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'campus_id',
        'academic_year_id', 'grade_id', 'class_id', 'academic_track_id', 'session_id',
        'enrollment_status', 'student_type', 'effective_on', 'reason', 'reasons', 'reason_kh', 'other_reason_en', 'other_reason_kh', 'new_school', 'new_school_address', 'dropout_type', 'requested_by_type', 'requested_by_name', 'requested_by_phone', 'additional_comments', 'notes', 'changed_by',
    ];

    protected $casts = ['effective_on' => 'date', 'reasons' => 'array', 'paper_signed_at' => 'datetime', 'principal_approved_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function enrollment() { return $this->belongsTo(StudentEnrollment::class, 'enrollment_id'); }
    public function sourceHistory() { return $this->belongsTo(self::class, 'source_history_id'); }
    public function student() { return $this->belongsTo(Student::class); }
    public function campus() { return $this->belongsTo(SchoolInfo::class, 'campus_id'); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function grade() { return $this->belongsTo(Grade::class); }
    public function schoolClass() { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function academicTrack() { return $this->belongsTo(AcademicTrack::class, 'academic_track_id'); }
    public function session() { return $this->belongsTo(Session::class); }
    public function changedBy() { return $this->belongsTo(User::class, 'changed_by'); }
}
