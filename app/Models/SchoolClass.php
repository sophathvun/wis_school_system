<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolClass extends Model
{
    use SoftDeletes;
    protected $table = 'tb_class';

    protected $fillable = ['academic_year_id', 'grade_id', 'session_id', 'class_name', 'class_order', 'status'];

    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function grade() { return $this->belongsTo(Grade::class); }
    public function session() { return $this->belongsTo(Session::class); }
    public function groups() { return $this->hasMany(SchoolGroup::class, 'class_id'); }
}
