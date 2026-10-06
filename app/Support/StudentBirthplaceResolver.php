<?php

namespace App\Support;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

class StudentBirthplaceResolver
{
    private array $locations = [];
    private array $countryMatches = [];
    private array $locationMatches = [];

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
        return $this->locations[$level] ??= DB::table('tb_'.$level)->get();
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
        $countries = $this->countryMatches[$countryKey] ??= $this->records('country')->filter(fn ($record) =>
            filled($englishProvince) && (
                $this->name($record->country_name_en, 'country') === $this->name($englishProvince, 'country') ||
                $this->name($record->country_name_kh, 'country') === $this->name($englishProvince, 'country')
            ));
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
            $record = filled($id) ? $records->firstWhere('id', (int) $id) : null;
            if (!$record) {
                $names = ['en'=>$level === 'province' ? $englishProvince : $student->getAttribute('birth_'.$level.'_en'),
                    'kh'=>$student->getAttribute('birth_'.$level.'_kh')];
                if (blank($names['en']) && blank($names['kh'])) break;
                // A lower level must belong to the same resolved parent.
                if ($level !== 'province' && !$parentId) break;
                $matchKey = json_encode([$parentId, $this->name($names['en'], $level), $this->name($names['kh'], $level)]);
                $matches = $this->locationMatches[$level][$matchKey] ??= $records->filter(function ($candidate) use ($names, $level, $parentId, $parentLevel) {
                    if ($parentId && (int) $candidate->{$parentLevel.'_id'} !== (int) $parentId) return false;
                    $matched = false;
                    foreach ($names as $language=>$name) {
                        if (blank($name)) continue;
                        if ($this->name($candidate->{$level.'_name_'.$language}, $level) !== $this->name($name, $level)) return false;
                        $matched = true;
                    }
                    return $matched;
                });
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
