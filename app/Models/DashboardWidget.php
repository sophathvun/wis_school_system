<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardWidget extends Model
{
    protected $fillable = [
        'name',
        'code',
        'type',
        'icon',
        'color',
        'description',
        'permission_code',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function templates()
    {
        return $this->belongsToMany(DashboardTemplate::class, 'dashboard_template_widgets')
            ->withPivot(['display_order', 'width', 'status', 'settings'])
            ->withTimestamps();
    }
}
