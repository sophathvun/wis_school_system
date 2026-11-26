<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Human-readable transfer values are retained independently of location keys. */
class GepTransferNames
{
    public static function fields(): array
    {
        $fields=['nationality_en','nationality_kh'];
        foreach(['birth','address'] as $prefix)
            foreach(['country','province','district','commune','village'] as $level)
                foreach(['en','kh'] as $language)$fields[]="{$prefix}_{$level}_{$language}";
        return $fields;
    }

    public static function match(string $table,array $names,array $columns,array $parent=[]): ?int
    {
        $normalize=fn($value)=>mb_strtolower(preg_replace('/\s+/u',' ',trim((string)$value)));
        $query=DB::table($table);
        foreach($parent as $key=>$value)$query->where($key,$value);
        $matches=$query->get()->filter(function($record)use($names,$columns,$normalize){
            foreach($names as $language=>$name){
                if(blank($name))continue;
                $possible=$columns[$language]??$columns['any'];
                if(!collect($possible)->contains(fn($column)=>$normalize($record->$column??'')===$normalize($name)))return false;
            }
            return collect($names)->contains(fn($value)=>filled($value));
        });
        return $matches->count()===1?(int)$matches->first()->id:null;
    }

    public static function country(array $names): ?int
    {
        return self::match('tb_country',$names,['any'=>['country_name_en','country_name_kh','nationality_name_en','nationality_name_kh']]);
    }

    public static function import(array $row,array $profile,array &$unresolved): array
    {
        $raw=$profile['transfer_names']??[];
        foreach(self::fields() as $field)if(filled($row[$field]??null))$raw[$field]=$row[$field];
        foreach(['nationality','birth','address'] as $prefix){
            $parent=null;$changed=false;
            foreach($prefix==='nationality'?['nationality']:['country','province','district','commune','village'] as $level){
                $base=$prefix==='nationality'?'nationality':"{$prefix}_{$level}";
                $idField=$prefix==='nationality'?'nationality_country_id':$base.'_id';
                $incoming=filled($row[$base.'_en']??null)||filled($row[$base.'_kh']??null);
                $changed=$changed||$incoming;
                if($changed){
                    $names=['en'=>$raw[$base.'_en']??'','kh'=>$raw[$base.'_kh']??''];
                    $id=($level==='country'||$level==='nationality')?self::country($names):
                        ($parent?self::match('tb_'.$level,$names,['any'=>[$level.'_name_en',$level.'_name_kh']],[$previous.'_id'=>$parent]):null);
                    $profile[$idField]=$id;
                    if(!$id&&collect($names)->contains(fn($value)=>filled($value)))$unresolved[]=$base;
                }
                $parent=$profile[$idField]??null;$previous=$level;
            }
        }
        $profile['transfer_names']=$raw;
        return $profile;
    }

    public static function export(array $profile): array
    {
        $row=array_intersect_key($profile,array_flip(array_filter(GepStudentProfile::FIELDS,fn($field)=>!str_ends_with($field,'_id'))));
        foreach(['nationality','birth','address'] as $prefix){
            foreach($prefix==='nationality'?['nationality']:['country','province','district','commune','village'] as $level){
                $base=$prefix==='nationality'?'nationality':"{$prefix}_{$level}";
                $table=$level==='nationality'?'country':$level;
                $id=$profile[$prefix==='nationality'?'nationality_country_id':$base.'_id']??null;
                $record=$id?DB::table('tb_'.$table)->find($id):null;
                foreach(['en','kh'] as $language)$row[$base.'_'.$language]=$profile['transfer_names'][$base.'_'.$language]??
                    ($prefix==='nationality'?($record->{'nationality_name_'.$language}??$record->{'country_name_'.$language}??''):($record->{$table.'_name_'.$language}??''));
            }
        }
        return $row;
    }

    /** A deliberate form selection replaces the imported spelling for that location. */
    public static function clearChanged(array $before,array $profile): array
    {
        foreach(self::fields() as $field){
            $base=substr($field,0,-3);$id=$base==='nationality'?'nationality_country_id':$base.'_id';
            if((string)($before[$id]??'')!==(string)($profile[$id]??''))unset($profile['transfer_names'][$field]);
        }
        foreach(['birth','address'] as $prefix){
            $changed=false;
            foreach(['country','province','district','commune','village'] as $level){
                $base="{$prefix}_{$level}";
                $changed=$changed||(string)($before[$base.'_id']??'')!==(string)($profile[$base.'_id']??'');
                if($changed)foreach(['en','kh'] as $language)unset($profile['transfer_names'][$base.'_'.$language]);
            }
        }
        return $profile;
    }
}
