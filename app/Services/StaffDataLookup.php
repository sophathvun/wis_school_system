<?php

namespace App\Services;

use App\Models\Position;
use App\Models\SchoolInfo;
use App\Support\DepartmentHierarchy;
use App\Support\StaffAddress;
use App\Support\StaffImportValidationException;
use Illuminate\Support\Collection;

class StaffDataLookup
{
    private array $locations;
    private Collection $departments;
    private Collection $positions;
    private Collection $campuses;
    private array $errors=[];
    private array $suggestions=[];

    public function __construct()
    {
        foreach(StaffAddress::LEVELS as $level=>[$model]) $this->locations[$level]=$model::all();
        $this->departments=DepartmentHierarchy::options(false);
        $this->positions=Position::with('department.parent')->get();
        $this->campuses=SchoolInfo::all();
    }

    private function key(string $text): string
    {
        return mb_strtolower(preg_replace('/\s+/u',' ',trim($text)),'UTF-8');
    }

    private function match(Collection $records,array $values,array $fields,string $label): ?object
    {
        $values=array_filter($values,fn($v)=>trim((string)$v)!=='');
        if (!$values) return null;
        $matches=$records->filter(function($record)use($values,$fields) {
            foreach($values as $value) {
                if (!collect($fields)->contains(fn($field)=>$this->key((string)$record->{$field})===$this->key($value))) return false;
            }
            return true;
        });
        if ($matches->count()!==1) {
            $message=($matches->isEmpty()?'Unknown':'Ambiguous').' '.$label.': '.implode(' / ',$values).'. Use a name from the system location/settings lists.';
            $this->errors[$label][]=$message;
            if ($matches->isNotEmpty()) {
                $choices=$matches->take(3)->map(function($record)use($fields,$label) {
                    $text=collect($fields)->map(fn($field)=>(string)$record->{$field})->filter()->unique()->implode(' / ');
                    if ($label==='Position' && $record->department) $text.=' (Department: '.$record->department->hierarchy_name.')';
                    foreach(['country','province','district','commune'] as $parent) {
                        $parentId=$record->getAttribute($parent.'_id');
                        if ($parentId && isset($this->locations[$parent])) {
                            $location=$this->locations[$parent]->firstWhere('id',$parentId);
                            if ($location) $text.=' ('.$location->{$parent.'_name_en'}.')';
                        }
                    }
                    return $text;
                });
                $this->suggestions[$label][$message]='Choose the correct match: '.$choices->implode('; ').'. Specify its parent location/department.';
                return null;
            }
            $candidates=\App\Support\StaffImportSuggestions::rank($records,$values,$fields);
            $names=$candidates->map(function($candidate)use($fields) {
                return collect($fields)->map(fn($field)=>(string)$candidate['record']->{$field})->filter()->unique()->implode(' / ');
            })->unique();
            $this->suggestions[$label][$message]=$names->isNotEmpty()?$names->implode('; '):'No close match found. Select the correct name from the system location/settings list.';
            // Use only a clear candidate to scope further suggestions in this
            // rejected row. Nothing is saved until every lookup matches exactly.
            return $candidates->count()===1 || ($candidates->count()>1 && $candidates[0]['score']-$candidates[1]['score']>=.1)?$candidates->first()['record']:null;
        }
        return $matches->first();
    }

