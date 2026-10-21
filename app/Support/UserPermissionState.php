<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/** Configured grants shown to administrators; record and campus restrictions still apply at runtime. */
class UserPermissionState
{
    public static function forUser(User $user, Collection $catalog): array
    {
        $user->loadMissing(['roles.permissions', 'department.permissions', 'permissionOverrides']);
        $sources = [];
        $roles = $user->roles->where('status', 1);
        $superAdmin = $roles->contains('code', 'super-admin');
        foreach ($roles as $role) {
            foreach ($superAdmin && $role->code === 'super-admin' ? $catalog : $role->permissions as $permission) {
                $sources[$permission->id][] = 'Role: '.($role->name ?: $role->code);
            }
        }
        foreach ($user->department?->permissions ?? [] as $permission) {
            $sources[$permission->id][] = 'Department: '.$user->department->name;
        }
        $sources = array_map(fn ($labels) => array_values(array_unique($labels)), $sources);
        $direct = $user->permissionOverrides->filter(fn ($permission) => (bool) $permission->pivot?->allowed)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $inherited = array_map('intval', array_keys($sources));
        $ids = array_unique([...$direct, ...$inherited]);
        $visible = PermissionHierarchy::visibleIds($catalog);
        return [
            'permissions' => $catalog->whereIn('id', $ids)->values(), 'sources' => $sources,
            'direct_ids' => $direct, 'inherited_ids' => $inherited,
            'full_access' => $visible->isNotEmpty() && $visible->diff($ids)->isEmpty(),
            'inherited_full_access' => $visible->isNotEmpty() && $visible->diff($inherited)->isEmpty(),
        ];
    }

    public static function directIdsForSave(User $user, array $requested, Collection $catalog): array
    {
        $state = self::forUser($user, $catalog);
        // Inherited parents can authorize additional user actions. Saving must not
        // copy inherited grants to user overrides and detach them from future role changes.
        $normalized = PermissionHierarchy::normalizeIds([...$requested, ...$state['inherited_ids']], $catalog);
        return collect($requested)->map(fn ($id) => (int) $id)->unique()
            ->filter(fn ($id) => in_array($id, $normalized, true)
                && (!in_array($id, $state['inherited_ids'], true) || in_array($id, $state['direct_ids'], true)))
            ->values()->all();
    }
}
