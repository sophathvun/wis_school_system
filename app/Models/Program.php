<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends Model
{
    use SoftDeletes;
    protected $table = 'tb_program';
    protected $fillable = ['academic_year_id', 'education_level_id', 'program_name', 'program_code', 'description', 'status'];

    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function educationLevel() { return $this->belongsTo(EducationLevel::class); }
}
