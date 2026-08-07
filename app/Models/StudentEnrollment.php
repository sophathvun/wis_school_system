<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentEnrollment extends Model
{
    protected $table = 'tb_student_enrollment';
    protected $fillable = ['student_id', 'campus_id', 'academic_year_id', 'grade_id', 'class_id', 'group_id', 'session_id', 'status', 'student_type', 'enrollment_status', 'enrolled_on', 'ended_on', 'exit_reason', 'notes'];

    protected $casts = ['status' => 'boolean', 'enrolled_on' => 'date', 'ended_on' => 'date'];

    public function student() { return $this->belongsTo(Student::class); }
    public function campus() { return $this->belongsTo(SchoolInfo::class, 'campus_id'); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function grade() { return $this->belongsTo(Grade::class); }
    public function schoolClass() { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function schoolGroup() { return $this->belongsTo(SchoolGroup::class, 'group_id'); }
    public function session() { return $this->belongsTo(Session::class); }
    public function history() { return $this->hasMany(StudentEnrollmentHistory::class, 'enrollment_id')->latest('effective_on'); }
}
