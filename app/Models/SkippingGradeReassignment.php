<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkippingGradeReassignment extends Model
{
    protected $table = 'tb_skipping_grade_reassignments';
    protected $guarded = ['id'];
    public const UPDATED_AT = null;
    protected $casts = ['previous_reviewer'=>'array','replacement_reviewer'=>'array'];
}
