<?php

namespace App\Services;

use App\Models\User;
use App\Support\AttendancePermissions;
use App\Support\AttendancePeriodRecords as PeriodRecords;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceTransfer
{
    public const COLUMNS = ['student_id','academic_year','attendance_date','attendance','time_period','reason','campus','missed_sessions'];

    public function preview(User $user, string $path, string $filename): string
    {
        $stream=fopen($path,'rb');
        try {
            if(fread($stream,3)!=="\xEF\xBB\xBF")rewind($stream);
            $header=fgetcsv($stream,0,',','"','');
            if(!$header)throw ValidationException::withMessages(['file'=>'The CSV is empty.']);
            $aliases=['studentid'=>'student_id','academicyear'=>'academic_year','absdate'=>'attendance_date','abstime'=>'time_period','attandance'=>'attendance','status'=>'attendance','remarks'=>'reason'];
            $header=array_map(function($key)use($aliases){$key=strtolower(trim(preg_replace('/^\xEF\xBB\xBF/','',$key)));$key=preg_replace('/[\s-]+/','_',$key);return $aliases[str_replace('_','',$key)]??$key;},$header);
            if(count(array_unique($header))!==count($header))throw ValidationException::withMessages(['file'=>'CSV column headings must be unique.']);
            if(array_diff(['student_id','academic_year','attendance_date','attendance'],$header))throw ValidationException::withMessages(['file'=>'Required columns: student_id, academic_year, attendance_date, attendance. Download the template first.']);
            $id=(string)Str::uuid();
            DB::transaction(function()use($stream,$header,$user,$filename,$id){
                DB::table('attendance_import_batches')->insert(['id'=>$id,'user_id'=>$user->id,'filename'=>mb_substr(basename($filename),0,255),'status'=>'preview','created_at'=>now(),'updated_at'=>now()]);
                $years=DB::table('tb_academic_year')->whereNull('deleted_at')->get()->groupBy('academic_year');
                $campuses=DB::table('tb_school_info')->whereNull('deleted_at')->get()->keyBy('id');
                $allowed=AttendancePermissions::writeCampuses($user,'import');
                $cache=[];$seen=[];$rows=[];$line=1;
                while(($values=fgetcsv($stream,0,',','"',''))!==false){
                    $line++;
                    if(count($values)===1&&trim((string)$values[0])==='')continue;
                    if($line>50001)throw ValidationException::withMessages(['file'=>'Use files with no more than 50,000 data rows. Split larger files.']);
                    $source=[];$normalized=null;$reason=null;$outcome='pending';
                    foreach($header as $index=>$column)$source[$column]=trim((string)($values[$index]??''));
                    try {
                        if(count($values)!==count($header))throw new \InvalidArgumentException('The row has a different number of columns from its header.');
                        foreach($source as $value)if(!mb_check_encoding($value,'UTF-8'))throw new \InvalidArgumentException('Save the file as CSV UTF-8.');
                        $normalized=$this->normalize($source,$years,$campuses,$allowed,$cache);
                        $key=$normalized['academic_year_id'].':'.$normalized['student_id'].':'.$normalized['attendance_date'];
                        foreach($seen[$key]??[] as $previous){
                            if($previous['normalized']===$normalized){$outcome='duplicate';$reason='Identical duplicate in this file; not imported twice.';break;}
                            try{PeriodRecords::merge($previous['normalized'],$normalized);}
                            catch(\InvalidArgumentException $e){
                                $outcome='review';$reason='Multiple different records for the same student and date overlap or have unknown periods. '.$e->getMessage();
                                foreach($rows as &$pending)if($pending['csv_row']===$previous['line']){$pending['outcome']='review';$pending['reason']=$reason;}unset($pending);
                                DB::table('attendance_import_rows')->where('batch_id',$id)->where('csv_row',$previous['line'])->update(['outcome'=>'review','reason'=>$reason]);
                            }
                        }
                        if($outcome!=='duplicate')$seen[$key][]=['line'=>$line,'normalized'=>$normalized];
                        if($outcome==='pending'){
                            $mark=$this->existing($normalized);
                            if($mark && $this->same($mark,$normalized)){$outcome='duplicate';$reason='Already recorded; unchanged.';}
                            elseif($mark && empty($mark->leave_id)){
                                try{$merged=PeriodRecords::merge($this->periodRecord($mark),$normalized);if($merged['duplicate']){$outcome='duplicate';$reason='Already recorded; unchanged.';}}
                                catch(\InvalidArgumentException $e){$outcome='review';$reason='Existing attendance differs. It will not be overwritten.';}
                            }elseif($mark){$outcome='review';$reason='Approved leave attendance is already recorded. Review the leave record first.';}
                            if($outcome==='pending' && $this->scheduledLeave($normalized)){$outcome='review';$reason='Approved leave is scheduled for this date. Review the leave record first.';}
                        }
                    } catch(\InvalidArgumentException $e){$outcome='review';$reason=$e->getMessage();}
                    $rows[]=['batch_id'=>$id,'csv_row'=>$line,'source'=>json_encode($source,JSON_INVALID_UTF8_SUBSTITUTE),'normalized'=>$normalized?json_encode($normalized):null,'outcome'=>$outcome,'reason'=>$reason];
                    if(count($rows)>=250){DB::table('attendance_import_rows')->insert($rows);$rows=[];}
                }
                if($rows)DB::table('attendance_import_rows')->insert($rows);
                if(!DB::table('attendance_import_rows')->where('batch_id',$id)->exists())throw ValidationException::withMessages(['file'=>'No attendance rows were found.']);
            });
            return $id;
        } finally {fclose($stream);}
    }

    private function normalize(array $row, $years, $campuses, array $allowed, array &$cache): array
    {
        $code=$row['student_id'];
        if($code==='')throw new \InvalidArgumentException('Student ID is required.');
        $year=$years->get($row['academic_year']);
        if(!$year||$year->count()!==1)throw new \InvalidArgumentException('Academic year must match one configured academic year exactly.');
        $yearId=$year->first()->id;
        $date=$this->date($row['attendance_date']);
        if($date>now('Asia/Bangkok')->toDateString())throw new \InvalidArgumentException('Future attendance cannot be imported.');
        $status=match(strtoupper($row['attendance'])){'E','EX'=>'EX','U','UX'=>'UX','T','L'=>'T','P'=>'P',default=>null};
        if(!$status)throw new \InvalidArgumentException('Use E / EX, U / UX, T, or P for Attendance.');
        $cacheKey=$code.':'.$yearId;
        if(!array_key_exists($cacheKey,$cache)){
            $students=DB::table('tb_student')->where('student_id',$code)->get(['id']);
            $cache[$cacheKey]=$students->count()===1?DB::table('tb_student_enrollment')->where('student_id',$students->first()->id)->where('academic_year_id',$yearId)->whereNotIn('enrollment_status',['pending','cancelled','promotion_cancelled'])->get():collect();
        }
        $matches=$cache[$cacheKey]->filter(fn($e)=>(!$e->enrolled_on||substr($e->enrolled_on,0,10)<=$date)&&(!$e->ended_on||substr($e->ended_on,0,10)>=$date));
        if(filled($row['campus']??null))$matches=$matches->filter(fn($e)=>strtolower($campuses->get($e->campus_id)?->campus_name_en??'')===strtolower($row['campus']));
        if($matches->count()!==1)throw new \InvalidArgumentException('No unique enrollment on this date. Check Student ID, academic year, campus, and enrollment dates.');
        $e=$matches->first();
        if(!in_array((int)$e->campus_id,$allowed,true))throw new \InvalidArgumentException('You do not have attendance import access to this enrollment campus.');
        if(!$e->class_id)throw new \InvalidArgumentException('The enrollment has no class.');
        $missed=$row['missed_sessions']??'';
        if($missed!==''&&(!ctype_digit($missed)||(int)$missed>99))throw new \InvalidArgumentException('Missed sessions must be a whole number from 0 to 99.');
        $periods=[];$automatic=$missed==='' && in_array($status,['EX','UX','T'],true);
        if($automatic){
            if(blank($row['time_period']??null))throw new \InvalidArgumentException('Enter time_period to calculate missed sessions, or enter missed_sessions explicitly.');
            $periods=app(AttendanceImportPeriods::class)->resolve($e,$row['time_period'],$cache);
            $missed=count($periods);
        }
        elseif($missed!=='' && (int)$missed>0 && in_array($status,['EX','UX','T'],true) && filled($row['time_period']??null)){
            // Explicit counts remain authoritative; attach periods only when they agree exactly.
            try{$matched=app(AttendanceImportPeriods::class)->resolve($e,$row['time_period'],$cache);if(count($matched)===(int)$missed)$periods=$matched;}
            catch(\InvalidArgumentException $ignored){}
        }
        $reason=trim(($row['reason']??'').(filled($row['time_period']??null)?' | Time: '.$row['time_period']:''),' |');
        if(mb_strlen($reason)>500)throw new \InvalidArgumentException('Reason and time together exceed 500 characters.');
        return ['academic_year_id'=>$yearId,'student_id'=>$e->student_id,'enrollment_id'=>$e->id,'campus_id'=>$e->campus_id,'grade_id'=>(int)($e->grade_id??0),'class_id'=>$e->class_id,'attendance_date'=>$date,'status'=>$status,'missed_sessions'=>$status==='P'?0:(int)$missed,'remarks'=>$reason?:null,'absent_periods'=>$periods,'auto_periods'=>$automatic,'source_time_period'=>$row['time_period']??''];
    }

    private function date(string $value): string
    {
        foreach(['Y-m-d','n/j/Y','j-M-Y'] as $format){
            try{$date=CarbonImmutable::createFromFormat('!'.$format,$value);$errors=CarbonImmutable::getLastErrors();if($date&&(!$errors||(!$errors['warning_count']&&!$errors['error_count'])))return $date->toDateString();}catch(\Throwable){}
        }
        throw new \InvalidArgumentException('Invalid date. Use YYYY-MM-DD (recommended), M/D/YYYY, or DD-Mon-YYYY.');
    }

    private function existing(array $row)
    {
        return DB::table('attendance_daily_marks')->where(['academic_year_id'=>$row['academic_year_id'],'student_id'=>$row['student_id'],'attendance_date'=>$row['attendance_date']])->first();
    }

    private function same($mark, array $row): bool
    {
        if(PeriodRecords::mixed($mark))return false;
        return (int)$mark->enrollment_id===(int)$row['enrollment_id']&&$mark->status===$row['status']&&(int)$mark->missed_sessions===$row['missed_sessions']&&trim((string)$mark->remarks)===trim((string)$row['remarks'])
            && (empty($row['absent_periods']) || AttendanceImportPeriods::sameSnapshots(json_decode($mark->absent_periods??'[]',true)?:[],$row['absent_periods']));
    }

    private function periodRecord(object $mark): array
    {
        $periods=PeriodRecords::periods($mark);
        if(!$periods && $mark->missed_sessions>0 && preg_match('/\| Time: (.+)$/u',(string)$mark->remarks,$match)){
            try{$cache=[];$e=DB::table('tb_student_enrollment')->where('id',$mark->enrollment_id)->first();if($e)$periods=app(AttendanceImportPeriods::class)->resolve($e,$match[1],$cache);}
            catch(\InvalidArgumentException $ignored){}
        }
        return ['enrollment_id'=>$mark->enrollment_id,'status'=>$mark->status,'missed_sessions'=>(int)$mark->missed_sessions,'remarks'=>$mark->remarks,'absent_periods'=>$periods];
    }

    private function scheduledLeave(array $row): bool
    {
        return DB::table('attendance_leave_days as d')->join('attendance_leaves as l','l.id','=','d.leave_id')
            ->where('d.academic_year_id',$row['academic_year_id'])->where('d.student_id',$row['student_id'])->where('d.attendance_date',$row['attendance_date'])
            ->where('d.status','scheduled')->where('l.status','approved')->exists();
    }

    public function confirm(User $user, string $id): void
    {
        DB::transaction(function()use($user,$id){
            $batch=DB::table('attendance_import_batches')->where('id',$id)->lockForUpdate()->first();
            abort_unless($batch&&(int)$batch->user_id===$user->id,404);
            abort_if($batch->status==='validating',409,'The file is still being validated.');
            $rows=DB::table('attendance_import_rows')->where('batch_id',$id)->where('outcome','pending')->orderBy('id')->limit(100)->get();
            foreach($rows as $record){
                $row=json_decode($record->normalized,true);
                $campus=(int)$row['campus_id'];
                // Same campus/student lock order as daily attendance saves prevents concurrent overwrites.
                DB::table('tb_school_info')->where('id',$campus)->lockForUpdate()->first();
                DB::table('tb_student')->where('id',$row['student_id'])->lockForUpdate()->first();
                abort_unless(AttendancePermissions::allows($user,'import',$campus),403);
                $enrollment=DB::table('tb_student_enrollment')->where('id',$row['enrollment_id'])->first();
                $valid=$enrollment&&(int)$enrollment->student_id===(int)$row['student_id']&&(int)$enrollment->academic_year_id===(int)$row['academic_year_id']&&(int)$enrollment->campus_id===$campus&&(int)$enrollment->class_id===(int)$row['class_id']&&(int)($enrollment->grade_id??0)===$row['grade_id']&&!in_array($enrollment->enrollment_status,['pending','cancelled','promotion_cancelled'])&&(!$enrollment->enrolled_on||substr($enrollment->enrolled_on,0,10)<=$row['attendance_date'])&&(!$enrollment->ended_on||substr($enrollment->ended_on,0,10)>=$row['attendance_date']);
                $existing=$this->existing($row);
                $leave=$this->scheduledLeave($row);
                if($valid && !empty($row['auto_periods'])){
                    try{
                        $periodCache=[];$periods=app(AttendanceImportPeriods::class)->resolve($enrollment,$row['source_time_period'],$periodCache);
                        if(!AttendanceImportPeriods::sameSnapshots($periods,$row['absent_periods']))throw new \InvalidArgumentException('Configured periods changed.');
                    }catch(\InvalidArgumentException $e){
                        DB::table('attendance_import_rows')->where('id',$record->id)->update(['outcome'=>'review','reason'=>'Period settings or study group changed after preview. Preview the file again.']);continue;
                    }
                }
                $merged=null;
                if($valid && $existing && empty($existing->leave_id) && !$leave && !$this->same($existing,$row)){
                    try{$merged=PeriodRecords::merge($this->periodRecord($existing),$row);}
                    catch(\InvalidArgumentException $ignored){}
                }
                if(!$valid||($existing && (!$merged || $merged['duplicate']))||$leave){
                    $duplicate=$valid&&$existing&&($this->same($existing,$row)||($merged['duplicate']??false));
                    DB::table('attendance_import_rows')->where('id',$record->id)->update(['outcome'=>$duplicate?'duplicate':'review','reason'=>$duplicate?'Already recorded; unchanged.':(!$valid?'Enrollment changed after preview. Preview a corrected file.':($leave?'Approved leave is scheduled for this date. Review the leave record first.':'Existing attendance differs. It was not overwritten.'))]);
                    continue;
                }
                $scope=array_intersect_key($row,array_flip(['academic_year_id','campus_id','grade_id','class_id','attendance_date']));
                $register=DB::table('attendance_daily_registers')->where($scope)->lockForUpdate()->first();
                $registerId=$register?->id??DB::table('attendance_daily_registers')->insertGetId($scope+['version'=>0,'updated_by'=>$user->id,'created_at'=>now(),'updated_at'=>now()]);
                if($merged){
                    $markId=$existing->id;
                    DB::table('attendance_daily_marks')->where('id',$markId)->update(['status'=>$merged['status'],'missed_sessions'=>$merged['missed_sessions'],'absent_periods'=>json_encode($merged['absent_periods']),'remarks'=>$merged['remarks'],'updated_at'=>now()]);
                }else $markId=DB::table('attendance_daily_marks')->insertGetId(array_intersect_key($row,array_flip(['academic_year_id','student_id','enrollment_id','attendance_date','status','missed_sessions','remarks']))+['register_id'=>$registerId,'absent_periods'=>json_encode($row['absent_periods']??[]),'created_by'=>$user->id,'created_at'=>now(),'updated_at'=>now()]);
                DB::table('attendance_daily_registers')->where('id',$registerId)->update(['version'=>DB::raw('version + 1'),'updated_by'=>$user->id,'updated_at'=>now()]);
                DB::table('attendance_daily_events')->insert(['register_id'=>$registerId,'user_id'=>$user->id,'details'=>json_encode(['action'=>$merged?'historical_period_append':'historical_import','batch_id'=>$id,'csv_row'=>$record->csv_row,'exception_only'=>true,'before'=>$merged?(array)$existing:null,'after'=>$merged?array_replace($row,collect($merged)->except('duplicate')->all()):$row]),'created_at'=>now(),'updated_at'=>now()]);
                DB::table('attendance_import_rows')->where('id',$record->id)->update(['outcome'=>'imported','mark_id'=>$markId]);
            }
            DB::table('attendance_import_batches')->where('id',$id)->update(['status'=>DB::table('attendance_import_rows')->where('batch_id',$id)->where('outcome','pending')->exists()?'importing':'completed','updated_at'=>now()]);
        },3);
    }
}
