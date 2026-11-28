<?php

namespace App\Services;

use App\Models\StudentEnrollment;
use App\Support\AttendanceStudyGroup;
use Illuminate\Support\Facades\DB;

class AttendanceImportPeriods
{
    /** Match complete configured periods only; never guess a partial or overlapping session. */
    public function resolve(object $enrollment,string $time,array &$cache): array
    {
        [$start,$end]=$this->range($time);
        $key='import-periods:'.$enrollment->id;
        if(!array_key_exists($key,$cache)){
            $student=StudentEnrollment::with(['session','schoolGroup','grade'])->find($enrollment->id);
            $cache[$key]=DB::table('schedule_periods')->where('active',true)->whereNull('deleted_at')->orderBy('starts_at')->orderBy('id')->get()
                ->filter(fn($period)=>$student && AttendanceStudyGroup::allowsForEnrollment($period,$student))->values();
        }
        $matches=$cache[$key]->filter(fn($period)=>substr($period->starts_at,0,5)<$end && substr($period->ends_at,0,5)>$start)->values();
        if($matches->isEmpty() || substr($matches->first()->starts_at,0,5)!==$start || substr($matches->last()->ends_at,0,5)!==$end)
            throw new \InvalidArgumentException('Time period does not match complete configured periods for this student’s study group and grade. Check the time or enter missed_sessions explicitly.');
        $previous=null;$snapshots=[];
        foreach($matches as $period){
            $from=substr($period->starts_at,0,5);$to=substr($period->ends_at,0,5);
            if($from<$start || $to>$end || $from>=$to || ($previous!==null && $from<$previous))
                throw new \InvalidArgumentException('Time period matches overlapping or partial schedule periods. Review the configured periods or enter missed_sessions explicitly.');
            $snapshots[]=['id'=>$period->id,'name'=>$period->name,'session'=>$period->session,'starts_at'=>$from,'ends_at'=>$to];$previous=$to;
        }
        if(count($snapshots)>99)throw new \InvalidArgumentException('Time period matches more than 99 sessions. Review this row.');
        return $snapshots;
    }

    private function range(string $time): array
    {
        if(!preg_match('/^\s*(\d{1,2}):(\d{2})\s*([ap](?:\.?m\.?)?)?\s*[-–—]\s*(\d{1,2}):(\d{2})\s*([ap](?:\.?m\.?)?)?\s*$/iu',$time,$parts))
            throw new \InvalidArgumentException('Unrecognized time period. Use a range such as 08:00-08:50 AM or 13:00-13:50.');
        $left=strtolower($parts[3]??'');$right=strtolower($parts[6]??'');
        $convert=function(int $hour,int $minute,string $marker): string {
            if($minute>59 || ($marker!==''?($hour<1 || $hour>12):$hour>23))throw new \InvalidArgumentException('Time period contains an invalid hour or minute.');
            if($marker!=='')$hour=$hour%12+(str_starts_with($marker,'p')?12:0);
            return sprintf('%02d:%02d',$hour,$minute);
        };
        $start=$convert((int)$parts[1],(int)$parts[2],$left?:$right);
        $end=$convert((int)$parts[4],(int)$parts[5],$right?:$left);
        if($start>=$end)throw new \InvalidArgumentException('Time period must end after it starts on the same day. Include AM or PM on both times when crossing noon.');
        return [$start,$end];
    }
}