    public function resolve(array $data): array
    {
        $this->errors=[];$this->suggestions=[];
        $country=$this->match($this->locations['country'],[$data['birth_country_en']??'',$data['birth_country_kh']??''],['country_name_en','country_name_kh'],'Country of Birth');
        $province=$this->match($this->locations['province']->when($country,fn($rows)=>$rows->where('country_id',$country->id)),[$data['place_of_birth_en']??'',$data['place_of_birth_kh']??''],['province_name_en','province_name_kh'],'Province/City of Birth');
        if ($province && !$country) $country=$this->locations['country']->firstWhere('id',$province->country_id);
        $data['birth_country_id']=$country?->id;
        $data['birth_province_id']=$province?->id;
        $nationality=$this->match($this->locations['country'],[$data['nationality']??'',$data['nationality_kh']??''],['nationality_name_en','nationality_name_kh','country_name_en','country_name_kh'],'Nationality');
        $data['nationality_country_id']=$nationality?->id;
        if (empty($data['address_country_en']) && empty($data['address_country_kh']) && (!empty($data['province_en']) || !empty($data['province_kh']))) {
            $addressProvince=$this->match($this->locations['province'],[$data['province_en']??'',$data['province_kh']??''],['province_name_en','province_name_kh'],'Address Province/City');
            $addressCountry=$this->locations['country']->firstWhere('id',$addressProvince?->country_id);
            $data['address_country_en']=$addressCountry?->country_name_en;
            $data['address_country_kh']=$addressCountry?->country_name_kh;
        }
        $parentId=null;
        foreach(StaffAddress::LEVELS as $level=>[$model,$parent,$label]) {
            $english=$level==='country'?'address_country_en':$level.'_en';
            $khmer=$level==='country'?'address_country_kh':$level.'_kh';
            $records=$parent?$this->locations[$level]->where($parent,$parentId):$this->locations[$level];
            $record=$this->match($records,[$data[$english]??'',$data[$khmer]??''],[$level.'_name_en',$level.'_name_kh'],'Address '.$label);
            $data['address_'.$level.'_id']=$record?->id;
            $parentId=$record?->id;
        }
        $department=$this->match($this->departments,[$data['department']??''],['hierarchy_name','name'],'Department');
        $positionName=$data['position']??'';
        $positionMatches=$this->positions->filter(fn($position)=>$this->key($position->name)===$this->key($positionName));
        $position=$this->match($department && $positionMatches->count()>1?$positionMatches->where('department_id',$department->id):$this->positions,[$positionName],['name'],'Position');
        $data['department_id']=$department?->id??$position?->department_id;
        $data['position_id']=$position?->id;
        $data['primary_campus_id']=$this->match($this->campuses,[$data['primary_campus']??''],['campus_name_en','campus_name_kh'],'Primary Campus')?->id;
        $data['campuses']=[];
        foreach(array_filter(array_map('trim',explode('|',$data['working_campuses']??''))) as $name) {
            $campus=$this->match($this->campuses,[$name],['campus_name_en','campus_name_kh'],'Working Campus');
            if ($campus) $data['campuses'][]=$campus->id;
        }
        if ($this->errors) throw new StaffImportValidationException($this->errors,$this->suggestions);
        return $data;
    }

    public function sampleLocations(): array
    {
        $country=$this->locations['country']->first(fn($c)=>strtolower($c->country_code??'')==='kh')??$this->locations['country']->first();
        $province=$this->locations['province']->where('country_id',$country?->id)->first();
        $data=['birth_country_en'=>$country?->country_name_en,'birth_country_kh'=>$country?->country_name_kh,'place_of_birth_en'=>$province?->province_name_en,'place_of_birth_kh'=>$province?->province_name_kh,
            'nationality'=>$country?->nationality_name_en?:$country?->country_name_en,'nationality_kh'=>$country?->nationality_name_kh?:$country?->country_name_kh];
        $parentId=null;
        foreach(StaffAddress::LEVELS as $level=>[$model,$parent]) {
            $record=$level==='country'?$country:($parent?$this->locations[$level]->where($parent,$parentId)->first():null);
            $prefix=$level==='country'?'address_country':$level;
            $data[$prefix.'_en']=$record?->{$level.'_name_en'};
            $data[$prefix.'_kh']=$record?->{$level.'_name_kh'};
            $parentId=$record?->id;
        }
        $position=$this->positions->firstWhere('status',1);
        $department=$this->departments->firstWhere('id',$position?->department_id)??$this->departments->firstWhere('status',1);
        $data['position']=$position?->name;
        $data['department']=$department?->hierarchy_name;
        $data['primary_campus']=$this->campuses->firstWhere('status',1)?->campus_name_en;
        $data['working_campuses']=$data['primary_campus'];
        return $data;
    }
}
