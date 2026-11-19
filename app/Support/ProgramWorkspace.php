<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProgramWorkspace
{
    public const LABELS = ['wis' => 'WIS', 'gep' => 'GEP'];

    public static function catalog(): array
    {
        return [
            ['code'=>'program-workspaces.view','module'=>'program-workspaces','action'=>'view','name'=>'Access Program Workspaces'],
            ['code'=>'program-workspaces.wis.view','module'=>'program-workspaces.wis','action'=>'view','name'=>'Access WIS Workspace'],
            ['code'=>'program-workspaces.gep.view','module'=>'program-workspaces.gep','action'=>'view','name'=>'Access GEP Workspace'],
        ];
    }

    public static function registerMissing(): void
    {
        foreach (self::catalog() as $row) DB::table('access_permissions')->insertOrIgnore([...$row,'created_at'=>now(),'updated_at'=>now()]);
    }

    public static function available(?User $user): array
    {
        if (!$user) return [];
        $cache = self::class.'.'.$user->id;
        if (request()->attributes->has($cache)) return request()->attributes->get($cache);
        if ($user->isSuperAdmin()) return self::LABELS;
        $campuses = [null];
        if ($user->active_campus_id) $campuses[] = (int) $user->active_campus_id;
        $campuses = array_unique([...$campuses,...$user->accessibleCampuses()->pluck('tb_school_info.id')->all()]);
        $allowed = [];
        foreach (self::LABELS as $code=>$label) foreach ($campuses as $campus) {
            if ($user->hasPermission('program-workspaces.view', $campus) && $user->hasPermission('program-workspaces.'.$code.'.view', $campus)) {
                $allowed[$code]=$label;break;
            }
        }
        request()->attributes->set($cache, $allowed);
        return $allowed;
    }

    public static function selected(?User $user): ?string
    {
        $allowed=self::available($user);
        $saved=request()->hasSession() ? request()->session()->get('selected_program', $user?->preferred_program) : $user?->preferred_program;
        return isset($allowed[$saved??'']) ? $saved : array_key_first($allowed);
    }
}
