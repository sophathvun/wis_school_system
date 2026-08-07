<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $table = 'access_permissions';

    protected $fillable = ['code', 'module', 'action', 'name'];

    protected static function booted(): void
    {
        static::created(function (Permission $permission): void {
            $superAdmin = Role::where('code', 'super-admin')->first();
            $superAdmin?->permissions()->syncWithoutDetaching([$permission->id]);
        });
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'access_role_permissions');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'access_department_permissions');
    }
}
