<?php

namespace App\Support;

use App\Models\Student;
use App\Models\FamilyMember;

class LegacyStudentProfile
{
    public static function birthplace(array $row): array
    {
        $columns = ['village'=>'phoum_pob', 'commune'=>'sangkat_pob', 'district'=>'khan_pob', 'province'=>'place of birth'];
        $result = [];
        foreach ($columns as $level=>$source) {
            foreach (['en', 'kh'] as $language) {
                $key = $language === 'kh' ? ($level === 'province' ? 'kh_place_of_birth' : 'kh_'.$source) : $source;
                $text = trim((string) ($row[$key] ?? ''));
                if ($text !== '') $result["birth_{$level}_{$language}"] = mb_substr($text, 0, 255);
            }
        }
        return $result;
    }

    public static function missingBirthplace(Student $student, array $row): array
    {
        return array_filter(self::birthplace($row), function ($value, $key) use ($student) {
            $level = explode('_', $key)[1];
            return blank($student->getAttribute($key)) && blank($student->getAttribute("birth_{$level}_id"));
        }, ARRAY_FILTER_USE_BOTH);
    }

    public static function occupation(array $row, string $relationship): array
    {
        $result = [];
        foreach (['en', 'kh'] as $language) {
            $text = trim((string) ($row["{$relationship}_occupation_{$language}"] ?? ''));
            if ($text !== '') $result['occupation_'.$language] = mb_substr($text, 0, 120);
        }
        return $result;
    }

    public static function missingOccupation(FamilyMember $member, array $row): array
    {
        if (filled($member->occupation_id)) return [];
        $sourceName = preg_replace('/\s+/u', ' ', trim((string) ($row[$member->relationship_type.'_name_en'] ?? '')));
        $storedName = preg_replace('/\s+/u', ' ', trim((string) $member->full_name_en));
        // A source row must identify the same family contact before repairing it.
        if ($sourceName === '' || mb_strtolower($sourceName) !== mb_strtolower($storedName)) return [];
        return array_filter(self::occupation($row, $member->relationship_type), fn ($value, $key) =>
            blank($member->getAttribute($key)) && blank($member->getRawOriginal('occupation')), ARRAY_FILTER_USE_BOTH);
    }
}
