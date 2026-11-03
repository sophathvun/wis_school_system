<?php

namespace App\Support;

use App\Models\StudentEnrollment;

class AttendanceStudyGroup
{
    public static function code(StudentEnrollment $enrollment): ?string
    {
        $code=strtoupper(trim((string)($enrollment->session?->session_short_name ?: $enrollment->schoolGroup?->group_name)));
        $code=str_replace('Ⅲ','III',$code);
        return in_array($code,['M','A','III'],true)?$code:null;
    }

    public static function allows(object $period, ?string $group): bool
    {
        return $group!==null&&in_array($group,json_decode($period->applicable_groups??'[]',true)?:[],true);
    }

    public static function allowsForEnrollment(object $period, StudentEnrollment $enrollment): bool
    {
        $group=self::code($enrollment);
        if(!self::allows($period,$group))return false;
        if($group!=='III')return true;
        $grade=strtoupper(trim((string)($enrollment->grade?->grade_short_name ?: $enrollment->grade?->grade)));
        $grade=preg_replace('/^GRADE\s*/','',$grade);
        if($grade==='NURSERY')$grade='N';
        return in_array($grade,json_decode($period->full_day_grades??'[]',true)?:[],true);
    }
}
