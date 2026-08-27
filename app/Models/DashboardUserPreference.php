<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardUserPreference extends Model
{
    protected $fillable = [
        'user_id',
        'dashboard_template_id',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function template()
    {
        return $this->belongsTo(DashboardTemplate::class, 'dashboard_template_id');
    }
}
