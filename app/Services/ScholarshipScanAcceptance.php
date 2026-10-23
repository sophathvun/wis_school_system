<?php
namespace App\Services;

use App\Models\{ScholarshipAnswerScan,ScholarshipEvent,ScholarshipExam,ScholarshipPaper,User};
use App\Support\ScholarshipPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScholarshipScanAcceptance
{
    public function __construct(private ScholarshipAnswerSheets $sheets,private ScholarshipWorkflow $workflow){}

    private function snapshot(User $user,ScholarshipExam $exam,ScholarshipPaper $paper,array $filters): array
    {
        $this->sheets->authorize($user,$exam,'answer-accept-all');$this->sheets->authorize($user,$exam,'answer-verify');
        if($exam->published_at||$exam->isHistorical()||!$exam->seats_locked_at)
            throw ValidationException::withMessages(['scans'=>'Only an unpublished exam with confirmed seats can accept scans.']);
        $candidates=$this->workflow->candidates($user,$exam)->where('grade_number',$paper->grade_number)
            ->when(isset($filters['center']),fn($q)=>$q->where('exam_center_id',$filters['center']?:null))
            ->when(isset($filters['room']),fn($q)=>$q->where('room_number',$filters['room']))->with('scores')->orderBy('id')->get();
        $scans=ScholarshipAnswerScan::whereHas('sheet',fn($q)=>$q->where('paper_id',$paper->id)->whereIn('candidate_id',$candidates->modelKeys()))
            ->where('status','pending')->with('sheet.paper','sheet.candidate')->orderBy('id')->get();
        $counts=$scans->countBy('sheet.candidate_id');$eligible=collect();$skipped=[];$flagged=0;
        foreach($scans as $scan){
            $candidate=$candidates->find($scan->sheet->candidate_id);$subject=$candidate->scores->firstWhere('subject_id',$paper->subject_id);$reason=null;
            if(!ScholarshipPermissions::allows($user,'answer-accept-all',$candidate->campus_id)
                ||!ScholarshipPermissions::allows($user,'answer-verify',$candidate->campus_id)||!$this->workflow->canScore($user,$exam,$candidate))$reason='Missing permission or score-entry duty';
            elseif($counts[$candidate->id]>1)$reason='Multiple pending scans for the same student';
            elseif($candidate->absent||$subject?->is_absent)$reason='Marked absent';
            elseif($subject?->score!==null)$reason='Existing score (including zero)';
            elseif($candidate->reviewed_at)$reason='Scores already reviewed';
            elseif($candidate->version!==$scan->candidate_version||!$paper->latestKey||$paper->latestKey->id!==$scan->key_id||!$this->sheets->current($scan->sheet))$reason='Outdated scan, answer key or student sheet';
            elseif(!is_array($scan->detected)||!array_is_list($scan->detected)||count($scan->detected)!==$paper->question_count
                ||collect($scan->detected)->contains(fn($answer)=>$answer!==null&&!in_array($answer,$paper->answer_choices,true)))$reason='Invalid detected answers';
            if($reason){$skipped[$reason]=($skipped[$reason]??0)+1;continue;}
            $eligible->push($scan);if(count($scan->flags)||in_array(null,$scan->detected,true))$flagged++;
        }
        $center=isset($filters['center'])?($filters['center']?$exam->centers()->find($filters['center'])?->name:'Main Exam Center'):'All Exam Centers';
        $preview=['scope'=>$exam->academicYear->academic_year.' / Grade '.$paper->grade_number.' / '.$paper->subject->name.' / Paper '.$paper->paper_version.' / '.($center?:'Selected Exam Center').' / '.(isset($filters['room'])?'Room '.$filters['room']:'All Rooms'),
            'pending'=>$scans->count(),'eligible'=>$eligible->count(),'flagged'=>$flagged,'skipped'=>$skipped];
        $preview['fingerprint']=hash('sha256',json_encode([$user->id,$exam->version,$paper->version,$paper->latestKey?->id,$filters,$eligible->pluck('id')->all(),$skipped,
            $candidates->map(fn($c)=>[$c->id,$c->version,$c->absent,$c->reviewed_at,$c->scores->toArray()])->all(),
            $scans->map(fn($s)=>[$s->only(['id','status','key_id','candidate_version','detected','flags']),$s->sheet->only(['id','snapshot','fingerprint'])])->all()],JSON_THROW_ON_ERROR));
        return compact('preview','eligible');
    }

    public function preview(User $user,ScholarshipPaper $paper,array $filters=[]): array
    {
        return DB::transaction(function()use($user,$paper,$filters){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($paper->subject->exam_id);
            $paper=ScholarshipPaper::with('latestKey','subject')->lockForUpdate()->findOrFail($paper->id);
            return $this->snapshot($user,$exam,$paper,$filters)['preview'];
        });
    }

    public function accept(User $user,ScholarshipPaper $paper,array $filters,string $fingerprint,bool $acceptFlagged): array
    {
        return DB::transaction(function()use($user,$paper,$filters,$fingerprint,$acceptFlagged){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($paper->subject->exam_id);
            $paper=ScholarshipPaper::with('latestKey','subject')->lockForUpdate()->findOrFail($paper->id);
            ['preview'=>$preview,'eligible'=>$eligible]=$this->snapshot($user,$exam,$paper,$filters);
            if(!hash_equals($preview['fingerprint'],$fingerprint))throw ValidationException::withMessages(['scans'=>'Scan data changed. Open Accept All again to review the latest counts.']);
            if(!$preview['eligible'])throw ValidationException::withMessages(['scans'=>'No pending scans are ready to accept. Review the skipped scans individually.']);
            if($preview['flagged']&&!$acceptFlagged)throw ValidationException::withMessages(['scans'=>'Confirm accepting flagged answers before saving these scores.']);
            foreach($eligible as $scan)$this->sheets->verify($user,$scan,$scan->detected,$scan->candidate_version,false,'Accepted through Accept All; detected answers confirmed by staff.');
            unset($preview['fingerprint']);
            ScholarshipEvent::create(['exam_id'=>$exam->id,'user_id'=>$user->id,'action'=>'answer-scans-accepted-all','details'=>[
                'paper_id'=>$paper->id,'filters'=>$filters,'counts'=>$preview,'scan_ids'=>$eligible->pluck('id')->all(),'accepted_flagged'=>$acceptFlagged]]);
            return $preview;
        });
    }
}
