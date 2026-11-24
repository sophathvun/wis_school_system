<?php

namespace App\Support;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/** Link names within their parent and recover explicitly labelled address components. */
class StudentAddressResolver
{
    private array $records=[];
    private array $index=[];

    public static function clearTextForSelectionChanges(Student $student,array $input): array
    {
        $changes=[];$changed=false;
        foreach(['country','province','district','commune','village'] as $level){
            $key='address_'.$level.'_id';
            if(array_key_exists($key,$input) && (string)($input[$key]??'')!==(string)($student->getAttribute($key)??''))$changed=true;
            if($changed && $level!=='country')foreach(['en','kh'] as $language)$changes['address_'.$level.'_'.$language]=null;
        }
        return $changes;
    }

    private function name(string $value,string $level): string
    {
        $prefix=match($level){
            'province'=>'province|capital|ខេត្ត|រាជធានី',
            'district'=>'district|khan|srok|sruk|ខណ្ឌ|ស្រុក|ក្រុង',
            'commune'=>'commune|sangkat|khum|សង្កាត់|ឃុំ',
            'village'=>'village|phum|ភូមិ',default=>'',
        };
        $value=mb_strtolower(trim($value));
        if($prefix!=='')$value=preg_replace('/^(?:'.$prefix.')\s*/u','',$value);
        $value=preg_replace('/\s+(?:province|capital)$/u','',$value);
        $value=preg_replace('/^[\s.។]+|[\s.។]+$/u','',$value);
        return preg_replace('/[\s\x{200B}\x{FEFF}]+/u','',$value);
    }

    private function load(string $level): void
    {
        if(isset($this->records[$level]))return;
        $this->records[$level]=DB::table('tb_'.$level)->get()->keyBy('id');
        foreach($this->records[$level] as $record)foreach(['en','kh'] as $language){
            $name=$this->name((string)($record->{$level.'_name_'.$language}??''),$level);
            if($name!=='')$this->index[$level][$name][$record->id]=$record;
        }
    }

    public function changes(Student $student,array $row=[]): array
    {
        $changes=[];
        $values=LegacyStudentProfile::address($row);
        foreach(['en','kh'] as $language)if(filled($student->getAttribute('current_address_'.$language)))$values['current_address_'.$language]=$student->getAttribute('current_address_'.$language);
        foreach(LegacyStudentProfile::addressComponents($values) as $key=>$value)if(blank($student->getAttribute($key)))$changes[$key]=$value;
        $parts=[];
        foreach(['en'=>'home address','kh'=>'kh_current_address'] as $language=>$key){
            $text=$student->getAttribute('current_address_'.$language) ?: ($row['current_address_'.$language]??$row[$key]??'');
            $parts[$language]=array_filter(preg_split('/[,;|\r\n]+/u',(string)$text),fn($v)=>trim($v)!==''&&!preg_match('/^\s*\d+\s*$/u',$v));
        }
        $aliases=['country'=>['en'=>['address_country'],'kh'=>[]],
            'province'=>['en'=>['address_province'],'kh'=>['kh_city']],
            'district'=>['en'=>['address_district'],'kh'=>['kh_khan']],
            'commune'=>['en'=>['address_commune'],'kh'=>['kh_sangkat']],
            'village'=>['en'=>['address_village'],'kh'=>['kh_phoum']]];
        $parentId=null;$parentLevel=null;
        foreach(['country','province','district','commune','village'] as $level){
            $this->load($level);
            $existing=$student->getAttribute('address_'.$level.'_id');
            $record=$existing?$this->records[$level]->get((int)$existing):null;
            if($existing && !$record)break;
            if(!$record){
                if(!in_array($level,['country','province'],true) && !$parentId)break;
                $sets=[];
                foreach(['en','kh'] as $language){
                    $explicit=[];
                    if(filled($changes['address_'.$level.'_'.$language]??null))$explicit[]=$changes['address_'.$level.'_'.$language];
                    if(filled($student->getAttribute('address_'.$level.'_'.$language)))$explicit[]=$student->getAttribute('address_'.$level.'_'.$language);
                    foreach(['address_'.$level.'_'.$language,...$aliases[$level][$language]] as $key)if(filled($row[$key]??null))$explicit[]=$row[$key];
                    $names=$explicit ?: $parts[$language];$matches=[];
                    foreach($names as $name)foreach($this->index[$level][$this->name((string)$name,$level)]??[] as $id=>$candidate){
                        if($parentId && (int)$candidate->{$parentLevel.'_id'}!==(int)$parentId)continue;
                        $matches[$id]=$candidate;
                    }
                    if($matches)$sets[]=$matches;
                }
                $matches=$sets?array_shift($sets):[];
                foreach($sets as $set)$matches=array_intersect_key($matches,$set);
                if(count($matches)!==1){if($level==='country')continue;break;}
                $record=reset($matches);$changes['address_'.$level.'_id']=$record->id;
            }elseif($parentId && (int)$record->{$parentLevel.'_id'}!==(int)$parentId){break;}
            if($level==='province' && !$student->address_country_id && !isset($changes['address_country_id']))$changes['address_country_id']=$record->country_id;
            $parentId=$record->id;$parentLevel=$level;
        }
        return $changes;
    }
}
