<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Position extends Model
{
    protected $table = 'access_positions';
    protected $fillable = ['name', 'code', 'department_id', 'status'];

    protected $casts = ['status' => 'boolean'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'position_id');
    }
}
