<?php

namespace App\Services;

use App\Models\{ScholarshipAnswerScan,ScholarshipAnswerSheet,ScholarshipEvent,ScholarshipExam,ScholarshipPaper,ScholarshipScore,User};
use App\Support\ScholarshipPermissions;
use Illuminate\Support\Facades\{DB,Log,Storage};
use Illuminate\Validation\ValidationException;

class ScholarshipReseating
{
    public function __construct(private ScholarshipWorkflow $workflow){}

    public function authorize(User $user,ScholarshipExam $exam): void
    {
        abort_unless(ScholarshipPermissions::allows($user,'reset-for-reseating')&&$this->workflow->central($user,$exam,'reset-for-reseating')
            &&$this->workflow->canView($user,$exam),403);
    }

    private function snapshot(User $user,ScholarshipExam $exam): array
    {
        $this->authorize($user,$exam);
        if($exam->published_at||$exam->isHistorical())throw ValidationException::withMessages(['reset'=>'Published and locked results or historical exams cannot be reset for reseating.']);
        $candidates=$exam->candidates()->orderBy('id')->get();
        $papers=ScholarshipPaper::whereHas('subject',fn($q)=>$q->where('exam_id',$exam->id))->orderBy('id')->get();
        $sheets=ScholarshipAnswerSheet::whereIn('paper_id',$papers->modelKeys())->orderBy('id')->get();
        $scans=ScholarshipAnswerScan::whereIn('sheet_id',$sheets->modelKeys())->orderBy('id')->get();
        $scores=ScholarshipScore::whereIn('candidate_id',$candidates->modelKeys())->orderBy('id')->get();
        $awards=$exam->awards()->orderBy('id')->get();
        $committee=$exam->committee()->whereNotNull('room_number')->orderBy('id')->get();
        $duties=$exam->invigilatorDuties()->orderBy('id')->get();
        $preview=['scope'=>$exam->examinationTitle().' — Academic Year: '.$exam->academicYear->academic_year,'students'=>$candidates->count(),
            'scores'=>$scores->count(),'manual_scores'=>$scores->where('entry_method','manual')->count(),'scans'=>$scans->count(),
            'links'=>$sheets->count(),'reviews'=>$candidates->whereNotNull('reviewed_at')->count(),'awards'=>$awards->count(),
            'seats'=>$candidates->whereNotNull('room_number')->count(),'room_duties'=>$committee->count()+$duties->count(),
            'papers_kept'=>$papers->count()];
        $preview['fingerprint']=hash('sha256',json_encode([$user->id,$exam->toArray(),$candidates->toArray(),$papers->toArray(),
            $sheets->toArray(),$scans->toArray(),$scores->toArray(),$awards->toArray(),$committee->toArray(),$duties->toArray()],JSON_THROW_ON_ERROR));
        return compact('preview','candidates','papers','sheets','scans','scores','awards','committee','duties');
    }

    public function preview(User $user,ScholarshipExam $exam): array
    {
        return DB::transaction(fn()=>$this->snapshot($user,ScholarshipExam::lockForUpdate()->findOrFail($exam->id))['preview']);
    }

    public function reset(User $user,ScholarshipExam $exam,string $fingerprint,string $reason): array
    {
        if(mb_strlen(trim($reason))<5||mb_strlen($reason)>1000)throw ValidationException::withMessages(['reason'=>'Enter a reset reason between 5 and 1000 characters.']);
        [$result,$paths]=DB::transaction(function()use($user,$exam,$fingerprint,$reason){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);
            ['preview'=>$preview,'candidates'=>$candidates,'papers'=>$papers,'sheets'=>$sheets,'scans'=>$scans,
                'scores'=>$scores,'awards'=>$awards,'committee'=>$committee,'duties'=>$duties]=$this->snapshot($user,$exam);
            if(!hash_equals($preview['fingerprint'],$fingerprint))throw ValidationException::withMessages(['reset'=>'The exam changed after the preview. Open Reset Exam for Reseating again to check the updated counts.']);
            if(!$exam->seats_locked_at&&!$preview['scores']&&!$preview['scans']&&!$preview['links']&&!$preview['reviews']&&!$preview['awards']&&!$preview['seats']&&!$preview['room_duties'])
                throw ValidationException::withMessages(['reset'=>'This exam is already ready for new ranks and seating.']);
            $paths=$scans->flatMap(fn($s)=>[$s->image_path,$s->original_path])->filter()->unique()->values()->all();
            // Delete attempts before their issued-sheet links to satisfy restrictive foreign keys.
            ScholarshipAnswerScan::whereIn('id',$scans->modelKeys())->delete();
            ScholarshipScore::whereIn('id',$scores->modelKeys())->delete();
            ScholarshipAnswerSheet::whereIn('id',$sheets->modelKeys())->delete();
            $exam->awards()->delete();$exam->committee()->whereIn('id',$committee->modelKeys())->delete();$exam->invigilatorDuties()->delete();
            $exam->candidates()->update(['absent'=>false,'scored_by'=>null,'reviewed_by'=>null,'reviewed_at'=>null,
                'result_total'=>null,'result_percentage'=>null,'result_rank'=>null,'award_percent'=>null,
                'room_number'=>null,'room_seat'=>null,'grade_exam_number'=>null,'candidate_code'=>null,
                'exam_center_id'=>null,'exam_center_name'=>null,'seat_scope'=>0,'version'=>DB::raw('version + 1')]);
            ScholarshipPaper::whereIn('id',$papers->modelKeys())->increment('version');
            $exam->forceFill(['seats_locked_at'=>null,'version'=>$exam->version+1])->save();
            unset($preview['fingerprint']);
            ScholarshipEvent::create(['exam_id'=>$exam->id,'user_id'=>$user->id,'action'=>'exam-reset-for-reseating','details'=>[
                'reason'=>trim($reason),'counts'=>$preview,
                'candidate_before'=>$candidates->map->only(['id','room_number','room_seat','candidate_code','exam_center_id','absent','reviewed_by','reviewed_at','version'])->all(),
                'scores'=>$scores->toArray(),'awards'=>$awards->toArray(),'room_committee'=>$committee->toArray(),'invigilator_duties'=>$duties->toArray(),
                'scan_ids'=>$scans->modelKeys(),'sheet_ids'=>$sheets->modelKeys(),
            ]]);
            return [$preview,$paths];
        });
        $failed=0;
        foreach($paths as $path){
            // Rechecked attempts may share a file. Never remove an image still used elsewhere.
            if(ScholarshipAnswerScan::where('image_path',$path)->orWhere('original_path',$path)->exists())continue;
            try{if(!Storage::disk('local')->delete($path)){$failed++;Log::warning('Reseating scan image cleanup failed',['exam_id'=>$exam->id,'path'=>$path]);}}
            catch(\Throwable $error){$failed++;Log::warning('Reseating scan image cleanup failed',['exam_id'=>$exam->id,'path'=>$path,'error'=>$error->getMessage()]);}
        }
        return $result+['image_cleanup_pending'=>$failed];
    }
}
