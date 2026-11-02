<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlacementTestApplicant extends Model
{
    protected $table = 'tb_placement_test_applicant';

    protected $guarded = ['id'];

    protected $casts = ['date_of_birth' => 'date:Y-m-d', 'metadata' => 'array'];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function campus()
    {
        return $this->belongsTo(SchoolInfo::class, 'campus_id');
    }

    public function targetGrade()
    {
        return $this->belongsTo(Grade::class, 'target_grade_id');
    }

    public function recommendedGrade()
    {
        return $this->belongsTo(Grade::class, 'recommended_grade_id');
    }

    public function enrolledStudent()
    {
        return $this->belongsTo(Student::class, 'enrolled_student_id');
    }

    public function attempts()
    {
        return $this->hasMany(PlacementTestAttempt::class, 'applicant_id');
    }
}
