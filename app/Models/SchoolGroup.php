<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolGroup extends Model
{
    use SoftDeletes;
    protected $table = 'tb_group';
    protected $fillable = ['class_id', 'group_name', 'group_order', 'status'];

    public function schoolClass() { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function enrollments() { return $this->hasMany(StudentEnrollment::class, 'group_id'); }
}
