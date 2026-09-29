<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffEducation extends Model
{
    protected $table = 'hrm_staff_educations';

    protected $fillable = [
        'staff_id', 'institution_name', 'degree', 'field_of_study', 'started_on', 'ended_on', 'grade_or_result', 'notes',
    ];

    protected $casts = [
        'started_on' => 'date:Y-m-d',
        'ended_on' => 'date:Y-m-d',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
}
