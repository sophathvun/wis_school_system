<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

class GepPromotionPermissions
{
    public static function allows(?User $user,string $action='view',?int $campus=null): bool
    {
        if(!$user || !in_array($action,['view','promote'],true))return false;
        $campuses=$campus===null?$user->accessibleCampuses()->pluck('tb_school_info.id')->all():[$campus];
        foreach($campuses as $id){
            // All grants must apply to the same campus; a menu grant elsewhere is insufficient.
            if(GepEnrollmentPermissions::allows($user,'view',(int)$id)
                && $user->hasPermission('gep-students.promotion.view',(int)$id)
                && ($action==='view' || $user->hasPermission('gep-students.promotion.promote',(int)$id)))return true;
        }
        return false;
    }

    public static function campuses(?User $user,string $action='view'): Collection
    {
        return GepEnrollmentPermissions::campuses($user)->filter(fn($campus)=>self::allows($user,$action,(int)$campus->id))->values();
    }
}
