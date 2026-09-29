<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HomeroomRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'assignment_id', 'student_id', 'enrollment_id', 'academic_year_id', 'campus_id', 'grade_id', 'class_id', 'group_id',
        'record_type', 'problem_type', 'recorded_on', 'meeting_with', 'status', 'problem_description', 'action_taken',
        'parent_feedback', 'follow_up_on', 'resolution_note', 'created_by', 'updated_by',
    ];

    protected $casts = ['recorded_on' => 'date:Y-m-d', 'follow_up_on' => 'date:Y-m-d'];

    public function assignment() { return $this->belongsTo(HomeroomAssignment::class, 'assignment_id'); }
    public function student() { return $this->belongsTo(Student::class); }
    public function enrollment() { return $this->belongsTo(StudentEnrollment::class, 'enrollment_id'); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function campus() { return $this->belongsTo(SchoolInfo::class, 'campus_id'); }
    public function grade() { return $this->belongsTo(Grade::class); }
    public function schoolClass() { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function schoolGroup() { return $this->belongsTo(SchoolGroup::class, 'group_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
}
