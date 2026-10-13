<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffWorkHistory extends Model
{
    protected $table = 'hrm_staff_work_histories';
    protected $guarded = ['id'];
    protected $casts = ['before_snapshot'=>'array','after_snapshot'=>'array','effective_on'=>'date'];
    public function staff() { return $this->belongsTo(Staff::class); }
}
