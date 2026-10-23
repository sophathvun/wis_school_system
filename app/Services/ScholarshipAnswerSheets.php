<?php
namespace App\Services;

use App\Models\{User,ScholarshipExam,ScholarshipSubject,ScholarshipPaper,ScholarshipAnswerKey,ScholarshipAnswerSheet,ScholarshipAnswerScan,ScholarshipScore,ScholarshipEvent};
use App\Support\{ScholarshipPermissions,ScholarshipAnswerSheetLayout};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage,Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule,ValidationException};

class ScholarshipAnswerSheets
{
    public function __construct(private ScholarshipWorkflow $workflow,private ScholarshipAnswerSheetLayout $layout){}
    private function fail(string $message): never {throw ValidationException::withMessages(['answer_sheet'=>$message]);}
    public function authorize(User $user,ScholarshipExam $exam,string $action='tab-answer-sheets'): void
    {
        abort_unless(ScholarshipPermissions::allows($user,'tab-answer-sheets')&&ScholarshipPermissions::allows($user,$action)&&$this->workflow->canView($user,$exam),403);
        if(in_array($action,['answer-delete','answer-clear-scans'],true))abort_unless($this->workflow->central($user,$exam,$action),403);
    }
    private function writable(ScholarshipExam $exam): void
    {
        if($exam->isHistorical()||$exam->published_at)$this->fail('Published or historical exams cannot be changed. Reopen published results before correcting answer sheets.');
    }
    private function paperExam(ScholarshipPaper $paper): ScholarshipExam {return $paper->subject->exam;}
    public function candidateAccess(User $user,ScholarshipExam $exam,$candidate,string $action): void
    {
        $this->authorize($user,$exam,$action);
        abort_unless($candidate->exam_id===$exam->id&&ScholarshipPermissions::allows($user,$action,$candidate->campus_id)
            &&$this->workflow->candidates($user,$exam)->whereKey($candidate->id)->exists(),403);
    }
    private function event(User $user,ScholarshipExam $exam,string $action,array $details): void
    {ScholarshipEvent::create(['exam_id'=>$exam->id,'user_id'=>$user->id,'action'=>$action,'details'=>$details]);}
    public function linked(ScholarshipPaper $paper): bool
    {
        return $paper->sheets()->exists()||ScholarshipScore::where('source_paper_id',$paper->id)->exists()
            ||ScholarshipAnswerScan::whereIn('key_id',$paper->keys()->select('id'))->exists();
    }
    public function unlinkStudents(User $user,ScholarshipPaper $paper,int $version): array
    {
        $exam=$this->paperExam($paper);$this->authorize($user,$exam,'answer-unlink');
        return DB::transaction(function()use($user,$exam,$paper,$version){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);$this->writable($exam);
            $paper=ScholarshipPaper::lockForUpdate()->findOrFail($paper->id);
            if($paper->version!==$version)$this->fail('This paper changed. Refresh before unlinking students.');
            if($paper->scores()->exists()||$paper->keys()->whereHas('scans')->exists()||$paper->sheets()->whereHas('scans')->exists()
                ||$paper->sheets()->whereHas('candidate.scores',fn($q)=>$q->where('subject_id',$paper->subject_id)->whereNull('source_paper_id')
                    ->where(fn($q)=>$q->whereNotNull('score')->orWhere('is_absent',true)))->exists())
                $this->fail('Students cannot be unlinked because this paper already has a scan or recorded subject score. Use an Answer Key revision instead.');
            $candidates=$this->workflow->candidates($user,$exam);
            if(!$user->isSuperAdmin())$candidates->whereIn('campus_id',ScholarshipPermissions::campuses($user)->get()
                ->filter(fn($campus)=>ScholarshipPermissions::allows($user,'answer-unlink',$campus->id))->pluck('id'));
            $sheets=$paper->sheets()->whereIn('candidate_id',$candidates->pluck('id'))->lockForUpdate()->get();
            if($sheets->isEmpty())$this->fail('No linked students are available to unlink within your permitted campuses.');
            $this->event($user,$exam,'answer-students-unlinked',['paper_id'=>$paper->id,'sheets'=>$sheets->toArray()]);
            $paper->sheets()->whereIn('id',$sheets->modelKeys())->delete();$paper->increment('version');
            return ['count'=>$sheets->pluck('candidate_id')->unique()->count(),'remaining'=>$paper->sheets()->distinct()->count('candidate_id')];
        });
    }
    public function deletePaper(User $user,ScholarshipPaper $paper,int $version): void
    {
        $exam=$this->paperExam($paper);$this->authorize($user,$exam,'answer-delete');
        DB::transaction(function()use($user,$exam,$paper,$version){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);$this->writable($exam);
            $paper=ScholarshipPaper::lockForUpdate()->findOrFail($paper->id);
            if($paper->version!==$version)$this->fail('This paper changed in another window. Refresh before deleting.');
            if($this->linked($paper))$this->fail('This answer sheet is linked to students, scans or scores and cannot be deleted.');
            $this->event($user,$exam,'answer-paper-deleted',['paper'=>$paper->toArray(),'approved_keys'=>$paper->keys()->get()->toArray()]);
            $paper->keys()->delete();$paper->delete();
        });
    }
    public function savePaper(User $user,ScholarshipExam $exam,array $data,?int $paperId=null): ScholarshipPaper
    {
        $this->authorize($user,$exam,'answer-paper');
        $data=Validator::make($data,[
            'subject_id'=>['required','integer'],'grade'=>['required','integer','between:1,12'],
            'paper_version'=>['required','string','regex:/^[A-Z0-9_-]{1,20}$/'],
            'question_count'=>['required','integer','between:1,100'],'choice_style'=>['required',Rule::in(['letters','numbers'])],
            'choice_count'=>['required','integer','between:2,26'],'points'=>['required','numeric','gt:0','decimal:0,4'],
            'version'=>['required','integer','min:0'],
        ])->validate();
        return DB::transaction(function()use($user,$exam,$data,$paperId){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);$this->writable($exam);
            $subject=$exam->subjects()->findOrFail($data['subject_id']);
            if($data['grade']<$subject->grade_from||$data['grade']>$subject->grade_to)$this->fail('Select a grade covered by this subject.');
            if($data['points']>$subject->max_score)$this->fail('The default question score cannot exceed the subject maximum.');
            $paper=$paperId?ScholarshipPaper::whereHas('subject',fn($q)=>$q->where('exam_id',$exam->id))->lockForUpdate()->findOrFail($paperId):null;
            if($subject->papers()->where('grade_number',$data['grade'])->where('paper_version',$data['paper_version'])
                ->when($paper,fn($q)=>$q->whereKeyNot($paper->id))->exists())
                $this->fail('A paper already exists for '.$subject->name.' · Grade '.$data['grade'].' · Paper '.$data['paper_version'].'. Use Edit to change the existing paper, or choose a different subject, grade or paper version.');
            if(($paper?->version??0)!=(int)$data['version'])$this->fail('This paper changed in another window. Refresh before saving.');
            if($paper&&$this->linked($paper))$this->fail('This answer sheet is linked to students, scans or scores. Create a new paper version to change its setup.');
            $before=$paper?->toArray();$keys=$paper?->keys()->get()->toArray()??[];
            $choices=array_map('strval',array_slice($data['choice_style']==='letters'?range('A','Z'):range(1,26),0,$data['choice_count']));
            $paper??=new ScholarshipPaper(['subject_id'=>$subject->id,'grade_number'=>$data['grade'],'paper_version'=>$data['paper_version']]);
            if($paper->exists)$paper->keys()->delete();
            $paper->fill(['subject_id'=>$subject->id,'grade_number'=>$data['grade'],'paper_version'=>$data['paper_version'],
                'question_count'=>$data['question_count'],'answer_choices'=>$choices,'points_per_question'=>$data['points'],'draft_answers'=>null,'draft_points'=>null,'version'=>($paper->version??0)+1])->save();
            $this->event($user,$exam,'answer-paper-saved',['paper_id'=>$paper->id,'question_count'=>$paper->question_count,'choices'=>$choices,'before'=>$before,'cleared_keys'=>$keys]);return $paper;
        });
    }
    private function answers(ScholarshipPaper $paper,array $answers,bool $complete): array
    {
        if(count($answers)!==$paper->question_count||!array_is_list($answers))$this->fail('Provide one answer for every question.');
        if($complete){
            $missing=[];foreach($answers as $i=>$answer)if($answer===null||$answer==='')$missing[]='Q'.($i+1);
            if($missing)$this->fail('Blank answers: '.implode(', ',$missing).'. Select an answer for every question before approval.');
        }
        return array_map(function($answer)use($paper,$complete){
            $answer=$answer===null||$answer===''?null:(string)$answer;
            if(($complete&&$answer===null)||($answer!==null&&!in_array($answer,$paper->answer_choices,true)))$this->fail('An answer is missing or outside this paper’s choices.');
            return $answer;
        },$answers);
    }
    public function defaultPoints(ScholarshipPaper $paper): array
    {return array_fill(0,$paper->question_count,(float)$paper->points_per_question);}
    private function points(ScholarshipPaper $paper,array $points,bool $complete): array
    {
        if(count($points)!==$paper->question_count||!array_is_list($points))$this->fail('Provide one score for every question.');
        Validator::make(['points'=>$points],['points.*'=>['nullable','numeric','gt:0','decimal:0,4','max:'.$paper->subject->max_score]])->validate();
        $points=array_map(fn($value)=>$value===null||$value===''?null:(float)$value,$points);
        if($complete&&(in_array(null,$points,true)||abs(round(array_sum($points),2)-$paper->subject->max_score)>.001))
            $this->fail('Enter a positive score for every question. The total must equal the subject maximum of '.$paper->subject->max_score.'.');
        return $points;
    }
    public function saveKey(User $user,ScholarshipPaper $paper,array $answers,int $version,?array $points=null): void
    {
        $exam=$this->paperExam($paper);$this->authorize($user,$exam,'answer-key');
        DB::transaction(function()use($user,$exam,$paper,$answers,$version,$points){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);$this->writable($exam);
            $paper=ScholarshipPaper::lockForUpdate()->findOrFail($paper->id);
            if($paper->version!==$version)$this->fail('The draft key changed. Refresh before saving.');
            if(!$paper->question_count)$this->fail('Configure this paper first.');
            $paper->update(['draft_answers'=>$this->answers($paper,$answers,false),
                'draft_points'=>$this->points($paper,$points??$paper->draft_points??$this->defaultPoints($paper),false),'version'=>$version+1]);
            $this->event($user,$exam,'answer-key-draft-saved',['paper_id'=>$paper->id]);
        });
    }
    public function approveKey(User $user,ScholarshipPaper $paper,int $version): ScholarshipAnswerKey
    {
        $exam=$this->paperExam($paper);$this->authorize($user,$exam,'answer-key-approve');
        return DB::transaction(function()use($user,$exam,$paper,$version){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);$this->writable($exam);
            $paper=ScholarshipPaper::lockForUpdate()->findOrFail($paper->id);
            if($paper->version!==$version)$this->fail('The draft key changed. Refresh before approving.');
            $answers=$this->answers($paper,$paper->draft_answers??[],true);$previous=$paper->keys()->latest('revision')->first();
            $points=$this->points($paper,$paper->draft_points??$this->defaultPoints($paper),true);
            if($previous&&$previous->answers===$answers&&array_map('floatval',$previous->question_points??$this->defaultPoints($paper))===$points)$this->fail('This answer key and its question scores are already approved.');
            $key=$paper->keys()->create(['revision'=>($previous?->revision??0)+1,'answers'=>$answers,'question_points'=>$points,'approved_by'=>$user->id,'approved_at'=>now()]);
            $paper->increment('version');
            // A corrected key never silently changes scores, but removes their
            // review status and award selections until an explicit recheck.
            $ids=ScholarshipScore::where('source_paper_id',$paper->id)->where('entry_method','scan')->pluck('candidate_id');
            if($ids->isNotEmpty()){
                $exam->candidates()->whereIn('id',$ids)->update(['reviewed_at'=>null,'reviewed_by'=>null,'version'=>DB::raw('version + 1')]);
                $exam->awards()->where('grade_number',$paper->grade_number)->delete();
            }
            $exam->increment('version');
            $this->event($user,$exam,'answer-key-approved',['paper_id'=>$paper->id,'key_id'=>$key->id,'revision'=>$key->revision,'recheck_candidates'=>$ids->all()]);return $key;
        });
    }
    public function snapshot(ScholarshipPaper $paper,$candidate,int $layoutVersion=ScholarshipAnswerSheetLayout::VERSION): array
    {
        $exam=$this->paperExam($paper);$s=$candidate->snapshot;
        $class=trim(explode(' / ',(string)($s['class']??''))[0]);
        $classLabel=$class!==''&&str_starts_with($class,(string)$candidate->grade_number)?$class:$candidate->grade_number.$class;
        return ($layoutVersion>=2?['layout_version'=>$layoutVersion]:[])+($layoutVersion>=4?['class_label'=>$classLabel,'campus'=>trim((string)($s['campus']??''))]:[])+['exam_id'=>$exam->id,'year'=>$exam->academicYear->academic_year,'date'=>$exam->exam_date?->format('F j, Y')??'',
            'name'=>$s['name_en']??'','student_code'=>$s['student_code']??'','code'=>$candidate->candidate_code,
            'grade'=>$candidate->grade_number,'center'=>$candidate->exam_center_name??'Main Exam Center','center_id'=>$candidate->exam_center_id,
            'room'=>$candidate->room_number,'seat'=>$candidate->room_seat,'subject_id'=>$paper->subject_id,'subject'=>$paper->subject->name,
            'paper_version'=>$paper->paper_version,'question_count'=>$paper->question_count,'choices'=>$paper->answer_choices,
            'points'=>$paper->points_per_question,'max_score'=>$paper->subject->max_score];
    }
    public function current(ScholarshipAnswerSheet $sheet): bool
    {
        $sheet->load(['paper.subject.exam.academicYear','candidate']);
        $snapshot=$this->snapshot($sheet->paper,$sheet->candidate,$sheet->snapshot['layout_version']??1);
        // Sheets printed before the campus label was added retain their original identity.
        if(!array_key_exists('campus',$sheet->snapshot))unset($snapshot['campus']);
        return hash_equals($sheet->fingerprint,hash('sha256',json_encode($snapshot)));
    }
    public function issue(User $user,ScholarshipPaper $paper,array $candidateIds)
    {return $this->linkCandidates($user,$paper,$candidateIds,'answer-print');}
    public function linkStudents(User $user,ScholarshipPaper $paper,int $version)
    {return $this->linkCandidates($user,$paper,null,'answer-link',$version);}
    private function linkCandidates(User $user,ScholarshipPaper $paper,?array $candidateIds,string $action,?int $version=null)
    {
        $exam=$this->paperExam($paper);$this->authorize($user,$exam,$action);
        return DB::transaction(function()use($user,$exam,$paper,$candidateIds,$action,$version){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);$this->writable($exam);
            $paper=ScholarshipPaper::lockForUpdate()->findOrFail($paper->id);
            if($version!==null&&$paper->version!==$version)$this->fail('The paper or answer key changed. Refresh before linking students.');
            if(!$exam->seats_locked_at||!$paper->latestKey()->exists())$this->fail('Confirm seats and approve the answer key before issuing sheets.');
            if($candidateIds===null){
                $query=$this->workflow->candidates($user,$exam)->where('grade_number',$paper->grade_number);
                if(!$user->isSuperAdmin())$query->whereIn('campus_id',ScholarshipPermissions::campuses($user)->get()
                    ->filter(fn($campus)=>ScholarshipPermissions::allows($user,$action,$campus->id))->pluck('id'));
                $candidateIds=$query->pluck('id')->all();
                if(!$candidateIds)$this->fail('No students in this grade are available to link within your permitted campuses.');
            }
            $candidates=$exam->candidates()->whereIn('id',$candidateIds)->orderBy('exam_center_id')->orderBy('room_number')->orderBy('room_seat')->get();
            if(!$candidateIds||$candidates->count()!==count(array_unique($candidateIds)))$this->fail('Select valid candidates.');
            // Linking is by student and paper. Printed layout/seating versions
            // remain separate sheet records so their tokens and scans stay valid.
            $existing=$action==='answer-link'?$paper->sheets()->whereIn('candidate_id',$candidateIds)->orderByDesc('id')->get()->unique('candidate_id')->keyBy('candidate_id'):collect();
            $sheets=collect();
            foreach($candidates as $candidate){
                $this->candidateAccess($user,$exam,$candidate,$action);
                if($candidate->grade_number!==$paper->grade_number||!$candidate->room_seat)$this->fail('Candidate grade or confirmed seating does not match this paper.');
                if($existing->has($candidate->id)){$sheets->push($existing->get($candidate->id));continue;}
                $snapshot=$this->snapshot($paper,$candidate);$fingerprint=hash('sha256',json_encode($snapshot));
                $sheets->push(ScholarshipAnswerSheet::firstOrCreate(['paper_id'=>$paper->id,'candidate_id'=>$candidate->id,'fingerprint'=>$fingerprint],
                    ['snapshot'=>$snapshot,'token'=>bin2hex(random_bytes(12)),'issued_by'=>$user->id]));
            }
            $this->event($user,$exam,$action==='answer-link'?'answer-students-linked':'answer-sheets-issued',['paper_id'=>$paper->id,'sheets'=>$sheets->pluck('id')->all()]);return $sheets;
        });
    }
    public function upload(User $user,ScholarshipExam $exam,ScholarshipPaper $paper,UploadedFile $image,UploadedFile $original): ScholarshipAnswerScan
    {
        $this->authorize($user,$exam,'answer-scan');
        abort_unless($paper->subject->exam_id===$exam->id,404);
        $bytes=$image->get();$identity=$this->layout->read($bytes);
        $sheet=ScholarshipAnswerSheet::where('token',$identity['token'])->first();
        if(!$sheet||$sheet->paper_id!==$paper->id)$this->fail('The identification code does not match this exam / grade / subject / paper.');
        $this->candidateAccess($user,$exam,$sheet->candidate,'answer-scan');
        $detected=$this->layout->read($bytes,['question_count'=>$paper->question_count,'choices'=>$paper->answer_choices,'layout_version'=>$sheet->snapshot['layout_version']??1]);
        $paths=[];
        try{return DB::transaction(function()use($user,$exam,$paper,$sheet,$bytes,$original,$detected,&$paths){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);$this->writable($exam);
            $sheet=ScholarshipAnswerSheet::whereKey($sheet->id)->where('token',$sheet->token)->lockForUpdate()->first();
            if(!$sheet)$this->fail('This sheet was unlinked. Link the students again and print new answer sheets.');
            if(!$exam->seats_locked_at||!$this->current($sheet))$this->fail('This sheet is outdated. Confirm seats and issue a current sheet.');
            $key=$paper->keys()->latest('revision')->first();if(!$key)$this->fail('Approve an answer key first.');
            $hash=hash('sha256',$bytes);
            if($sheet->scans()->where('image_hash',$hash)->where('key_id',$key->id)->exists())$this->fail('This image was already uploaded. Open its existing scan instead.');
            $path='scholarship-answer-scans/'.Str::uuid();
            $paths=[$path.'.png',$path.'-original.'.$original->guessExtension()];
            if(!Storage::disk('local')->put($paths[0],$bytes)||!Storage::disk('local')->put($paths[1],$original->get()))$this->fail('The scan image could not be stored. Please try again.');
            $scan=$sheet->scans()->create(['key_id'=>$key->id,'image_path'=>$paths[0],'original_path'=>$paths[1],'image_hash'=>$hash,
                'detected'=>$detected['responses'],'flags'=>$detected['flags'],'confidence'=>$detected['confidence'],'status'=>'pending','candidate_version'=>$sheet->candidate->fresh()->version,'uploaded_by'=>$user->id]);
            $this->event($user,$exam,'answer-scan-uploaded',['scan_id'=>$scan->id,'sheet_id'=>$sheet->id,'flagged_questions'=>array_keys($detected['flags'])]);return $scan;
        });}catch(\Throwable $e){if($paths)Storage::disk('local')->delete($paths);throw $e;}
    }
    public function verify(User $user,ScholarshipAnswerScan $scan,array $responses,int $version,bool $replace,string $reason): void
    {
        $sheet=$scan->sheet;$exam=$this->paperExam($sheet->paper);$this->candidateAccess($user,$exam,$sheet->candidate,'answer-verify');
        DB::transaction(function()use($user,$scan,$sheet,$exam,$responses,$version,$replace,$reason){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);$this->writable($exam);
            $scan=ScholarshipAnswerScan::lockForUpdate()->findOrFail($scan->id);
            $candidate=$exam->candidates()->lockForUpdate()->findOrFail($sheet->candidate_id);$paper=$sheet->paper;
            if($scan->status!=='pending')$this->fail('This scan has already been verified.');
            if(!$this->current($sheet)||!$exam->seats_locked_at)$this->fail('This sheet no longer matches the confirmed seating / paper.');
            if($candidate->version!==$version||$scan->candidate_version!==$version)$this->fail('The student’s scores changed since this scan. Recheck it before saving.');
            if($scan->key_id!==$paper->keys()->latest('revision')->first()->id)$this->fail('A new answer key was approved. Recheck this scan first.');
            $responses=$this->answers($paper,$responses,false);
            if($responses!==$scan->detected&&mb_strlen(trim($reason))<5)$this->fail('Enter a reason for correcting the detected responses.');
            $previous=$candidate->scores()->get()->keyBy('subject_id');$subject=$previous->get($paper->subject_id);
            if($candidate->absent||$subject?->is_absent)$this->fail('This subject is marked absent. Correct attendance in Scoring before verifying a sheet.');
            if($subject&&$subject->score!==null){
                abort_unless(ScholarshipPermissions::allows($user,'answer-replace',$candidate->campus_id),403);
                if(!$replace||mb_strlen(trim($reason))<5)$this->fail('Confirm replacement and enter a reason before replacing an existing score.');
            }
            $points=$scan->key->question_points??$this->defaultPoints($paper);
            $score=round(collect($responses)->map(fn($answer,$i)=>$answer!==null&&$answer===(string)$scan->key->answers[$i]?(float)$points[$i]:0)->sum(),2);
            $values=$previous->pluck('score','subject_id')->all();$values[$paper->subject_id]=$score;
            $absences=$previous->pluck('is_absent','subject_id')->all();
            // Use existing committee, publication, version, review and award safeguards.
            $this->workflow->saveScores($user,$exam,$candidate,$values,false,$version,false,$absences);
            // The existing manual form clears scan provenance. Preserve other
            // subjects' provenance when this action changes only one subject.
            foreach($previous as $id=>$old)if($id!==$paper->subject_id)ScholarshipScore::where('candidate_id',$candidate->id)->where('subject_id',$id)
                ->update($old->only(['entry_method','source_paper_id','source_reference']));
            ScholarshipScore::where('candidate_id',$candidate->id)->where('subject_id',$paper->subject_id)->update(['entry_method'=>'scan','source_paper_id'=>$paper->id,'source_reference'=>(string)$scan->id]);
            $scan->update(['responses'=>$responses,'score'=>$score,'status'=>'accepted','verified_by'=>$user->id,'verified_at'=>now(),'reason'=>$reason?:null]);
            $this->event($user,$exam,'answer-scan-verified',['scan_id'=>$scan->id,'key_id'=>$scan->key_id,'score'=>$score,'replaced'=>$subject?->score!==null,'reason'=>$reason,'responses'=>$responses]);
        });
    }
    public function recheck(User $user,ScholarshipAnswerScan $scan,string $reason): ScholarshipAnswerScan
    {
        $sheet=$scan->sheet;$paper=$sheet->paper;$exam=$this->paperExam($paper);$this->candidateAccess($user,$exam,$sheet->candidate,'answer-replace');
        if(mb_strlen(trim($reason))<5)$this->fail('Enter a reason for this recheck.');
        return DB::transaction(function()use($user,$scan,$sheet,$paper,$exam,$reason){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);$this->writable($exam);
            if(!$this->current($sheet)||!$exam->seats_locked_at)$this->fail('This sheet is outdated. Issue a current sheet.');
            $key=$paper->keys()->latest('revision')->firstOrFail();
            $new=$scan->replicate(['responses','score','status','verified_by','verified_at','reason','source_scan_id']);
            $new->fill(['key_id'=>$key->id,'status'=>'pending','candidate_version'=>$sheet->candidate->fresh()->version,
                'uploaded_by'=>$user->id,'reason'=>$reason,'source_scan_id'=>$scan->id])->save();
            $this->event($user,$exam,'answer-scan-rechecked',['source_scan_id'=>$scan->id,'scan_id'=>$new->id,'key_id'=>$key->id,'reason'=>$reason]);return $new;
        });
    }
    public static function assertCurrentScanKeys($candidate): void
    {
        foreach($candidate->scores()->where('entry_method','scan')->whereNotNull('source_reference')->get() as $score){
            $scan=ScholarshipAnswerScan::with('sheet.paper.latestKey')->find($score->source_reference);
            if(!$scan||$scan->status!=='accepted'||$scan->sheet->candidate_id!==$candidate->id||$scan->sheet->paper->subject_id!==$score->subject_id
                ||$scan->key_id!==$scan->sheet->paper->latestKey?->id)throw ValidationException::withMessages(['scores'=>'Recheck and verify scanned scores against the current approved answer key before reviewing or publishing.']);
        }
    }
}
