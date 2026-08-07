<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EducationLevel extends Model
{
    use SoftDeletes;
    protected $table = 'tb_education_level';
    protected $fillable = ['level_name', 'level_short_name', 'level_order', 'description', 'status'];

    public function grades() { return $this->hasMany(Grade::class); }
    public function programs() { return $this->hasMany(Program::class); }
}
