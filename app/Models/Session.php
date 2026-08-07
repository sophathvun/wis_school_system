<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Session extends Model
{
    use SoftDeletes;
    protected $table = 'tb_session';

    protected $fillable = [
        'session_name',
        'session_short_name',
        'session_order',
        'description',
        'status',
    ];

    public function classes() { return $this->hasMany(SchoolClass::class); }
    public function enrollments() { return $this->hasMany(StudentEnrollment::class); }
}
