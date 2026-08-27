<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardAssignment extends Model
{
    protected $fillable = [
        'dashboard_template_id',
        'assignment_type',
        'assignment_id',
        'priority',
        'status',
    ];

    protected $casts = [
        'priority' => 'integer',
        'status' => 'boolean',
    ];

    public function template()
    {
        return $this->belongsTo(DashboardTemplate::class, 'dashboard_template_id');
    }
}
