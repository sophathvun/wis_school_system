<?php
namespace App\Services;

use App\Models\{PlacementTestApplicant,PlacementTestAttempt,PlacementTestAnswer,PlacementTestEvent,PlacementTestForm,PlacementTestQuestion,PlacementTestScan,PlacementTestSetting,User};
use App\Support\{PlacementTestPermissions,ScholarshipAnswerSheetLayout};
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlacementTests {
    public function __construct(private ScholarshipAnswerSheetLayout $layout){}
    private function fail(string $message,string $field='placement_test'): never {throw ValidationException::withMessages([$field=>$message]);}
    public function authorize(User $user,string $action,?int $campusId=null): void {abort_unless(PlacementTestPermissions::allows($user,$action,$campusId),403);}
    public function event(?User $user,string $action,?PlacementTestApplicant $applicant=null,?PlacementTestAttempt $attempt=null,array $details=[]): void {
        PlacementTestEvent::create(['applicant_id'=>$applicant?->id??$attempt?->applicant_id,'attempt_id'=>$attempt?->id,'user_id'=>$user?->id,'action'=>$action,'details'=>$details,'created_at'=>now()]);
    }
    public function applicantCode(): string {
        $prefix='PT-'.now()->format('ym').'-';$last=PlacementTestApplicant::where('application_code','like',$prefix.'%')->lockForUpdate()->orderByDesc('application_code')->value('application_code');
        return $prefix.str_pad(((int)substr((string)$last,-4))+1,4,'0',STR_PAD_LEFT);
    }
    public function saveApplicant(User $user,array $data,?PlacementTestApplicant $applicant=null): PlacementTestApplicant {
        $this->authorize($user,'applicants',$data['campus_id']??$applicant?->campus_id);
        return DB::transaction(function()use($user,$data,$applicant){
            $new=!$applicant;$applicant=$applicant?PlacementTestApplicant::lockForUpdate()->findOrFail($applicant->id):new PlacementTestApplicant();
            if($applicant->enrolled_student_id)$this->fail('An enrolled applicant cannot be edited.','applicant');
            $applicant->fill($data);if(!$applicant->exists){$applicant->application_code=$this->applicantCode();$applicant->created_by=$user->id;$applicant->status='draft';}$applicant->save();
            $this->event($user,$new?'applicant-created':'applicant-updated',$applicant,null,['fields'=>array_keys($data)]);return $applicant->fresh(['academicYear','campus','targetGrade','recommendedGrade']);
        });
    }
    public function archiveApplicant(User $user,PlacementTestApplicant $applicant): PlacementTestApplicant {
        $this->authorize($user,'applicants',$applicant->campus_id);
        if($applicant->enrolled_student_id)$this->fail('An enrolled applicant cannot be archived.','applicant');
        if($applicant->attempts()->whereIn('status',['assigned','in_progress','submitted'])->exists())$this->fail('Invalidate the applicant’s active test session before archiving the applicant.','applicant');
        $applicant->update(['status'=>'archived']);$this->event($user,'applicant-archived',$applicant);return $applicant->fresh();
    }
    public function saveQuestion(User $user,array $data,?PlacementTestQuestion $question=null): PlacementTestQuestion {
        $this->authorize($user,'questions');if(($data['status']??'draft')==='approved'){$this->authorize($user,'publish');$data['approved_by']=$user->id;$data['approved_at']=now();}$type=$data['type']??'multiple_choice';$options=collect($data['options']??[])->map(fn($value)=>trim((string)$value))->filter(fn($value)=>$value!=='')->values()->all();$style=$data['option_label_style']??'letters';
        if($type==='true_false'){$options=['True','False'];$style='letters';}
        if(in_array($type,['multiple_choice','true_false'],true)){
            if(count($options)<2||count($options)>26)$this->fail('Provide between 2 and 26 answer choices.','options');
            $labels=$this->optionLabels($style,count($options));$correct=$style==='letters'?mb_strtoupper(trim((string)($data['correct_answer']??''))):trim((string)($data['correct_answer']??''));if(!in_array($correct,$labels,true))$this->fail('The correct answer must match one of the answer choices.','correct_answer');
        }else{$options=[];$style='letters';$correct=trim((string)($data['correct_answer']??''));if($type==='fill_blank'&&$correct==='')$this->fail('Enter the accepted answer for a fill-in-the-blank question.','correct_answer');}
        if($question&&$question->forms()->whereIn('tb_placement_test_form.status',['approved','published'])->exists())$this->fail('This question is used by an approved or published form. Create a new question revision instead.','question');
        $data['options']=$options;$data['option_label_style']=$style;$data['correct_answer']=$correct;$data['created_by']=$question?->created_by??$user->id;
        $question??=new PlacementTestQuestion();$question->fill($data)->save();return $question->fresh('grade');
    }
    private function optionLabels(string $style,int $count): array {return $style==='numbers'?array_map('strval',range(1,$count)):array_map(fn($index)=>chr(65+$index),range(0,$count-1));}
    public function duplicateQuestion(User $user,PlacementTestQuestion $question): PlacementTestQuestion {
        $this->authorize($user,'questions');$copy=$question->replicate(['approved_by','approved_at']);$copy->status='draft';$copy->created_by=$user->id;$copy->prompt=$question->prompt.' (Copy)';$copy->save();return $copy->fresh('grade');
    }
    public function archiveQuestion(User $user,PlacementTestQuestion $question): PlacementTestQuestion {
        $this->authorize($user,'questions');$question->update(['status'=>'archived']);return $question->fresh('grade');
    }
    public function saveForm(User $user,array $data,?PlacementTestForm $form=null): PlacementTestForm {
        $this->authorize($user,'forms');
        if($form&&in_array($form->status,['approved','published'],true))$this->fail('Approved or published forms are locked. Create a new revision to make changes.','form');
        $data['form_code']=mb_strtoupper($data['form_code']);if(!in_array($data['form_code'],['A','B','C'],true))$this->fail('Test Form must be A, B, or C.','form_code');
        if((float)$data['pass_score']>(float)$data['max_score'])$this->fail('Passing score cannot exceed the maximum score.','pass_score');
        $data['subject_rules']=$this->normalizeRules($data['subject_rules']??[]);$data['created_by']=$form?->created_by??$user->id;$form??=new PlacementTestForm();$form->fill($data)->save();return $form->fresh(['grade','questions']);
    }
    private function normalizeRules(array $rules): array {
        return collect($rules)->map(function($rule){$subject=trim((string)($rule['subject']??''));$max=(float)($rule['max_score']??0);$pass=(float)($rule['pass_score']??0);
            if($subject===''||$max<=0||$pass<0||$pass>$max)$this->fail('Every subject rule needs a subject, positive maximum score, and a valid passing score.','subject_rules');
            return ['subject'=>$subject,'max_score'=>round($max,2),'pass_score'=>round($pass,2),'required'=>(bool)($rule['required']??true)];})->values()->all();
    }
    public function syncFormQuestions(User $user,PlacementTestForm $form,array $items): PlacementTestForm {
        $this->authorize($user,'forms');if(in_array($form->status,['approved','published'],true))$this->fail('Approved or published forms are locked. Create a new revision.','form');
        $ids=collect($items)->pluck('question_id')->map(fn($id)=>(int)$id);if($ids->duplicates()->isNotEmpty())$this->fail('A question can be assigned only once.','questions');
        $questions=PlacementTestQuestion::whereIn('id',$ids)->get()->keyBy('id');$sync=[];$order=0;
        foreach($items as $item){$question=$questions->get((int)$item['question_id']);if(!$question||$question->grade_id!==$form->grade_id)$this->fail('Every assigned question must belong to the same grade as the form.','questions');
            $sync[$question->id]=['order_no'=>++$order,'subject'=>$question->subject,'points'=>round((float)($item['points']??$question->default_points),2)];}
        $form->questions()->sync($sync);return $form->fresh(['grade','questions']);
    }
    public function changeFormStatus(User $user,PlacementTestForm $form,string $status): PlacementTestForm {
        $this->authorize($user,in_array($status,['approved','published'],true)?'publish':'forms');
        if(!in_array($status,['draft','review','approved','published','archived'],true))$this->fail('Invalid form status.','status');
        if(in_array($status,['approved','published'],true)){
            $form->load('questions');if($form->questions->isEmpty())$this->fail('Assign questions before approving this form.','questions');
            $total=round($form->questions->sum(fn($q)=>(float)$q->pivot->points),2);if(abs($total-(float)$form->max_score)>.001)$this->fail("Question points total {$total}; it must equal the form maximum of {$form->max_score}.",'max_score');
            foreach($form->subject_rules??[] as $rule){$subjectTotal=round($form->questions->where('pivot.subject',$rule['subject'])->sum(fn($q)=>(float)$q->pivot->points),2);if(abs($subjectTotal-(float)$rule['max_score'])>.001)$this->fail("{$rule['subject']} questions total {$subjectTotal}; the configured maximum is {$rule['max_score']}.",'subject_rules');}
            $blueprint=['subjects'=>$form->questions->groupBy(fn($q)=>$q->pivot->subject)->map(fn($items)=>['questions'=>$items->count(),'points'=>round($items->sum(fn($q)=>(float)$q->pivot->points),2)])->sortKeys()->all(),'difficulty'=>$form->questions->groupBy('difficulty')->map(fn($items)=>['questions'=>$items->count(),'points'=>round($items->sum(fn($q)=>(float)$q->pivot->points),2)])->sortKeys()->all(),'topics'=>$form->questions->pluck('topic')->filter()->unique()->sort()->values()->all()];
            $comparison=PlacementTestForm::where('grade_id',$form->grade_id)->where('id','!=',$form->id)->whereIn('status',['approved','published'])->whereNotNull('blueprint')->latest('revision')->first();
            if($comparison){$other=$comparison->blueprint??[];if(array_keys($blueprint['subjects'])!==array_keys($other['subjects']??[]))$this->fail('This version must cover the same subjects as the approved test versions for this grade.','questions');foreach($blueprint['subjects'] as $subject=>$values)if(abs($values['points']-(float)($other['subjects'][$subject]['points']??0))>.001)$this->fail("{$subject} must carry the same points across Forms A, B and C.",'questions');$tolerance=max(1,(float)$form->max_score*.20);foreach(['easy','medium','difficult'] as $level)if(abs((float)($blueprint['difficulty'][$level]['points']??0)-(float)($other['difficulty'][$level]['points']??0))>$tolerance)$this->fail('The difficulty balance differs too much from the approved versions. Adjust the easy, medium and difficult question mix.','questions');}
            $form->blueprint=$blueprint;
            if($status==='approved'){$form->approved_by=$user->id;$form->approved_at=now();}
            if($status==='published'){if(!$form->approved_at)$this->fail('Approve the form before publishing it.','status');$form->published_by=$user->id;$form->published_at=now();}
        }
        $form->status=$status;$form->save();return $form->fresh(['grade','questions']);
    }
    public function duplicateForm(User $user,PlacementTestForm $form): PlacementTestForm {
        $this->authorize($user,'forms');
        return DB::transaction(function()use($user,$form){$form->load('questions');$revision=(int)PlacementTestForm::where('grade_id',$form->grade_id)->where('form_code',$form->form_code)->lockForUpdate()->max('revision')+1;$copy=$form->replicate(['blueprint','approved_by','published_by','approved_at','published_at']);$copy->revision=$revision;$copy->status='draft';$copy->created_by=$user->id;$copy->save();$sync=$form->questions->mapWithKeys(fn($question)=>[$question->id=>['order_no'=>$question->pivot->order_no,'subject'=>$question->pivot->subject,'points'=>$question->pivot->points]])->all();$copy->questions()->sync($sync);return $copy->fresh(['grade','questions']);});
    }
    public function formSnapshot(PlacementTestForm $form): array {
        $form->loadMissing(['grade','questions']);return ['form_id'=>$form->id,'name'=>$form->name,'grade_id'=>$form->grade_id,'grade'=>$form->grade?->grade,'form_code'=>$form->form_code,'revision'=>$form->revision,
            'duration_minutes'=>$form->duration_minutes,'max_score'=>(float)$form->max_score,'pass_score'=>(float)$form->pass_score,'subject_rules'=>$form->subject_rules??[],'instructions'=>$form->instructions,
            'questions'=>$form->questions->map(fn($q)=>['id'=>$q->id,'order'=>(int)$q->pivot->order_no,'order_no'=>(int)$q->pivot->order_no,'subject'=>$q->pivot->subject,'topic'=>$q->topic,'difficulty'=>$q->difficulty,'type'=>$q->type,'prompt'=>$q->prompt,
                'options'=>$q->options,'option_label_style'=>$q->option_label_style??'letters','correct_answer'=>$q->correct_answer,'points'=>(float)$q->pivot->points,'explanation'=>$q->explanation])->sortBy('order')->values()->all()];
    }
    public function assign(User $user,PlacementTestApplicant $applicant,PlacementTestForm $form,string $method): PlacementTestAttempt {
        $this->authorize($user,'assign',$applicant->campus_id);if($form->status!=='published')$this->fail('Only published test forms can be assigned.','form_id');
        $settings=PlacementTestSetting::effective($applicant->campus_id);if(!($settings[$method.'_enabled']??false))$this->fail(ucfirst($method).' placement tests are disabled for this campus.','method');if($applicant->attempts()->count()>=(int)$settings['max_attempts'])$this->fail('This applicant has reached the maximum number of placement-test attempts.','applicant');
        if($applicant->target_grade_id!==$form->grade_id)$this->fail('The test form grade must match the applicant target grade.','form_id');
        if(!in_array($method,['online','paper','manual'],true))$this->fail('Choose Online, Printed & Scan, or Manual Entry.','method');
        if($method==='paper'&&collect($form->questions)->contains(fn($question)=>!in_array($question->type,['multiple_choice','true_false'],true)||count($question->options??[])>4||($question->option_label_style??'letters')!=='letters'))$this->fail('Printed scan forms support only lettered multiple-choice or true/false questions with A–D choices. Use Manual Entry for writing questions, numeric labels, or additional choices.','form_id');
        return DB::transaction(function()use($user,$applicant,$form,$method){$number=(int)$applicant->attempts()->lockForUpdate()->max('attempt_no')+1;$snapshot=$this->formSnapshot($form);
            do{$access=Str::random(48);}while(PlacementTestAttempt::where('access_token',$access)->exists());do{$paper=bin2hex(random_bytes(12));}while(PlacementTestAttempt::where('paper_token',$paper)->exists());
            $attempt=PlacementTestAttempt::create(['applicant_id'=>$applicant->id,'form_id'=>$form->id,'access_token'=>$access,'paper_token'=>$paper,'attempt_no'=>$number,'method'=>$method,'status'=>'assigned','duration_minutes'=>$form->duration_minutes,'max_score'=>$form->max_score,'snapshot'=>$snapshot,'created_by'=>$user->id]);
            $applicant->update(['status'=>'invited']);$this->event($user,'test-assigned',$applicant,$attempt,['method'=>$method,'form'=>$form->form_code,'revision'=>$form->revision]);return $attempt->fresh(['applicant','form']);});
    }
    public function start(PlacementTestAttempt $attempt): PlacementTestAttempt {
        if(!in_array($attempt->status,['assigned','in_progress'],true))$this->fail('This test is no longer available.','attempt');
        if(!$attempt->started_at){$attempt->started_at=now();$attempt->expires_at=now()->addMinutes($attempt->duration_minutes);$attempt->status='in_progress';$attempt->save();$attempt->applicant()->update(['status'=>'testing']);$this->event(null,'online-test-started',$attempt->applicant,$attempt);}
        return $attempt->fresh();
    }
    public function submit(PlacementTestAttempt $attempt,array $responses,string $source='online',?User $user=null,array $awardedScores=[]): PlacementTestAttempt {
        if(in_array($attempt->status,['verified','invalidated'],true))$this->fail('This attempt is already finalized.','attempt');
        $settings=PlacementTestSetting::effective($attempt->applicant->campus_id);if($source==='online'&&$attempt->expires_at&&now()->gt($attempt->expires_at->copy()->addMinutes((int)$settings['online_grace_minutes'])))$this->fail('The test time has expired. Submit the saved answers to an examiner for review.','attempt');
        return DB::transaction(function()use($attempt,$responses,$source,$user,$awardedScores){$attempt=PlacementTestAttempt::lockForUpdate()->findOrFail($attempt->id);$questions=collect($attempt->snapshot['questions']??[])->keyBy('order');
            $zeroBased=array_is_list($responses);
            foreach($questions as $order=>$question){$type=$question['type']??'multiple_choice';$style=$question['option_label_style']??'letters';$answer=trim((string)($zeroBased?($responses[$order-1]??''):($responses[$order]??'')));$manual=in_array($type,['short_answer','essay'],true);if(in_array($type,['multiple_choice','true_false'],true)){$answer=$style==='letters'?mb_strtoupper($answer):$answer;$allowed=$this->optionLabels($style,count($question['options']??[]));if($answer!==''&&!in_array($answer,$allowed,true))$answer='';}
                $scoreKey=array_key_exists($order,$awardedScores)?$order:(array_key_exists((string)$order,$awardedScores)?(string)$order:null);$score=$scoreKey===null?null:min((float)$question['points'],max(0,(float)$awardedScores[$scoreKey]));
                PlacementTestAnswer::updateOrCreate(['attempt_id'=>$attempt->id,'question_order'=>$order],['question_id'=>$question['id']??null,'subject'=>$question['subject'],'question_snapshot'=>$question,'answer'=>$answer?:null,'detected_answer'=>$source==='scan'?($answer?:null):null,'awarded_points'=>$manual&&$score!==null?$score:0,'is_correct'=>$manual&&$score!==null?$score>=(float)$question['points']:null,'flag'=>$manual?($score===null?'manual_review':'manual_reviewed'):null,'source'=>$source]);}
            $attempt->submitted_at??=now();$attempt->status='submitted';$attempt->result='pending';$attempt->save();$written=$questions->filter(fn($question)=>in_array($question['type']??'multiple_choice',['short_answer','essay'],true));$allMarked=$written->every(fn($question,$order)=>array_key_exists($order,$awardedScores)||array_key_exists((string)$order,$awardedScores));return $written->isNotEmpty()&&!$allMarked?$attempt->fresh(['applicant','form','answers']):$this->score($attempt,$user,$source);});
    }
    public function score(PlacementTestAttempt $attempt,?User $user=null,string $source='online'): PlacementTestAttempt {
        $questions=collect($attempt->snapshot['questions']??[])->keyBy('order');$answers=$attempt->answers()->get()->keyBy('question_order');$subjects=[];$total=0;
        foreach($questions as $order=>$question){$answer=$answers->get($order);$type=$question['type']??'multiple_choice';$manual=in_array($type,['short_answer','essay'],true);$correct=$manual?($answer&&$answer->flag==='manual_reviewed'&&$answer->is_correct):($answer&&$answer->answer!==null&&($type==='fill_blank'?mb_strtolower(trim($answer->answer))===mb_strtolower(trim((string)$question['correct_answer'])):$answer->answer===$question['correct_answer']));$points=$manual?(float)($answer?->awarded_points??0):($correct?(float)$question['points']:0);$total+=$points;$subject=$question['subject'];$subjects[$subject]??=['score'=>0,'max_score'=>0,'pass_score'=>0,'passed'=>false];$subjects[$subject]['score']+=$points;$subjects[$subject]['max_score']+=(float)$question['points'];
            if($answer&&!$manual)$answer->update(['is_correct'=>$correct,'awarded_points'=>$points]);}
        foreach($attempt->snapshot['subject_rules']??[] as $rule){$subjects[$rule['subject']]??=['score'=>0,'max_score'=>(float)$rule['max_score'],'pass_score'=>(float)$rule['pass_score'],'passed'=>false];$subjects[$rule['subject']]['pass_score']=(float)$rule['pass_score'];$subjects[$rule['subject']]['required']=(bool)($rule['required']??true);}
        foreach($subjects as &$subject){$subject['score']=round($subject['score'],2);$subject['max_score']=round($subject['max_score'],2);$subject['passed']=$subject['score']>=($subject['pass_score']??0);}unset($subject);
        $requiredPassed=collect($subjects)->every(fn($s)=>!($s['required']??false)||$s['passed']);$passed=$total>=(float)$attempt->snapshot['pass_score']&&$requiredPassed;$attempt->update(['score'=>round($total,2),'percentage'=>$attempt->max_score>0?round($total/(float)$attempt->max_score*100,2):0,'subject_scores'=>$subjects,'result'=>$passed?'passed':'failed','status'=>'verified','verified_by'=>$user?->id,'verified_at'=>now()]);
        $attempt->applicant()->update(['status'=>$passed?'passed':'failed','recommended_grade_id'=>$passed?$attempt->form->grade_id:null]);$this->event($user,'test-scored',$attempt->applicant,$attempt,['source'=>$source,'score'=>$total,'result'=>$passed?'passed':'failed']);return $attempt->fresh(['applicant','form','answers']);
    }
    public function uploadScan(User $user,PlacementTestAttempt $attempt,UploadedFile $image): PlacementTestScan {
        $this->authorize($user,'scan',$attempt->applicant->campus_id);if($attempt->method!=='paper')$this->fail('This attempt was not assigned as a printed test.','image');
        if(!PlacementTestSetting::effective($attempt->applicant->campus_id)['scan_enabled'])$this->fail('Answer-sheet scanning is disabled for this campus.','image');
        $bytes=file_get_contents($image->getRealPath());$configuration=['question_count'=>count($attempt->snapshot['questions']??[]),'choices'=>['A','B','C','D'],'layout_version'=>ScholarshipAnswerSheetLayout::VERSION,'student_sheet'=>false];$read=$this->layout->read($bytes,$configuration);
        if(!hash_equals($attempt->paper_token,$read['token']))$this->fail('This answer sheet belongs to another placement-test attempt.','image');$hash=hash('sha256',$bytes);
        if($attempt->scans()->where('image_hash',$hash)->exists())$this->fail('This answer sheet image was already uploaded.','image');$id=(string)Str::uuid();$processed="placement-test-scans/{$id}.webp";$original="placement-test-scans/original/{$id}.".strtolower($image->extension()?:'jpg');
        $canvas=imagecreatefromstring($bytes);ob_start();imagewebp($canvas,null,88);$webp=ob_get_clean();imagedestroy($canvas);if(!Storage::disk('local')->put($processed,$webp)||!Storage::disk('local')->put($original,$bytes))$this->fail('The scan image could not be stored.','image');
        $scan=$attempt->scans()->create(['image_path'=>$processed,'original_path'=>$original,'image_hash'=>$hash,'detected'=>$read['responses'],'flags'=>$read['flags'],'confidence'=>$read['confidence'],'status'=>'pending','uploaded_by'=>$user->id]);$this->event($user,'answer-sheet-scanned',$attempt->applicant,$attempt,['scan_id'=>$scan->id,'flags'=>$read['flags']]);return $scan;
    }
    public function verifyScan(User $user,PlacementTestScan $scan,array $responses,string $reason=''): PlacementTestAttempt {
        $attempt=$scan->attempt()->with('applicant')->firstOrFail();$this->authorize($user,'score',$attempt->applicant->campus_id);if($scan->status!=='pending')$this->fail('This scan has already been verified.','scan');
        $detected=collect($scan->detected??[])->mapWithKeys(fn($answer,$index)=>[$index+1=>$answer])->all();
        $submitted=collect($responses)->mapWithKeys(fn($answer,$index)=>[(int)$index=>$answer?:null])->all();
        if($submitted!==$detected&&mb_strlen(trim($reason))<5)$this->fail('Enter a reason when correcting detected answers.','reason');$scan->update(['status'=>'verified','verified_by'=>$user->id,'verified_at'=>now(),'reason'=>$reason?:null]);return $this->submit($attempt,$responses,'scan',$user);
    }
    public function invalidateAttempt(User $user,PlacementTestAttempt $attempt,string $reason): PlacementTestAttempt {
        $this->authorize($user,'score',$attempt->applicant->campus_id);if($attempt->status==='invalidated')$this->fail('This session is already invalidated.','attempt');if($attempt->applicant->enrolled_student_id)$this->fail('An enrolled applicant session cannot be invalidated.','attempt');$attempt->update(['status'=>'invalidated','result'=>'pending']);$remaining=$attempt->applicant->attempts()->where('id','!=',$attempt->id)->where('status','verified')->latest('id')->first();$attempt->applicant()->update(['status'=>$remaining?($remaining->result==='passed'?'passed':'failed'):'draft','recommended_grade_id'=>$remaining?->result==='passed'?$remaining->form->grade_id:null]);$this->event($user,'test-invalidated',$attempt->applicant,$attempt,['reason'=>$reason]);return $attempt->fresh(['applicant','form']);
    }
    public function answerSheetSvg(PlacementTestAttempt $attempt,?string $logo=null): string {
        $s=$attempt->snapshot;$questions=$s['questions']??[];if(count($questions)>100)$this->fail('A scanned answer sheet supports a maximum of 100 questions.','questions');$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_XML1,'UTF-8');$applicant=$attempt->applicant;$token=$attempt->paper_token;
        $svg='<svg xmlns="http://www.w3.org/2000/svg" width="210mm" height="297mm" viewBox="0 0 1000 1414"><rect width="1000" height="1414" fill="white"/>';
        $text=function($x,$y,$value,$size=18,$bold=false,$anchor='start')use(&$svg,$e){$svg.='<text x="'.$x.'" y="'.$y.'" font-family="Arial, Noto Sans Khmer, sans-serif" font-size="'.$size.'" font-weight="'.($bold?700:400).'" text-anchor="'.$anchor.'" fill="#172033">'.$e($value).'</text>';};
        foreach(ScholarshipAnswerSheetLayout::MARKERS as [$x,$y])$svg.='<rect x="'.($x-10).'" y="'.($y-10).'" width="20" height="20" fill="black"/>';if($logo)$svg.='<image href="'.$e($logo).'" x="55" y="45" width="130" height="105" preserveAspectRatio="xMidYMid meet"/>';
        $text(205,74,'STUDENT PLACEMENT TEST',30,true);$text(205,105,'Grade '.$s['grade'].' · Test Form '.$s['form_code'].' · Revision '.$s['revision'],19);$text(205,135,'Duration: '.$s['duration_minutes'].' minutes · Maximum: '.$s['max_score'],16);
        $text(65,205,'Applicant: '.$applicant->full_name_en,22,true);$text(65,238,'Application Code: '.$applicant->application_code.'   |   Attempt: '.$attempt->attempt_no,16);$text(65,271,'Campus: '.($applicant->campus?->campus_name_en??'—').'   |   Academic Year: '.($applicant->academicYear?->academic_year??'—'),16);
        foreach($this->layout->bits($token) as $i=>$bit)if($bit==='1')$svg.='<rect x="'.(ScholarshipAnswerSheetLayout::CODE_X+($i%12)*ScholarshipAnswerSheetLayout::CELL).'" y="'.(ScholarshipAnswerSheetLayout::CODE_Y+intdiv($i,12)*ScholarshipAnswerSheetLayout::CELL).'" width="10" height="10" fill="black"/>';
        $qr='data:image/png;base64,'.base64_encode((new Writer(new GDLibRenderer(264,4)))->writeString('WIS-PT:'.$token));$svg.='<image href="'.$qr.'" x="858" y="185" width="108" height="108"/>';$text(740,300,'Sheet identification',11);
        $svg.='<rect x="52" y="315" width="896" height="70" rx="7" fill="#f5f7fb" stroke="#9aa6b2"/>';$text(72,344,'Fill ONE circle completely for every question. Use dark pencil or pen.',16,true);$text(72,370,'Keep all four corner squares clear. Do not fold the answer sheet.',15);
        $text(500,425,'ANSWER SHEET',31,true,'middle');foreach($this->layout->questions(count($questions),['A','B','C','D'],ScholarshipAnswerSheetLayout::VERSION) as $row){$text($row['x']+18,$row['y']+$row['font']*.32,$row['number'],$row['font'],false,'end');foreach($row['dots'] as $dot){$svg.='<circle cx="'.$dot['x'].'" cy="'.$dot['y'].'" r="'.$dot['radius'].'" fill="white" stroke="#303846" stroke-width="1.2"/>';$text($dot['x'],$dot['y']+$row['label_font']*.34,$dot['label'],$row['label_font'],false,'middle');}}
        $text(65,1295,'Candidate signature: ____________________    Invigilator: ____________________',16);$text(500,1345,'Sheet ID: '.$token.' · Print A4 at 100% / Actual size',12,false,'middle');return $svg.'</svg>';
    }
}
