<?php

namespace App\Support;

use Illuminate\Support\Collection;

class StaffImportSuggestions
{
    public static function rank(Collection $records,array $values,array $fields): Collection
    {
        return $records->map(function($record)use($values,$fields) {
            $score=0;
            foreach($values as $value) foreach($fields as $field) {
                $source=self::key((string)$value);$target=self::key((string)data_get($record,$field));
                if ($source==='' || $target==='') continue;
                $length=max(mb_strlen($source),mb_strlen($target));
                if (abs(mb_strlen($source)-mb_strlen($target))>4) continue;
                $distance=self::distance($source,$target);
                if ($distance<=max(1,min(4,(int)floor($length*.3)))) {
                    $similarity=1-$distance/$length;
                    if ($length===3 && $distance===1) $similarity=.7;
                    $score=max($score,$similarity);
                }
            }
            return ['record'=>$record,'score'=>$score];
        })->filter(fn($candidate)=>$candidate['score']>=.7)->sortByDesc('score')->take(3)->values();
    }

    public static function choices(string $value,array $labels): string
    {
        $records=collect($labels)->map(fn($label,$key)=>['code'=>(string)$key,'label'=>$label]);
        $close=self::rank($records,[$value],['code','label']);
        return $close->isNotEmpty()?$close->map(fn($item)=>$item['record']['label'])->unique()->implode('; '):'Choose one of: '.implode(', ',$labels).'.';
    }

    private static function key(string $text): string { return mb_strtolower(preg_replace('/\s+/u',' ',trim($text)),'UTF-8'); }

    private static function distance(string $source,string $target): int
    {
        $left=preg_split('//u',$source,-1,PREG_SPLIT_NO_EMPTY);$right=preg_split('//u',$target,-1,PREG_SPLIT_NO_EMPTY);
        if (count($left)===count($right)) {
            $differences=[];
            foreach($left as $index=>$character) if ($character!==$right[$index]) $differences[]=$index;
            if (count($differences)===2 && $differences[1]===$differences[0]+1 && $left[$differences[0]]===$right[$differences[1]] && $left[$differences[1]]===$right[$differences[0]]) return 1;
        }
        if (preg_match('/^[\x00-\x7F]*$/',$source.$target)) return levenshtein($source,$target);
        $previous=range(0,count($right));
        foreach($left as $i=>$character) {
            $current=[$i+1];
            foreach($right as $j=>$other) $current[$j+1]=min($current[$j]+1,$previous[$j+1]+1,$previous[$j]+($character===$other?0:1));
            $previous=$current;
        }
        return $previous[count($right)];
    }
}
