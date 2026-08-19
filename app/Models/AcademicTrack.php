<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicTrack extends Model
{
    use SoftDeletes;

    protected $table = 'tb_academic_track';

    protected $fillable = ['grade_id', 'name_en', 'name_kh', 'code', 'stream_type', 'language', 'status'];

    protected $casts = ['status' => 'boolean'];

    public function grade() { return $this->belongsTo(Grade::class); }
    public function studentEnrollments() { return $this->hasMany(StudentEnrollment::class, 'academic_track_id'); }

    public function getDisplayNameAttribute(): string
    {
        return trim(($this->name_kh ? "{$this->name_kh} / " : '').$this->name_en);
    }
}
