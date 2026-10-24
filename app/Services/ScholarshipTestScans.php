<?php

namespace App\Services;

use App\Models\{ScholarshipAnswerScan,ScholarshipEvent,ScholarshipExam,ScholarshipPaper,ScholarshipScore,User};
use Illuminate\Support\Facades\{DB,Log,Storage};
use Illuminate\Validation\ValidationException;

class ScholarshipTestScans
{
    public function __construct(private ScholarshipAnswerSheets $sheets,private ScholarshipWorkflow $workflow){}

    private function snapshot(User $user,ScholarshipExam $exam,ScholarshipPaper $paper,array $filters): array
    {
        $this->sheets->authorize($user,$exam,'answer-clear-scans');
        if($exam->published_at||$exam->isHistorical())throw ValidationException::withMessages(['scans'=>'Published or historical exams cannot be cleared. Reopen published results first.']);
        $candidates=$this->workflow->candidates($user,$exam)->where('grade_number',$paper->grade_number)
            ->when(isset($filters['center']),fn($q)=>$q->where('exam_center_id',$filters['center']?:null))
            ->when(isset($filters['room']),fn($q)=>$q->where('room_number',$filters['room']))->orderBy('id')->get();
        $scans=ScholarshipAnswerScan::whereHas('sheet',fn($q)=>$q->where('paper_id',$paper->id)->whereIn('candidate_id',$candidates->modelKeys()))
            ->with('sheet:id,candidate_id')->orderBy('id')->get();
        $currentScores=ScholarshipScore::whereIn('candidate_id',$candidates->modelKeys())->where('subject_id',$paper->subject_id)->orderBy('id')->get();
        $byId=$scans->keyBy('id');
        // Only remove a current score whose provenance points to an attempt being cleared.
        $scores=$currentScores->filter(function($score)use($paper,$byId){
            $scan=$byId->get($score->source_reference);
            return $score->entry_method==='scan'&&$score->source_paper_id===$paper->id&&$scan&&$scan->status==='accepted'
                &&(int)$scan->sheet->candidate_id===(int)$score->candidate_id;
        });
        $affected=$candidates->whereIn('id',$scores->pluck('candidate_id'));
        $awards=$exam->awards()->whereIn('grade_number',$affected->pluck('grade_number')->unique())->orderBy('id')->get();
        $center=isset($filters['center'])?($candidates->first()?->exam_center_name?:($filters['center']?$exam->centers()->find($filters['center'])?->name:'Main Exam Center')):'All Exam Centers';
        $scope=($exam->academicYear->academic_year).' / '.$exam->name.' / Grade '.$paper->grade_number.' / '.$paper->subject->name.' / Paper '.$paper->paper_version
            .' / '.($center?:'Selected Exam Center').' / '.(isset($filters['room'])?'Room '.$filters['room']:'All Rooms');
        $preview=['scope'=>$scope,'scans'=>$scans->count(),'pending'=>$scans->where('status','pending')->count(),
            'accepted'=>$scans->where('status','accepted')->count(),'students'=>$scans->pluck('sheet.candidate_id')->unique()->count(),
            'scores'=>$scores->count(),'manual_scores_kept'=>$currentScores->where('entry_method','manual')->whereNotNull('score')->count(),
            'reviews_reset'=>$affected->whereNotNull('reviewed_at')->count(),'awards_reset'=>$awards->count()];
        // The confirmation is stale if uploads, verification, scores, seating, keys or awards changed.
        $preview['fingerprint']=hash('sha256',json_encode([$user->id,$exam->id,$exam->version,$paper->toArray(),$filters,
            $candidates->map->only(['id','version','exam_center_id','room_number'])->all(),$scans->toArray(),$currentScores->toArray(),$awards->toArray()],JSON_THROW_ON_ERROR));
        return compact('preview','scans','scores','affected','awards');
    }

    public function preview(User $user,ScholarshipPaper $paper,array $filters=[]): array
    {
        return DB::transaction(function()use($user,$paper,$filters){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($paper->subject->exam_id);
            $paper=ScholarshipPaper::lockForUpdate()->findOrFail($paper->id);
            return $this->snapshot($user,$exam,$paper,$filters)['preview'];
        });
    }

    public function clear(User $user,ScholarshipPaper $paper,array $filters,string $fingerprint): array
    {
        [$result,$paths]=DB::transaction(function()use($user,$paper,$filters,$fingerprint){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($paper->subject->exam_id);
            $paper=ScholarshipPaper::lockForUpdate()->findOrFail($paper->id);
            ['preview'=>$preview,'scans'=>$scans,'scores'=>$scores,'affected'=>$affected,'awards'=>$awards]=$this->snapshot($user,$exam,$paper,$filters);
            if(!hash_equals($preview['fingerprint'],$fingerprint))throw ValidationException::withMessages(['scans'=>'Scan data changed after the preview. Open Clear Test Scans again to check the updated counts.']);
            if($scans->isEmpty())throw ValidationException::withMessages(['scans'=>'There are no test scans for these filters.']);
            $paths=$scans->flatMap(fn($scan)=>[$scan->image_path,$scan->original_path])->filter()->unique()->values()->all();
            ScholarshipScore::whereIn('id',$scores->modelKeys())->delete();
            foreach($affected as $candidate)$candidate->forceFill(['absent'=>false,'reviewed_by'=>null,'reviewed_at'=>null,
                'result_total'=>null,'result_percentage'=>null,'result_rank'=>null,'award_percent'=>null,'version'=>$candidate->version+1])->save();
            $exam->awards()->whereIn('id',$awards->modelKeys())->delete();
            ScholarshipAnswerScan::whereIn('id',$scans->modelKeys())->delete();
            $exam->increment('version');
            unset($preview['fingerprint']);
            ScholarshipEvent::create(['exam_id'=>$exam->id,'user_id'=>$user->id,'action'=>'answer-test-scans-cleared','details'=>[
                'paper_id'=>$paper->id,'filters'=>$filters,'counts'=>$preview,
                'scans'=>$scans->map->only(['id','sheet_id','key_id','source_scan_id','status','score','uploaded_by','verified_by','verified_at'])->values()->all(),
                'scores'=>$scores->values()->toArray(),'awards'=>$awards->toArray(),
            ]]);
            return [$preview,$paths];
        });
        // Rechecks share images. Keep any file still referenced by another attempt.
        $failed=0;
        foreach($paths as $path){
            if(ScholarshipAnswerScan::where('image_path',$path)->orWhere('original_path',$path)->exists())continue;
            try{if(!Storage::disk('local')->delete($path)){$failed++;Log::warning('Test scan image cleanup failed',['paper_id'=>$paper->id,'path'=>$path]);}}
            catch(\Throwable $e){$failed++;Log::warning('Test scan image cleanup failed',['paper_id'=>$paper->id,'path'=>$path,'error'=>$e->getMessage()]);}
        }
        return $result+['image_cleanup_pending'=>$failed];
    }
}
