<?php

namespace App\Support;

use App\Models\User;

class RolePermissions
{
    public static function allows(?User $user, string $action): bool
    {
        if (!$user) return false;
        if ($user->isSuperAdmin()) return true;
        $has = fn (string $code) => $user->hasPermission($code, $user->active_campus_id);
        if (!$has('roles.view')) return false;

        return $action === 'view' || $has('roles.'.$action)
            || (in_array($action, ['create', 'update'], true) && $has('roles.manage'));
    }
}
