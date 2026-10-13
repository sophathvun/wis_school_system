<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $table = 'access_departments';
    protected $fillable = ['code', 'name', 'status', 'parent_id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class, 'department_id');
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class, 'department_id');
    }

    public function getHierarchyNameAttribute(): string
    {
        $names = [];
        $seen = [];
        $department = $this;
        while ($department && !isset($seen[$department->id])) {
            $seen[$department->id] = true;
            array_unshift($names, $department->name);
            $department = $department->parent_id ? $department->parent : null;
        }
        return implode(' › ', $names);
    }

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
