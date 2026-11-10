<?php
namespace App\Services;

use App\Models\{AcademicYear,SchoolInfo,Student,StudentEnrollment,ScholarshipExam,ScholarshipStudentRank,ScholarshipCertificate,ScholarshipCertificateSetting,User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** One-time old-system archive transfer; issued numbers are never generated or replaced. */
class ScholarshipLegacyImport
{
    private function fail(string $message): never {throw ValidationException::withMessages(['file'=>$message]);}
    private function date(string $value,string $format): string
    {
        $date=\DateTimeImmutable::createFromFormat('!'.$format,$value);$error=\DateTimeImmutable::getLastErrors();
        if(!$date||($error&&($error['warning_count']||$error['error_count'])))$this->fail('Invalid historical date: '.$value);
        return $date->format('Y-m-d');
    }
    public function normalized(array $raw): array
    {
        $grade=filter_var($raw['grade_level'],FILTER_VALIDATE_INT);
        if(!$grade||$grade>11)$this->fail('Historical grade must be 1–11.');
        $unused=$grade<=6?'score_physics':'score_science';
        if(!is_numeric($raw[$unused])||(float)$raw[$unused]!==0.0)$this->fail('The unused subject has a nonzero score; review it before importing.');
        $scores=[];
        foreach(['Khmer'=>'score_khmer',($grade<=6?'Science':'Physics')=>($grade<=6?'score_science':'score_physics'),'Math'=>'score_math','English'=>'score_english'] as $name=>$field){
            if(!is_numeric($raw[$field])||(float)$raw[$field]<0||(float)$raw[$field]>100)$this->fail('Subject scores must be between 0 and 100.');
            $scores[$name]=(float)$raw[$field];
        }
        if(!in_array($raw['stu_rank'],['1','2','3'],true))$this->fail('Class rank must be 1, 2, or 3.');
        $label=trim($raw['result_rank']);$subject='';$category='';$type='class';$place=['1'=>'1st Place','2'=>'2nd Place','3'=>'3rd Place'][$raw['stu_rank']];
        if(in_array($label,['1st Place','2nd Place','3rd Place'],true)){$type='exam';$place=$label;$category='total_'.substr($label,0,1);}
        elseif(str_starts_with($label,'Best in ')){$subject=substr($label,8);if(!array_key_exists($subject,$scores))$this->fail('Unknown or inapplicable historical award subject.');$type='subject';$place='';$category='subject';}
        elseif($label!=='')$this->fail('Unknown historical result label.');
        $award=trim($raw['result_award']);
        if(($category==='')!==($award===''))$this->fail('Historical award label and percent must both be present.');
        if($award!==''&&!preg_match('/^(100|75|50|25)%$/',$award))$this->fail('Unknown historical award percentage.');
        $absent=count(array_filter($scores,fn($score)=>$score>0))===0;
        if($absent&&$category!=='')$this->fail('An absent student has a historical award; review the original record.');
        if(!preg_match('/^(\d{4})-(\d{4})$/',$raw['academic_year'],$year)||(int)$year[2]!==((int)$year[1]+1))$this->fail('Invalid academic year.');
        if(!preg_match('/^'.substr($year[2],-2).'-(\d{3})$/',$raw['certificate_no'],$number)||(int)$number[1]<1)$this->fail('Keep the original YY-NNN certificate number.');
        $class=trim($raw['Class']??preg_replace('/^\d+/','',$raw['grade']));
        if($class===''||(string)$grade.$class!==trim($raw['grade']))$this->fail('Class and grade do not agree.');
        return ['grade'=>$grade,'class'=>$class,'scores'=>$scores,'absent'=>$absent,'total'=>array_sum($scores),'category'=>$category,'subject'=>$subject,'type'=>$type,'place'=>$place,
            'award_percent'=>$award===''?null:(float)rtrim($award,'%'),'sequence'=>(int)$number[1],
            'exam_date'=>$this->date($raw['exam_date'],'d-M-y'),'certificate_date'=>$this->date($raw['certificate_date'],'d-F-Y'),
            'result_date'=>$this->date($raw['result_approved_date'],'d-M-y'),
            'scholarship_year'=>trim($raw['award_for_ay'])?:$year[2].'-'.((int)$year[2]+1)];
    }
    /** Read-only plan. Student ID is the sole identity key. */
    public function plan(array $paths): array
    {
        $students=Student::get(['id','student_id'])->groupBy(fn($s)=>(string)$s->student_id);
        $campuses=SchoolInfo::get()->keyBy(fn($c)=>strtoupper(trim($c->campus_name_en)));
        $years=AcademicYear::regular()->get()->keyBy('academic_year');
        $enrollments=StudentEnrollment::with(['grade','schoolClass'])->get()->groupBy(fn($e)=>$e->academic_year_id.'|'.$e->student_id);
        $files=[];$numbers=[];$identities=[];$seats=[];$sequences=[];
        foreach($paths as $path){
            $h=fopen($path,'rb');if(!$h)$this->fail('Source file not found.');$rows=[];$line=1;
            try{
                $headers=fgetcsv($h,0,',','"','');if(!$headers)$this->fail('Empty source file.');$headers[0]=ltrim($headers[0],"\xEF\xBB\xBF");
                if(count($headers)!==count(array_unique($headers)))$this->fail('Duplicate source headers.');
                $required=['awarding_id','student_id','student_name_eng','student_name_kh','academic_year','campus','grade','grade_level','stu_rank','stu_group','score_khmer','score_science','score_physics','score_math','score_english','result_rank','result_award','certificate_no','exam_date','certificate_date','result_approved_date','award_for_ay','registrar_name','registrar_position','president_name','president_position','exam_center','exam_room','exam_seat'];
                if(array_diff($required,$headers))$this->fail('Missing required archive columns.');
                while(($values=fgetcsv($h,0,',','"',''))!==false){
                    $line++;if(!array_filter($values,fn($v)=>trim($v)!==''))continue;
                    if(count($values)!==count($headers))$this->fail('Invalid CSV row width.');
                    $raw=array_combine($headers,$values);foreach($raw as $v)if(!mb_check_encoding($v,'UTF-8'))$this->fail('Use UTF-8 source files.');
                    $row=$this->normalized($raw);$year=$years->get($raw['academic_year']);if(!$year)$this->fail('Academic year is not configured.');
                    $match=$students->get((string)$raw['student_id'],collect());if($match->count()!==1)$this->fail('Student ID must match exactly one student.');$student=$match->first();
                    $es=$enrollments->get($year->id.'|'.$student->id,collect());if($es->count()!==1)$this->fail('Student ID must have exactly one enrollment in the source year.');$e=$es->first();
                    $campus=$campuses->get(strtoupper(trim($raw['campus']))==='CKD'?'CKD1':strtoupper(trim($raw['campus'])));
                    if(!$campus||(int)$e->campus_id!==$campus->id||app(ScholarshipWorkflow::class)->gradeNumber($e->grade)!==$row['grade']||preg_replace('/^\d+/','',(string)$e->schoolClass?->class_name)!==$row['class'])$this->fail('Historical enrollment campus/grade/class does not agree with the source.');
                    $identity=$year->id.'|'.$student->id;$sequence=$year->id.'|'.$row['sequence'];
                    if(isset($identities[$identity])||isset($numbers[$raw['certificate_no']])||isset($sequences[$sequence]))$this->fail('Duplicate student or certificate number in the archive.');
                    $identities[$identity]=true;$numbers[$raw['certificate_no']]=true;$sequences[$sequence]=true;
                    foreach(['exam_room','exam_seat'] as $field)if(!ctype_digit($raw[$field])||(int)$raw[$field]<1)$this->fail('Invalid room or seat number.');
                    $seat=$year->id.'|'.$raw['exam_center'].'|'.(int)$raw['exam_room'].'|'.(int)$raw['exam_seat'];if(isset($seats[$seat]))$this->fail('Duplicate historical room/seat.');$seats[$seat]=true;
                    $certificate=['student_id'=>$student->id,'campus_id'=>$campus->id,'campus'=>$campus->campus_name_en,'student_name'=>trim($raw['student_name_eng']),
                        'grade'=>$row['grade'],'class'=>$raw['grade'],'type'=>$row['type'],'place'=>$row['place'],'subject'=>$row['subject'],
                        'award_percent'=>$row['award_percent']===null?'':(string)$row['award_percent'],'academic_year'=>$year->academic_year,
                        'source'=>'legacy:'.$raw['awarding_id'],'historical_issue_date'=>$row['certificate_date'],'historical_scholarship_year'=>$row['scholarship_year'],
                        'historical_location'=>'Phnom Penh','historical_signers'=>[['name'=>$raw['president_name'],'position'=>$raw['president_position']],['name'=>$raw['registrar_name'],'position'=>$raw['registrar_position']]]];
                    $rows[]=$row+['year_id'=>$year->id,'student_id'=>$student->id,'enrollment_id'=>$e->id,'campus_id'=>$campus->id,'class_id'=>$e->class_id,'group_id'=>$e->group_id,'certificate'=>$certificate,'raw'=>$raw];
                }
            }catch(ValidationException $error){$this->fail(basename($path).' row '.$line.': '.implode(' ',array_merge(...array_values($error->errors()))));}
            finally{fclose($h);}
            if(!$rows)$this->fail('No archive records.');
            $yearIds=array_unique(array_column($rows,'year_id'));if(count($yearIds)!==1)$this->fail('Use one academic year per source file.');
            $yearId=reset($yearIds);$exam=ScholarshipExam::where('academic_year_id',$yearId)->first();
            if($exam&&($exam->candidates()->exists()||$exam->published_at||$exam->seats_locked_at||$exam->isHistorical()||$exam->awards()->exists()))$this->fail('The scholarship exam already contains records; this archive import will not overwrite them.');
            if(ScholarshipCertificate::where('academic_year_id',$yearId)->exists()||ScholarshipCertificate::whereIn('certificate_number',array_column(array_column($rows,'raw'),'certificate_no'))->exists())$this->fail('Existing issued certificates conflict with this archive.');
            if(ScholarshipStudentRank::whereIn('student_enrollment_id',array_column($rows,'enrollment_id'))->exists())$this->fail('Existing class ranks require reconciliation before import.');
            foreach(['exam_date','certificate_date','result_date'] as $field)if(count(array_unique(array_column($rows,$field)))!==1)$this->fail('Conflicting dates within the source year.');
            $files[]=['filename'=>basename($path),'sha256'=>hash_file('sha256',$path),'year_id'=>$yearId,'exam_id'=>$exam?->id,'rows'=>$rows];
        }
        if(!$files)$this->fail('Select archive files.');
        return ['files'=>$files,'hash'=>hash('sha256',json_encode($files,JSON_THROW_ON_ERROR))];
    }
    public function import(User $user,array $paths,string $hash): array
    {
        abort_unless($user->isSuperAdmin(),403);
        return DB::transaction(function()use($user,$paths,$hash){
            AcademicYear::orderBy('id')->lockForUpdate()->get();
            $plan=$this->plan($paths);if(!hash_equals($hash,$plan['hash']))$this->fail('Source files or student records changed. Review the plan again.');
            $summary=[];
            foreach($plan['files'] as $file){
                $first=$file['rows'][0];$exam=$file['exam_id']?ScholarshipExam::lockForUpdate()->findOrFail($file['exam_id']):new ScholarshipExam();
                $exam->fill(['academic_year_id'=>$file['year_id'],'name'=>$first['raw']['academic_year'],'exam_date'=>$first['exam_date'],'campus_ids'=>array_values(array_unique(array_column($file['rows'],'campus_id'))),
                    'eligible_ranks'=>[],'room_capacity'=>$exam->room_capacity?:25,'tie_policy'=>'competition','created_by'=>$exam->created_by??$user->id,
                    'published_at'=>$first['result_date'].' 00:00:00','historical_imported_at'=>now(),'historical_imported_by'=>$user->id,'version'=>($exam->version??0)+1])->save();
                $subjects=$exam->subjects()->get();
                foreach([['Khmer',1,6],['Science',1,6],['Math',1,6],['English',1,6],['Khmer',7,11],['Physics',7,11],['Math',7,11],['English',7,11]] as $position=>[$name,$from,$to]){
                    $matches=$subjects->filter(fn($s)=>$s->name===$name&&$s->grade_from<=$from&&$s->grade_to>=$to);
                    if($matches->isEmpty())$exam->subjects()->create(['name'=>$name,'grade_from'=>$from,'grade_to'=>$to,'max_score'=>100,'weight'=>1,'position'=>$position]);
                }
                $subjects=$exam->subjects()->get();$centers=[];
                foreach($file['rows'] as $row){
                    $raw=$row['raw'];$center=$centers[$raw['exam_center']]??=$exam->centers()->firstOrCreate(['name'=>$raw['exam_center']],['location'=>'Phnom Penh','position'=>count($centers)]);
                    ScholarshipStudentRank::create(['student_enrollment_id'=>$row['enrollment_id'],'rank'=>(int)$raw['stu_rank'],'assigned_by'=>$user->id]);
                    DB::table('tb_scholarship_center_campus')->updateOrInsert(['exam_id'=>$exam->id,'campus_id'=>$row['campus_id']],['exam_center_id'=>$center->id]);
                    $snapshot=['student_code'=>$raw['student_id'],'name_en'=>$raw['student_name_eng'],'name_kh'=>$raw['student_name_kh'],'gender'=>$raw['gender']??'','campus'=>$row['certificate']['campus'],'class'=>$raw['grade'].' / '.$raw['stu_group'],'grade_class'=>$raw['grade'],
                        'historical'=>true,'historical_award_category'=>$row['category'],'historical_award_subject'=>$row['subject'],'historical_certificate'=>$row['certificate'],
                        'legacy_source'=>['file'=>$file['filename'],'sha256'=>$file['sha256'],'record'=>$raw]];
                    $candidate=$exam->candidates()->create(['student_enrollment_id'=>$row['enrollment_id'],'student_id'=>$row['student_id'],'campus_id'=>$row['campus_id'],'class_id'=>$row['class_id'],'group_id'=>$row['group_id'],'grade_number'=>$row['grade'],'class_rank'=>(int)$raw['stu_rank'],'snapshot'=>$snapshot,
                        'room_number'=>(int)$raw['exam_room'],'room_seat'=>(int)$raw['exam_seat'],'exam_center_id'=>$center->id,'exam_center_name'=>$center->name,'seat_scope'=>$center->id,
                        'candidate_code'=>null,'absent'=>$row['absent'],'result_total'=>$row['total'],'result_percentage'=>$row['total']/4,'result_rank'=>null,'award_percent'=>$row['award_percent'],'scored_by'=>$user->id]);
                    foreach($row['scores'] as $name=>$score){
                        $matches=$subjects->filter(fn($s)=>$s->name===$name&&$s->grade_from<=$row['grade']&&$s->grade_to>=$row['grade']);
                        if($matches->count()!==1||(float)$matches->first()->max_score!==100.0)$this->fail('Historical subject settings are not compatible.');
                        $candidate->scores()->create(['subject_id'=>$matches->first()->id,'score'=>$score,'is_absent'=>$score===0.0,'entry_method'=>'historical','source_reference'=>$file['filename']]);
                    }
                    if($row['category']!=='')$exam->awards()->create(['grade_number'=>$row['grade'],'slot'=>'historical_'.$candidate->id,'candidate_id'=>$candidate->id,'award_percent'=>$row['award_percent'],'reason'=>'Imported original award: '.$raw['result_rank']]);
                    $cert=$row['certificate'];$cert['fingerprint']=ScholarshipCertificates::historicalFingerprint($cert);
                    ScholarshipCertificate::create(['academic_year_id'=>$file['year_id'],'student_id'=>$row['student_id'],'campus_id'=>$row['campus_id'],'sequence'=>$row['sequence'],'certificate_number'=>$raw['certificate_no'],'type'=>$row['type'],'snapshot'=>$cert,'status'=>'active','assigned_by'=>$user->id]);
                }
                $setting=ScholarshipCertificateSetting::firstOrNew(['academic_year_id'=>$file['year_id']]);$setting->fill(['issue_date'=>$first['certificate_date'],'location'=>'Phnom Penh','scholarship_year'=>$first['scholarship_year'],'signers'=>$first['certificate']['historical_signers'],'version'=>($setting->version??0)+1])->save();
                $exam->events()->create(['user_id'=>$user->id,'action'=>'legacy-certificates-imported','details'=>['file'=>$file['filename'],'sha256'=>$file['sha256'],'count'=>count($file['rows']),'certificate_numbers_preserved'=>true,'zero_scores_are_absent'=>true]]);
                $summary[]=['academic_year'=>$first['raw']['academic_year'],'records'=>count($file['rows']),'absent'=>count(array_filter($file['rows'],fn($r)=>$r['absent'])),'exam_id'=>$exam->id];
            }
            return $summary;
        });
    }
}
