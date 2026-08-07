<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicYear extends Model
{
    use SoftDeletes;
    protected $table = 'tb_academic_year';

    protected $fillable = [
        'academic_year',
        'ay_code',
        'period_type',
        'parent_academic_year_id',
        'start_date',
        'end_date',
        'description',
        'status',
    ];

    protected $appends = ['ay_code'];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    public function getAyCodeAttribute()
    {
        return $this->attributes['academic_year_code'] ?? null;
    }

    public function setAyCodeAttribute($value)
    {
        $this->attributes['academic_year_code'] = $value;
    }

    public function programs() { return $this->hasMany(Program::class); }
    public function classes() { return $this->hasMany(SchoolClass::class); }
    public function enrollments() { return $this->hasMany(StudentEnrollment::class); }
    public function parentAcademicYear() { return $this->belongsTo(self::class, 'parent_academic_year_id'); }
    public function summerPeriods() { return $this->hasMany(self::class, 'parent_academic_year_id'); }

    public function isSummer(): bool
    {
        return $this->period_type === 'summer';
    }
}
