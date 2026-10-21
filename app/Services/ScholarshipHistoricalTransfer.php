<?php

namespace App\Services;

use App\Models\{ScholarshipExam,ScholarshipCandidate,ScholarshipStudentRank,Student,StudentEnrollment,User};
use App\Support\ScholarshipPermissions;
use App\Support\ScholarshipTransferSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** CSV archive import deliberately preserves old results rather than running today's award rules. */
class ScholarshipHistoricalTransfer
{
    public const COLUMNS=['academic_year','student_id','campus','grade','class','group','class_rank','eoy_average','exam_code','exam_center','exam_center_location','room_no','seat_no','absent','total_score','percentage','exam_rank','award_category','award_subject','award_percent','subject_order'];
    public function __construct(private readonly ScholarshipWorkflow $workflow) {}

    private function fail(string $message): never { throw ValidationException::withMessages(['file'=>$message]); }
    public function authorize(User $user, ScholarshipExam $exam, bool $import): void
    {
        abort_unless(ScholarshipPermissions::allows($user,'tab-data-transfer') && $this->workflow->canView($user,$exam),403);
        abort_unless($import?$this->workflow->central($user,$exam,'import-history'):ScholarshipPermissions::allows($user,'export-data'),403);
        if(!$exam->academicYear || $exam->academicYear->trashed() || $exam->academicYear->period_type!=='regular')$this->fail('Choose a regular academic year configured in Scholarship Settings.');
        if($import && !$exam->isHistorical() && ($exam->seats_locked_at || $exam->published_at || $exam->candidates()->whereHas('scores')->exists() || $exam->awards()->exists()))
            $this->fail('This year already has confirmed seats, scores, or awards. Historical import cannot replace an active or published exam.');
    }

    public function headers(?ScholarshipExam $exam): array
    {
        $names=$exam?$exam->subjects()->orderBy('position')->pluck('name')->unique()->values()->all():['Khmer','Mathematics','English','Science'];
        return [...self::COLUMNS,...array_map(fn($name)=>'score:'.$name,$names)];
    }

    public function sample(?ScholarshipExam $exam): array
    {
        $headers=$this->headers($exam);$rows=[];
        $sampleGrade=3;$subjects=collect();
        if($exam){
            foreach(array_unique([3,...range(1,12)]) as $grade){
                $available=$this->workflow->applicableSubjects($exam,$grade)->unique('name')->values();
                if($available->count()>$subjects->count()){$sampleGrade=$grade;$subjects=$available;}
                if($subjects->count()>=4)break;
            }
        }else $subjects=collect(['Khmer','Mathematics','English','Science'])->map(fn($name)=>(object)['name'=>$name,'max_score'=>100,'weight'=>1]);
        $awardSubjects=$subjects->take(4)->values();
        foreach(range(1,8) as $i){
            $row=array_fill_keys($headers,'');
            $classRank=($i-1)%3+1;$subjectAward=$i>=4&&$i<=7?$awardSubjects->get($i-4):null;
            $row=array_replace($row,['academic_year'=>$exam?->academicYear?->academic_year??'2023-2024','student_id'=>'202300'.$i,'campus'=>'BCH','grade'=>$sampleGrade,
                'class'=>chr(65+intdiv($i-1,3)),'group'=>'III','class_rank'=>$classRank,'eoy_average'=>number_format(100-$classRank*2,2,'.',''),'absent'=>'0']);
            $center=$exam?->centers->sortBy('position')->values()->get($i<=4?0:1)??$exam?->centers->sortBy('position')->first();
            $row['exam_center']=$center?->name??($i<=4?'Phnom Penh Exam Center':'Battambang Exam Center');
            $row['exam_center_location']=$center?->location??($i<=4?'Phnom Penh':'Battambang');
            $row['subject_order']=$exam?app(ScholarshipTransferSettings::class)->exportOrder($exam,$sampleGrade):json_encode(['band'=>['Khmer','Mathematics','English','Science'],'grade'=>null],JSON_THROW_ON_ERROR);
            $total=0;$weighted=0;$weights=0;
            foreach($subjects as $subject){
                // Overall winners come first; subject winners lead among the remaining candidates.
                $rate=$i<=3?1-($i-1)*.02:($i===8?.60:($subjectAward?->name===$subject->name?.95:.70));
                $max=$subject->max_score;$score=round($max*$rate,2);
                $row['score:'.$subject->name]=$score;$total+=$score;$weighted+=($score/$max)*$subject->weight;$weights+=$subject->weight;
            }
            if($weights>0)$row=array_replace($row,['total_score'=>round($total,2),'percentage'=>round($weighted/$weights*100,4)]);
            if($i<=3)$row=array_replace($row,['award_category'=>'total_'.$i,'award_percent'=>[1=>100,2=>75,3=>50][$i]]);
            elseif($subjectAward)$row=array_replace($row,['award_category'=>'subject','award_subject'=>$subjectAward->name,'award_percent'=>25]);
            $rows[]=$row;
        }
        foreach($rows as &$row)if($row['total_score']!=='')$row['exam_rank']=1+count(array_filter($rows,fn($other)=>$other['total_score']!==''&&$other['total_score']>$row['total_score']));
        unset($row);
        return [$headers,...array_map('array_values',$rows)];
    }

