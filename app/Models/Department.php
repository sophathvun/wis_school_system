<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $table = 'access_departments';
    protected $fillable = ['code', 'name', 'status'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'access_department_permissions');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'department_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_id');
    }
}
