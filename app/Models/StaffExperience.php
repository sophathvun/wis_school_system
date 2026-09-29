<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffExperience extends Model
{
    protected $table = 'hrm_staff_experiences';

    protected $fillable = [
        'staff_id', 'company_name', 'position', 'employment_type', 'started_on', 'ended_on', 'is_current', 'responsibilities',
    ];

    protected $casts = [
        'started_on' => 'date:Y-m-d',
        'ended_on' => 'date:Y-m-d',
        'is_current' => 'boolean',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
}
