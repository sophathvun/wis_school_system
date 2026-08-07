<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Role extends Model
{
    protected $table = 'access_roles';

    protected $fillable = ['code', 'name', 'description', 'department_id', 'is_global', 'is_system', 'status'];

    protected function casts(): array
    {
        return ['is_global' => 'boolean', 'is_system' => 'boolean', 'status' => 'integer'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'access_role_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'access_user_roles')->withPivot('campus_id');
    }
}
