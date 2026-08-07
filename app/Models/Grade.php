<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grade extends Model
{
    use SoftDeletes;
    protected $table = 'tb_grade';

    protected $fillable = [
        'education_level_id',
        'grade',
        'grade_short_name',
        'grade_order',
        'description',
        'status',
    ];

    public function educationLevel() { return $this->belongsTo(EducationLevel::class); }
    public function classes() { return $this->hasMany(SchoolClass::class); }
}
