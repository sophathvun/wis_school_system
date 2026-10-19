<?php

namespace App\Support;

class ScholarshipCandidateCode
{
    public static function yearPrefix(string $academicYear): ?string
    {
        if(!preg_match('/(?<!\d)(\d{4})\D+(\d{4})(?!\d)/u',$academicYear,$years))return null;
        return substr($years[1],-2).substr($years[2],-2);
    }
}
