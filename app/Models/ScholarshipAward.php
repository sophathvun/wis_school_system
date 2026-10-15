<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScholarshipAward extends Model
{
    protected $table = 'tb_scholarship_award';
    protected $guarded = ['id'];
    protected $casts = ['exam_id'=>'integer','grade_number'=>'integer','candidate_id'=>'integer','award_percent'=>'float'];
}
