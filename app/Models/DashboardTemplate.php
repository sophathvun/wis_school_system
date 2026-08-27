<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardTemplate extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'layout',
        'display_order',
        'is_default',
        'status',
        'settings',
        'created_by',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_default' => 'boolean',
        'status' => 'boolean',
        'settings' => 'array',
    ];

    public function widgets()
    {
        return $this->belongsToMany(DashboardWidget::class, 'dashboard_template_widgets')
            ->withPivot(['display_order', 'width', 'status', 'settings'])
            ->withTimestamps()
            ->orderByPivot('display_order');
    }

    public function assignments()
    {
        return $this->hasMany(DashboardAssignment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