    /** Returns a plan for display and hashing; it never writes application data. */
    public function preview(User $user, ScholarshipExam $exam, string $path, bool $replace=false): array
    {
        $exam->load(['academicYear','subjects','centers']);$this->authorize($user,$exam,true);
        $handle=fopen($path,'rb');if(!$handle)$this->fail('Unable to read the CSV file.');
        try{
            $headers=fgetcsv($handle,0,',','"','');
            if(!$headers)$this->fail('The CSV file is empty.');
            $headers=array_map(fn($v)=>trim((string)$v),$headers);$headers[0]=ltrim($headers[0],"\xEF\xBB\xBF");
            if(count($headers)!==count(array_unique($headers)))$this->fail('CSV column names must be unique.');
            $missing=array_diff(['academic_year','student_id','class_rank'],$headers);
            if($missing)$this->fail('Missing required columns: '.implode(', ',$missing).'. Download the sample CSV.');
            $unknown=array_diff($headers,$this->headers($exam));
            if($unknown)$this->fail('Unknown columns or subjects: '.implode(', ',$unknown).'. Use the subject names from this year’s settings.');
            $rows=[];$errors=[];$seen=[];$seats=[];$line=1;
            while(($values=fgetcsv($handle,0,',','"',''))!==false){
                $line++;
                if(!array_filter($values,fn($v)=>trim((string)$v)!==''))continue;
                if(count($rows)+count($errors)>=5000)$this->fail('Import a maximum of 5,000 students at a time.');
                $raw=[];
                try{
                    if(count($values)!==count($headers))$this->fail('The number of values does not match the header.');
                    $raw=array_combine($headers,array_map(fn($v)=>trim((string)$v),$values));
                    foreach($raw as $value)if(!mb_check_encoding($value,'UTF-8'))$this->fail('Save the file as CSV UTF-8.');
                    $row=$this->row($user,$exam,$raw,$replace);
                    if(isset($seen[$row['student_id']]))$this->fail('The same student appears more than once in this CSV.');
                    $seen[$row['student_id']]=true;
                    if($row['room_no']!==null){
                        $seat=mb_strtolower($row['exam_center']).':'.$row['room_no'].':'.$row['seat_no'];
                        if(isset($seats[$seat]))$this->fail('Duplicate center / room / seat in this CSV.');$seats[$seat]=true;
                    }
                    $rows[]=['line'=>$line]+$row;
                }catch(ValidationException $e){$errors[]=['line'=>$line,'student_id'=>$raw['student_id']??'','message'=>implode(' ',array_merge(...array_values($e->errors())))];}
            }
            if(!$rows&&!$errors)$this->fail('The CSV has no student records.');
            // Compare imported seats with candidates outside this file; no existing seat may be stolen.
            $replaced=collect($rows)->pluck('candidate_id')->filter()->all();
            foreach($exam->candidates()->whereNotIn('id',$replaced)->whereNotNull('room_number')->get() as $existing){
                $key=mb_strtolower((string)$existing->exam_center_name).':'.$existing->room_number.':'.$existing->room_seat;
                if(isset($seats[$key]))$errors[]=['line'=>0,'student_id'=>$existing->snapshot['student_code']??'','message'=>'A selected seat already belongs to an existing candidate outside this file.'];
            }
            $settings=app(ScholarshipTransferSettings::class)->plan($exam,$rows,$replace);
            $errors=[...$errors,...$settings['errors']];unset($settings['errors']);
            return ['exam_id'=>$exam->id,'version'=>$exam->version,'replace'=>$replace,'rows'=>$rows,'errors'=>$errors,'report_settings'=>$settings,
                'created'=>count(array_filter($rows,fn($r)=>!$r['candidate_id'])),'updated'=>count(array_filter($rows,fn($r)=>$r['candidate_id']))];
        }finally{fclose($handle);}
    }

