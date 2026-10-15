<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScholarshipStudentRank extends Model
{
    protected $table = 'tb_scholarship_student_rank';
    protected $fillable = ['student_enrollment_id', 'rank', 'eoy_average', 'assigned_by'];
    protected $casts = ['rank' => 'integer','eoy_average'=>'float'];

    public function enrollment() { return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id'); }
    public function assignedBy() { return $this->belongsTo(User::class, 'assigned_by'); }
}
