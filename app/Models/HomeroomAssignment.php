<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HomeroomAssignment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'staff_id', 'teacher_id', 'academic_year_id', 'campus_id', 'grade_id', 'class_id',
        'is_primary', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = ['is_primary' => 'boolean', 'status' => 'boolean'];

    public function staff() { return $this->belongsTo(Staff::class, 'staff_id'); }
    public function teacher() { return $this->belongsTo(User::class, 'teacher_id'); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function campus() { return $this->belongsTo(SchoolInfo::class, 'campus_id'); }
    public function grade() { return $this->belongsTo(Grade::class); }
    public function schoolClass() { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function records() { return $this->hasMany(HomeroomRecord::class, 'assignment_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
}