    private function number(array $raw,string $field,float $max=99999999.99,bool $integer=false): int|float|null
    {
        $value=$raw[$field]??'';if($value==='')return null;
        if(!preg_match($integer?'/^\d+$/':'/^\d+(?:\.\d{1,4})?$/D',$value) || !is_finite((float)$value) || (float)$value>$max || ($integer&&(int)$value<1))$this->fail($field.': enter '.($integer?'a positive whole number':'a number from 0 to '.$max).'.');
        if(!$integer&&$field!=='percentage'&&round((float)$value,2)!=(float)$value)$this->fail($field.': use at most two decimal places.');
        return $integer?(int)$value:(float)$value;
    }

    private function row(User $user, ScholarshipExam $exam,array $raw,bool $replace): array
    {
        if($raw['academic_year']!==$exam->academicYear->academic_year)$this->fail('Academic Year must match the selected Scholarship Settings.');
        $students=Student::where('student_id',$raw['student_id'])->get();
        if($raw['student_id']==='' || $students->count()!==1)$this->fail('Student ID must match exactly one existing student. Import student profiles first if necessary.');
        $student=$students->first();
        $enrollments=StudentEnrollment::with(['campus','grade','schoolClass','schoolGroup','session'])->where('student_id',$student->id)->where('academic_year_id',$exam->academic_year_id)->get();
        if($enrollments->count()>1)$enrollments=$enrollments->filter(function($enrollment)use($raw){
            $placement=['campus'=>$enrollment->campus?->campus_name_en,'grade'=>$enrollment->grade?$this->workflow->gradeNumber($enrollment->grade):null,'class'=>$enrollment->schoolClass?->class_name,'group'=>$enrollment->session?->session_short_name?:$enrollment->schoolGroup?->group_name];
            foreach($placement as $key=>$value)if(($raw[$key]??'')!==''&&mb_strtolower($raw[$key])!==mb_strtolower((string)$value))return false;
            return true;
        });
        if($enrollments->count()!==1)$this->fail('One matching enrollment in this historical academic year is required. Import missing enrollments first, or provide campus, grade, class, and group to identify the correct enrollment.');
        $enrollment=$enrollments->first();
        abort_unless(ScholarshipPermissions::allows($user,'import-history',(int)$enrollment->campus_id),403);
        $grade=$enrollment->grade?$this->workflow->gradeNumber($enrollment->grade):null;
        if(!$grade || !$enrollment->campus || !$enrollment->schoolClass)$this->fail('Complete the historical campus, Grade 1–12, and class enrollment first.');
        $expected=['campus'=>$enrollment->campus->campus_name_en,'grade'=>(string)$grade,'class'=>$enrollment->schoolClass->class_name,'group'=>$enrollment->session?->session_short_name?:$enrollment->schoolGroup?->group_name];
        foreach($expected as $key=>$value)if(($raw[$key]??'')!==''&&mb_strtolower($raw[$key])!==mb_strtolower((string)$value))$this->fail($key.' does not match this student’s historical enrollment (expected '.($value?:'blank').').');
        $rank=$this->number($raw,'class_rank',3,true);if(!$rank)$this->fail('Class Rank is required (1, 2, or 3).');
        $candidate=$exam->candidates()->where(fn($q)=>$q->where('student_id',$student->id)->orWhere('student_enrollment_id',$enrollment->id))->first();
        if($candidate&&!$replace)$this->fail('This student is already a scholarship candidate. Enable Replace Matching Records to preview an explicit update.');
        $otherRank=ScholarshipStudentRank::whereHas('enrollment',fn($q)=>$q->where('student_id',$student->id)->where('academic_year_id',$exam->academic_year_id))->where('student_enrollment_id','!=',$enrollment->id)->exists();
        if($otherRank)$this->fail('A class rank already exists for another enrollment of this student in this year.');
        $eoy=$this->number($raw,'eoy_average',100);$room=$this->number($raw,'room_no',1000000,true);$seat=$this->number($raw,'seat_no',10000000,true);
        if(($room===null)!==($seat===null))$this->fail('Provide both Room No. and Seat No., or leave both blank.');
        $code=$raw['exam_code']??'';$center=$raw['exam_center']??'';
        if(mb_strlen($code)>24 || mb_strlen($center)>160)$this->fail('Exam Code may have up to 24 characters and Exam Center up to 160.');
        $location=$raw['exam_center_location']??'';
        if(mb_strlen($location)>80)$this->fail('exam_center_location may have up to 80 characters.');
        if($location!==''&&$center==='')$this->fail('Provide exam_center when exam_center_location is filled.');
        $existingCenter=$exam->centers->first(fn($item)=>mb_strtolower(trim($item->name))===mb_strtolower($center));
        $center=$existingCenter?->name??$center;
        $subjectOrder=app(ScholarshipTransferSettings::class)->parseOrder($exam,$grade,$raw['subject_order']??'');
        if(!in_array($raw['absent']??'',['','0','1'],true))$this->fail('Absent must be 0 or 1.');
        $absent=($raw['absent']??'')==='1';$scores=[];$subjectAbsences=[];$applicable=$this->workflow->applicableSubjects($exam,$grade);
        foreach($raw as $header=>$value)if(str_starts_with($header,'score:')&&$value!==''){
            $matches=$applicable->where('name',substr($header,6));if($matches->count()!==1)$this->fail(substr($header,6).' is not a unique configured subject for Grade '.$grade.'.');
            $subject=$matches->first();$isAbsent=strtoupper(trim($value))==='A';
            $scores[$subject->id]=$isAbsent?0:$this->number($raw,$header,(float)$subject->max_score);
            if($isAbsent)$subjectAbsences[$subject->id]=true;
        }
        if($absent){
            if(collect($scores)->contains(fn($score)=>$score!=0))$this->fail('A student absent from all subjects can only have zero or A scores.');
            foreach($applicable as $subject){$scores[$subject->id]=0;$subjectAbsences[$subject->id]=true;}
        }elseif($applicable->isNotEmpty()&&count($subjectAbsences)===$applicable->count())$absent=true;
        $total=$this->number($raw,'total_score');$percent=$this->number($raw,'percentage',100);$examRank=$this->number($raw,'exam_rank',10000000,true);
        $category=$raw['award_category']??'';$awardSubject=$raw['award_subject']??'';$award=$this->number($raw,'award_percent',100);
        if(mb_strlen($category)>160||mb_strlen($awardSubject)>100)$this->fail('Award Category or Award Subject is too long.');
        if(($category==='')!==($award===null))$this->fail('Provide both Award Category and Award Percent, or leave both blank.');
        if($category==='subject'&&($awardSubject===''||$applicable->where('name',$awardSubject)->count()!==1))$this->fail('For a subject award, select an Award Subject configured for this grade.');
        if($awardSubject!==''&&$category!=='subject')$this->fail('Award Subject is only used when Award Category is subject.');
        if($absent&&(($total!==null&&$total!=0)||($percent!==null&&$percent!=0)||$examRank!==null||$award!==null))$this->fail('A student absent from all subjects must have zero totals and no exam rank or award.');
        if($subjectAbsences&&in_array($category,['total_1','total_2','total_3'],true))$this->fail('Overall awards require attendance in all subjects.');
        if($category==='subject'&&isset($subjectAbsences[$applicable->firstWhere('name',$awardSubject)?->id]))$this->fail('A student cannot receive an award for a subject they missed.');
        if($applicable->isNotEmpty()&&count($scores)===$applicable->count()){
            $sum=round(array_sum($scores),2);$weight=$applicable->sum('weight');
            $calculated=round($applicable->sum(fn($s)=>$scores[$s->id]/$s->max_score*$s->weight)/$weight*100,4);
            if($total!==null&&abs($total-$sum)>.005)$this->fail('Total Score does not match the subject scores (expected '.$sum.').');
            if($percent!==null&&abs($percent-$calculated)>.01)$this->fail('Percentage does not match this year’s subject weighting (expected '.$calculated.').');
            $total??=$sum;$percent??=$calculated;
        }
        return ['student_id'=>$student->id,'student_code'=>$raw['student_id'],'student_name'=>$student->full_name_en,'enrollment_id'=>$enrollment->id,'candidate_id'=>$candidate?->id,
            'campus_id'=>(int)$enrollment->campus_id,'class_id'=>(int)$enrollment->class_id,'group_id'=>$enrollment->group_id,'grade'=>$grade,'class_rank'=>$rank,'eoy_average'=>$eoy,
            'campus'=>$expected['campus'],'class'=>$expected['class'],'group'=>$expected['group'],'name_kh'=>$student->full_name_kh,
            'room_no'=>$room,'seat_no'=>$seat,'exam_code'=>$code,'exam_center'=>$center,'exam_center_location'=>$location,'subject_order'=>$subjectOrder,'absent'=>$absent,'scores'=>$scores,'subject_absences'=>$subjectAbsences,
            'total_score'=>$total,'percentage'=>$percent,'exam_rank'=>$examRank,'award_category'=>$category,'award_subject'=>$awardSubject,'award_percent'=>$award];
    }

