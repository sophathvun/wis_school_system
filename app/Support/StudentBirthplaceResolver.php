<?php

namespace App\Support;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

class StudentBirthplaceResolver
{
    private array $locations = [];
    private array $countryMatches = [];
    private array $locationMatches = [];
    private array $nameIndex = [];

    public static function clearTextForSelectionChanges(Student $student, array $input): array
    {
        $changes = [];
        $changed = false;
        foreach (['country', 'province', 'district', 'commune', 'village'] as $level) {
            $key = 'birth_'.$level.'_id';
            if (array_key_exists($key, $input) && (string) ($input[$key] ?? '') !== (string) ($student->getAttribute($key) ?? '')) $changed = true;
            if ($changed && $level !== 'country') {
                foreach (['en', 'kh'] as $language) $changes['birth_'.$level.'_'.$language] = null;
            }
        }
        return $changes;
    }

    private function records(string $level): \Illuminate\Support\Collection
    {
        if(!isset($this->locations[$level])){
            $this->locations[$level]=DB::table('tb_'.$level)->get()->keyBy('id');
            foreach($this->locations[$level] as $record)foreach(['en','kh'] as $language){
                $name=$this->name($record->{$level.'_name_'.$language}??'',$level);
                if($name!=='')$this->nameIndex[$level][$name][$record->id]=$record;
            }
        }
        return $this->locations[$level];
    }

    private function name($value, string $level): string
    {
        $text = mb_strtolower(trim((string) $value));
        $prefixes = [
            'country'=>'',
            'province'=>'ខេត្ត|រាជធានី|province|capital',
            'district'=>'ស្រុក|ខណ្ឌ|ក្រុង|district|sruk|srok|khan',
            'commune'=>'ឃុំ|សង្កាត់|commune|khum|sangkat|sk\.?',
            'village'=>'ភូមិ|village|phum',
        ];
        if ($prefixes[$level] !== '') {
            $text = preg_replace('/^(?:'.$prefixes[$level].')\s*/u', '', $text);
            if ($level === 'province') $text = preg_replace('/\s+(?:province|capital)$/u', '', $text);
        }
        return preg_replace('/\s+/u', '', $text);
    }

    public function changes(Student $student): array
    {
        $changes = [];
        $englishProvince = $student->birth_province_en;
        $countryKey = $this->name($englishProvince, 'country');
        $this->records('country');
        $countries = $this->countryMatches[$countryKey] ??= collect($this->nameIndex['country'][$countryKey]??[]);
        // In the old workbook the English field sometimes names a country,
        // while its Khmer counterpart names the actual province.
        if ($countries->count() === 1) {
            if (blank($student->birth_country_id)) $changes['birth_country_id'] = $countries->first()->id;
            $changes['birth_province_en'] = null;
            $englishProvince = null;
        }
        $parentId = $changes['birth_country_id'] ?? $student->birth_country_id;
        $parentLevel = 'country';
        foreach (['province', 'district', 'commune', 'village'] as $level) {
            $records = $this->records($level);
            $id = $student->getAttribute('birth_'.$level.'_id');
            $record = filled($id) ? $records->get((int)$id) : null;
            if (!$record) {
                $names = ['en'=>$level === 'province' ? $englishProvince : $student->getAttribute('birth_'.$level.'_en'),
                    'kh'=>$student->getAttribute('birth_'.$level.'_kh')];
                if (blank($names['en']) && blank($names['kh'])) break;
                // A lower level must belong to the same resolved parent.
                if ($level !== 'province' && !$parentId) break;
                $matchKey = json_encode([$parentId, $this->name($names['en'], $level), $this->name($names['kh'], $level)]);
                if(!isset($this->locationMatches[$level][$matchKey])){
                    $sets=[];
                    foreach($names as $name){
                        if(blank($name))continue;
                        $set=array_filter($this->nameIndex[$level][$this->name($name,$level)]??[],fn($candidate)=>!$parentId || (int)$candidate->{$parentLevel.'_id'}===(int)$parentId);
                        if($set)$sets[]=$set;
                    }
                    $matches=$sets?array_shift($sets):[];
                    foreach($sets as $set)$matches=array_intersect_key($matches,$set);
                    $this->locationMatches[$level][$matchKey]=collect($matches);
                }
                $matches=$this->locationMatches[$level][$matchKey];
                if ($matches->count() !== 1) break;
                $record = $matches->first();
                $changes['birth_'.$level.'_id'] = $record->id;
            }
            if ($level === 'province' && blank($student->birth_country_id)) $changes['birth_country_id'] = $record->country_id;
            foreach (['en', 'kh'] as $language) {
                $name = $record->{$level.'_name_'.$language};
                if (filled($name) && $student->getAttribute('birth_'.$level.'_'.$language) !== $name) $changes['birth_'.$level.'_'.$language] = $name;
            }
            $parentId = $record->id;
            $parentLevel = $level;
        }
        return $changes;
    }
}
