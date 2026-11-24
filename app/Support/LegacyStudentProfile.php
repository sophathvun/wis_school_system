<?php

namespace App\Support;

use App\Models\Student;
use App\Models\FamilyMember;

class LegacyStudentProfile
{
    public static function birthplace(array $row): array
    {
        $columns = ['village'=>['phoum_pob'], 'commune'=>['sangkat_pob'], 'district'=>['khan_pob'], 'province'=>['province of birth','place of birth','province_of_birth']];
        $result = [];
        foreach ($columns as $level=>$sources) {
            foreach (['en', 'kh'] as $language) {
                $keys=$language==='kh'?($level==='province'?['kh_place_of_birth','kh_province_of_birth']:['kh_'.$sources[0]]):$sources;
                array_unshift($keys,'birth_'.$level.'_'.$language);
                $text='';foreach($keys as $key){$text=trim((string)($row[$key]??''));if($text!=='')break;}
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

    public static function locationSource(array $row): array
    {
        return array_filter($row,fn($key)=>in_array($key,['place of birth','province of birth','province_of_birth','home address','kh_current_address','kh_city','kh_city1','kh_khan','kh_sangkat','kh_phoum','khan_pob','sangkat_pob','phoum_pob','kh_khan_pob','kh_sangkat_pob','kh_phoum_pob','kh_place_of_birth','kh_province_of_birth'],true)||preg_match('/^(?:birth|address)_(?:country|province|district|commune|village|house_no|street)_(?:en|kh|id)$/',$key)||in_array($key,['current_address_en','current_address_kh'],true),ARRAY_FILTER_USE_KEY);
    }

    public static function address(array $row): array
    {
        $aliases=['province'=>['en'=>['address_province_en'],'kh'=>['address_province_kh','kh_city']],
            'district'=>['en'=>['address_district_en'],'kh'=>['address_district_kh','kh_khan']],
            'commune'=>['en'=>['address_commune_en'],'kh'=>['address_commune_kh','kh_sangkat']],
            'village'=>['en'=>['address_village_en'],'kh'=>['address_village_kh','kh_phoum']],
            'house_no'=>['en'=>['address_house_no_en'],'kh'=>['address_house_no_kh']],
            'street'=>['en'=>['address_street_en'],'kh'=>['address_street_kh']]];
        $values=[];
        foreach($aliases as $level=>$languages)foreach($languages as $language=>$keys)foreach($keys as $key)if(filled($row[$key]??null)){$values['address_'.$level.'_'.$language]=trim((string)$row[$key]);break;}
        foreach(['en'=>['current_address_en','home address'],'kh'=>['current_address_kh','kh_current_address']] as $language=>$keys)foreach($keys as $key)if(filled($row[$key]??null)){$values['current_address_'.$language]=trim((string)$row[$key]);break;}
        foreach (self::addressComponents($values) as $key=>$value) $values[$key] ??= $value;
        return $values;
    }

    /** Extract only labelled components; retain the complete address unchanged. */
    public static function addressComponents(array $values): array
    {
        $result=[];
        foreach (['en','kh'] as $language) {
            $text=trim((string)($values['current_address_'.$language]??''));
            $patterns=$language==='en' ? [
                'house_no'=>'/(?:^|[,;]\s*|\b)(?:house\s*(?:no\.?|number)?\s*[:#]?\s*|#)([\p{L}\p{M}\p{N}]+(?:[\/.-][\p{L}\p{M}\p{N}]+)*)/iu',
                'street'=>'/\b(?:street|st\.)\s*(?:no\.?|number)?\s*[:#]?\s*([\p{L}\p{M}\p{N}]+(?:[\/.-][\p{L}\p{M}\p{N}]+)*)/iu',
                'village'=>'/(?:^|[,;]\s*)(?:village\s+([^,;]+)|([^,;]+?)\s+village)\s*(?=[,;]|$)/iu',
            ] : [
                'house_no'=>'/ផ្ទះ\s*(?:លេខ)?\s*[:#]?\s*([\p{L}\p{M}\p{N}]+(?:[\/.-][\p{L}\p{M}\p{N}]+)*)/u',
                'street'=>'/ផ្លូវ\s*(?:លេខ)?\s*[:#]?\s*([\p{L}\p{M}\p{N}]+(?:[\/.-][\p{L}\p{M}\p{N}]+)*)/u',
                'village'=>'/ភូមិ\s*(.+?)(?=\s*(?:[,;]|សង្កាត់|ឃុំ|ខណ្ឌ|ស្រុក|រាជធានី|ខេត្ត)|$)/u',
            ];
            foreach($patterns as $level=>$pattern) {
                if (!preg_match_all($pattern,$text,$matches,PREG_SET_ORDER)) continue;
                $candidates=[];
                foreach($matches as $match) {
                    $value=trim($match[1] ?: ($match[2]??''));
                    if(in_array($level,['house_no','street'],true) && !preg_match('/\p{N}/u',$value) && !($level==='street' && $value==='លំ'))continue;
                    if($value!=='' && mb_strlen($value)<=120)$candidates[$value]=true;
                }
                if(count($candidates)===1)$result['address_'.$level.'_'.$language]=(string)array_key_first($candidates);
            }
            foreach(['house_no','street'] as $level)if(filled($values['address_'.$level.'_'.$language]??null))$result['address_'.$level.'_'.$language]=trim($values['address_'.$level.'_'.$language]);
        }
        return $result;
    }

    public static function missingAddress(Student $student,array $row): array
    {
        return array_filter(self::address($row),fn($value,$key)=>blank($student->getAttribute($key)),ARRAY_FILTER_USE_BOTH);
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