    public function commit(User $user, ScholarshipExam $exam,string $path,bool $replace,string $previewHash): array
    {
        return DB::transaction(function()use($user,$exam,$path,$replace,$previewHash){
            // Same lock order as normal rank assignment/settings; validation is repeated inside the transaction.
            \App\Models\AcademicYear::lockForUpdate()->findOrFail($exam->academic_year_id);
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);
            $plan=$this->preview($user,$exam,$path,$replace);
            if($plan['errors'])$this->fail('The file no longer passes validation. Preview it again.');
            if(!hash_equals($previewHash,hash('sha256',json_encode($plan,JSON_THROW_ON_ERROR))))$this->fail('The file, settings, or student records changed after preview. Preview the file again.');
            // Clear only explicitly replaced seats first, allowing deliberate swaps without temporary uniqueness conflicts.
            $ids=collect($plan['rows'])->pluck('candidate_id')->filter();
            $exam->candidates()->whereIn('id',$ids)->update(['room_number'=>null,'room_seat'=>null]);
            $centers=$exam->centers()->get()->keyBy(fn($center)=>mb_strtolower(trim($center->name)));
            foreach($plan['report_settings']['centers'] as $key=>$values){
                $center=$centers->get($key)??$exam->centers()->make(['name'=>$values['name'],'position'=>$centers->count()]);
                $center->location=$values['location'];$center->save();$centers->put($key,$center);
            }
            if($plan['report_settings']['subject_orders']!==null)$exam->subject_orders=$plan['report_settings']['subject_orders'];
            foreach($plan['rows'] as $row){
                ScholarshipStudentRank::updateOrCreate(['student_enrollment_id'=>$row['enrollment_id']],['rank'=>$row['class_rank'],'eoy_average'=>$row['eoy_average'],'assigned_by'=>$user->id]);
                $center=null;
                if($row['exam_center']!==''){
                    $key=mb_strtolower($row['exam_center']);$center=$centers->get($key);
                    if(!$center){$center=$exam->centers()->create(['name'=>$row['exam_center'],'position'=>$centers->count()]);$centers->put($key,$center);}
                }
                $snapshot=['student_code'=>$row['student_code'],'name_en'=>$row['student_name'],'name_kh'=>$row['name_kh'],'campus'=>$row['campus'],'class'=>implode(' / ',array_filter([$row['class'],$row['group']])),'eoy_average'=>$row['eoy_average'],
                    'historical'=>true,'historical_award_category'=>$row['award_category'],'historical_award_subject'=>$row['award_subject']];
                $candidate=$row['candidate_id']?$exam->candidates()->findOrFail($row['candidate_id']):new ScholarshipCandidate(['exam_id'=>$exam->id]);
                $candidate->fill(['student_enrollment_id'=>$row['enrollment_id'],'student_id'=>$row['student_id'],'campus_id'=>$row['campus_id'],'class_id'=>$row['class_id'],'group_id'=>$row['group_id'],'grade_number'=>$row['grade'],'class_rank'=>$row['class_rank'],'snapshot'=>$snapshot,
                    'room_number'=>$row['room_no'],'room_seat'=>$row['seat_no'],'exam_center_id'=>$center?->id,'exam_center_name'=>$center?->name,'seat_scope'=>$center?->id??0,'candidate_code'=>$row['exam_code']?:null,
                    'absent'=>$row['absent'],'result_total'=>$row['total_score'],'result_percentage'=>$row['percentage'],'result_rank'=>$row['exam_rank'],'award_percent'=>$row['award_percent'],'scored_by'=>$row['scores']?$user->id:null,'reviewed_by'=>null,'reviewed_at'=>null,'version'=>($candidate->version??0)+1])->save();
                $candidate->scores()->delete();foreach($row['scores'] as $subject=>$score)$candidate->scores()->create(['subject_id'=>$subject,'score'=>$score,'is_absent'=>$row['subject_absences'][$subject]??false,'entry_method'=>'historical','source_reference'=>'CSV import']);
                $exam->awards()->where('candidate_id',$candidate->id)->delete();
                if($row['award_percent']!==null)$exam->awards()->create(['grade_number'=>$row['grade'],'slot'=>'historical_'.$candidate->id,'candidate_id'=>$candidate->id,'award_percent'=>$row['award_percent'],'reason'=>'Imported original award: '.$row['award_category'].($row['award_subject']?' / '.$row['award_subject']:'')]);
            }
            $campuses=$exam->candidates()->distinct()->pluck('campus_id')->map(fn($id)=>(int)$id)->sort()->values()->all();
            $exam->forceFill(['campus_ids'=>$campuses,'historical_imported_at'=>$exam->historical_imported_at??now(),'historical_imported_by'=>$exam->historical_imported_by??$user->id,'version'=>$exam->version+1])->save();
            $exam->events()->create(['user_id'=>$user->id,'action'=>'historical-csv-imported','details'=>['created'=>$plan['created'],'updated'=>$plan['updated'],'sha256'=>hash_file('sha256',$path),'candidate_student_ids'=>collect($plan['rows'])->pluck('student_code')->all(),'report_settings_changes'=>$plan['report_settings']['changes']]]);
            return ['created'=>$plan['created'],'updated'=>$plan['updated']];
        });
    }

    public function export(User $user,ScholarshipExam $exam): iterable
    {
        $exam->load(['academicYear','subjects','centers']);$this->authorize($user,$exam,false);$headers=$this->headers($exam);yield $headers;
        $candidates=$this->workflow->candidates($user,$exam)->with(['scores','enrollment.schoolClass','enrollment.session','enrollment.schoolGroup'])->orderBy('grade_number')->orderBy('id')->get()->filter(fn($candidate)=>ScholarshipPermissions::allows($user,'export-data',$candidate->campus_id));
        $results=$this->workflow->results($exam,$candidates)->keyBy(fn($row)=>$row['candidate']->id);
        $awards=$exam->awards()->get()->keyBy('candidate_id');
        foreach($candidates as $candidate){
            $result=$results[$candidate->id];$snapshot=$candidate->snapshot;$enrollment=$candidate->enrollment;$award=$awards->get($candidate->id);$category=$snapshot['historical_award_category']??'';$awardSubject=$snapshot['historical_award_subject']??'';
            if(!$exam->isHistorical()&&$award){$category=str_starts_with($award->slot,'subject_')?'subject':$award->slot;if($category==='subject')$awardSubject=$exam->subjects->firstWhere('id',(int)substr($award->slot,8))?->name??'';}
            $row=array_fill_keys($headers,'');$row=array_replace($row,['academic_year'=>$exam->academicYear->academic_year,'student_id'=>$snapshot['student_code'],'campus'=>$snapshot['campus'],'grade'=>$candidate->grade_number,'class'=>$enrollment?->schoolClass?->class_name??'','group'=>$enrollment?->session?->session_short_name?:$enrollment?->schoolGroup?->group_name??'',
                'class_rank'=>$candidate->class_rank,'eoy_average'=>$snapshot['eoy_average']??'','exam_code'=>$candidate->candidate_code??'','exam_center'=>$candidate->exam_center_name??'','room_no'=>$candidate->room_number??'','seat_no'=>$candidate->room_seat??'','absent'=>$candidate->absent?'1':'0','total_score'=>$result['total']??'','percentage'=>$result['percentage']??'','exam_rank'=>$result['rank']??'','award_category'=>$category,'award_subject'=>$awardSubject,'award_percent'=>$result['award']??'']);
            $row['exam_center_location']=$exam->centers->firstWhere('id',$candidate->exam_center_id)?->location??'';
            $row['subject_order']=app(ScholarshipTransferSettings::class)->exportOrder($exam,(int)$candidate->grade_number);
            foreach($this->workflow->applicableSubjects($exam,$candidate->grade_number) as $subject){$score=$candidate->scores->firstWhere('subject_id',$subject->id);$row['score:'.$subject->name]=($candidate->absent||$score?->is_absent)?'A':($score?->score??'');}
            yield array_values($row);
        }
    }
}
