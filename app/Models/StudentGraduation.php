<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentGraduation extends Model
{
    protected $table = 'tb_student_graduation';
    protected $fillable = ['student_id', 'enrollment_id', 'academic_year_id', 'campus_id', 'grade_id', 'class_id', 'session_id', 'graduation_date', 'certificate_number', 'is_alumni', 'status', 'cancellation_type', 'cancellation_reason', 'cancelled_at', 'cancelled_by', 'notes', 'changed_by'];
    protected $casts = ['graduation_date' => 'date', 'is_alumni' => 'boolean', 'cancelled_at' => 'datetime'];
    public function student() { return $this->belongsTo(Student::class); }
    public function enrollment() { return $this->belongsTo(StudentEnrollment::class, 'enrollment_id'); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function campus() { return $this->belongsTo(SchoolInfo::class, 'campus_id'); }
    public function grade() { return $this->belongsTo(Grade::class); }
    public function schoolClass() { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function session() { return $this->belongsTo(Session::class); }
    public function changedBy() { return $this->belongsTo(User::class, 'changed_by'); }
    public function cancelledBy() { return $this->belongsTo(User::class, 'cancelled_by'); }
}
