<?php

namespace App\Support;

class FamilyMemberOccupation
{
    public static function khmer(?object $member): string
    {
        // Match Student Family Information: resolve the selected occupation first.
        $lookup = trim((string) data_get($member, 'occupationRecord.occupation_name_kh'));
        return $lookup !== '' ? $lookup : trim((string) data_get($member, 'occupation_kh'));
    }
}
