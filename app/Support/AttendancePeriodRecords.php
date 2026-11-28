<?php

namespace App\Support;

class AttendancePeriodRecords
{
    public static function periods(object|array $mark): array
    {
        $raw=is_array($mark)?($mark['absent_periods']??[]):($mark->absent_periods??[]);
        return is_string($raw)?(json_decode($raw,true)?:[]):$raw;
    }

    public static function mixed(object|array $mark): bool
    {
        return collect(self::periods($mark))->contains(fn($period)=>isset($period['attendance_status']));
    }

    /** Tardy is counted once per period; absence statuses retain their daily report grouping. */
    public static function expand(object $mark): array
    {
        $saved=self::periods($mark);
        if(!self::mixed($mark) && !($mark->status==='T' && count($saved)>1))return [$mark];
        return collect($saved)->groupBy(fn($period)=>$period['attendance_status']??$mark->status)->flatMap(function($periods,$status)use($mark){
            if($status==='T')return $periods->map(function($period)use($mark,$periods){
                $entry=clone $mark;$entry->status='T';$entry->missed_sessions=1;
                $entry->remarks=$period['attendance_remarks']??$mark->remarks;
                if($periods->count()>1)$entry->remarks=trim(($entry->remarks??'').' | Period: '.$period['name'].' '.$period['starts_at'].'–'.$period['ends_at'],' |');
                $entry->absent_periods=json_encode([$period]);return $entry;
            })->values()->all();
            $entry=clone $mark;$entry->status=$status;$entry->missed_sessions=$periods->count();
            $entry->remarks=$periods->map(fn($period)=>$period['attendance_remarks']??$mark->remarks)->filter()->unique()->implode(' | ');
            $entry->absent_periods=json_encode($periods->values()->all());return [$entry];
        })->values()->all();
    }

    /** Non-overlapping complete periods may coexist; overlapping differences stay in review. */
    public static function merge(array $existing,array $incoming): array
    {
        if(empty($existing['absent_periods']) || empty($incoming['absent_periods'])
            || $existing['missed_sessions']!==count($existing['absent_periods']) || $incoming['missed_sessions']!==count($incoming['absent_periods'])
            || (int)$existing['enrollment_id']!==(int)$incoming['enrollment_id']
            || !in_array($existing['status'],['EX','UX','T'],true) || !in_array($incoming['status'],['EX','UX','T'],true))
            throw new \InvalidArgumentException('Multiple daily records need complete, non-overlapping configured periods with matching session counts.');
        $decorate=fn($record)=>array_map(fn($p)=>[...$p,'attendance_status'=>$p['attendance_status']??$record['status'],'attendance_remarks'=>$p['attendance_remarks']??$record['remarks']],$record['absent_periods']);
        $periods=$decorate($existing);$added=false;
        foreach($decorate($incoming) as $next){
            $duplicate=false;
            foreach($periods as $saved){
                if($next['starts_at']<$saved['ends_at'] && $next['ends_at']>$saved['starts_at']){
                    if($next['starts_at']===$saved['starts_at'] && $next['ends_at']===$saved['ends_at'] && (int)$next['id']===(int)$saved['id']
                        && $next['attendance_status']===$saved['attendance_status'] && trim((string)$next['attendance_remarks'])===trim((string)$saved['attendance_remarks']))$duplicate=true;
                    else throw new \InvalidArgumentException('Different attendance records overlap the same period. Review the status, time and reason.');
                }
            }
            if(!$duplicate){$periods[]=$next;$added=true;}
        }
        if(count($periods)>99)throw new \InvalidArgumentException('Combined daily records exceed 99 sessions.');
        usort($periods,fn($a,$b)=>strcmp($a['starts_at'],$b['starts_at']));
        $statuses=array_unique(array_column($periods,'attendance_status'));
        $status=in_array('UX',$statuses,true)?'UX':(in_array('EX',$statuses,true)?'EX':'T');
        return ['duplicate'=>!$added,'status'=>$status,'missed_sessions'=>count($periods),'absent_periods'=>$periods,'remarks'=>'Multiple period records. See period details.'];
    }
}
