<?php

namespace App\Support;

use App\Models\Department;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DepartmentHierarchy
{
    public static function options(bool $activeOnly = true, ?int $excludeBranch = null): Collection
    {
        $all = Department::orderBy('name')->orderBy('id')->get();
        self::attachParents($all, $all);
        $byId = $all->keyBy('id');
        $children = $all->groupBy(fn ($department) => $department->parent_id ?? 0);
        $result = collect();
        $seen = [];
        $visit = function (Department $department) use (&$visit, &$seen, $children, $result, $activeOnly, $excludeBranch) {
            if (isset($seen[$department->id])) return;
            $seen[$department->id] = true;
            if ($department->id === $excludeBranch) return;
            if (!$activeOnly || $department->status) $result->push($department);
            foreach ($children->get($department->id, collect()) as $child) $visit($child);
        };
        foreach ($all as $department) {
            if (!$department->parent_id || !$byId->has($department->parent_id)) $visit($department);
        }
        return $result;
    }

    public static function attachParents(Collection $departments, ?Collection $all = null): void
    {
        $all ??= Department::all();
        $byId = $all->keyBy('id');
        foreach ($all as $department) $department->setRelation('parent', $byId->get($department->parent_id));
        foreach ($departments as $department) $department->setRelation('parent', $byId->get($department->parent_id));
    }

    public static function assertValidParent(?int $id, ?int $parentId, Collection $departments): void
    {
        if (!$parentId) return;
        $parents = $departments->keyBy('id');
        $seen = [];
        $current = $parentId;
        while ($current) {
            if ($current === $id || isset($seen[$current])) {
                throw ValidationException::withMessages(['parent_id' => 'A department cannot be its own parent or be placed under one of its subdepartments.']);
            }
            $seen[$current] = true;
            $parent = $parents->get($current);
            if (!$parent) throw ValidationException::withMessages(['parent_id' => 'Select an existing parent department.']);
            $current = $parent->parent_id ? (int) $parent->parent_id : null;
        }
    }
}
