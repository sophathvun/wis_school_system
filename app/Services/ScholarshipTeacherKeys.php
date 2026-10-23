<?php
namespace App\Services;

use App\Models\{User,ScholarshipExam,ScholarshipPaper,ScholarshipTeacherKeySheet,ScholarshipAnswerSheet,ScholarshipEvent};
use App\Support\ScholarshipAnswerSheetLayout;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScholarshipTeacherKeys
{
    public function __construct(private ScholarshipAnswerSheets $sheets,private ScholarshipAnswerSheetLayout $layout){}
    private function fail(string $message): never {throw ValidationException::withMessages(['teacher_key'=>$message]);}
    private function writable(ScholarshipExam $exam): void
    {if($exam->isHistorical()||$exam->published_at)$this->fail('Published or historical exams cannot import or issue teacher answer keys.');}
    private function snapshot(ScholarshipPaper $paper,int $layoutVersion=2): array
    {
        $paper->load('subject.exam.academicYear');$exam=$paper->subject->exam;
        return ($layoutVersion===2?['layout_version'=>2]:[])+['kind'=>'teacher-answer-key','exam_id'=>$exam->id,'year'=>$exam->academicYear->academic_year,
            'date'=>$exam->exam_date?->format('F j, Y')??'','grade'=>$paper->grade_number,'subject_id'=>$paper->subject_id,
            'subject'=>$paper->subject->name,'paper_version'=>$paper->paper_version,'question_count'=>$paper->question_count,
            'choices'=>$paper->answer_choices,'points'=>$paper->points_per_question,'max_score'=>$paper->subject->max_score];
    }
    public function current(ScholarshipTeacherKeySheet $sheet): bool
    {return hash_equals($sheet->fingerprint,hash('sha256',json_encode($this->snapshot($sheet->paper->fresh(),$sheet->snapshot['layout_version']??1))));}
    public function issue(User $user,ScholarshipPaper $paper,int $version): ScholarshipTeacherKeySheet
    {
        $exam=$paper->subject->exam;$this->sheets->authorize($user,$exam,'answer-key-print');
        return DB::transaction(function()use($user,$exam,$paper,$version){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);$this->writable($exam);
            $paper=ScholarshipPaper::lockForUpdate()->findOrFail($paper->id);
            if($paper->version!==$version)$this->fail('The paper changed. Refresh before printing a teacher answer key.');
            if(!$paper->question_count||!$paper->answer_choices)$this->fail('Set up the paper before printing a teacher answer key.');
            $snapshot=$this->snapshot($paper);$fingerprint=hash('sha256',json_encode($snapshot));
            do{$token=bin2hex(random_bytes(12));}while(ScholarshipAnswerSheet::where('token',$token)->exists());
            $sheet=ScholarshipTeacherKeySheet::firstOrCreate(['paper_id'=>$paper->id,'fingerprint'=>$fingerprint],
                ['token'=>$token,'snapshot'=>$snapshot,'printed_by'=>$user->id]);
            ScholarshipEvent::create(['exam_id'=>$exam->id,'user_id'=>$user->id,'action'=>'teacher-answer-key-issued',
                'details'=>['paper_id'=>$paper->id,'teacher_key_sheet_id'=>$sheet->id]]);
            return $sheet;
        });
    }
    /** Detect only: users review the returned answers before saving a draft. */
    public function detect(User $user,ScholarshipPaper $paper,UploadedFile $image,int $version): array
    {
        $exam=$paper->subject->exam;
        $this->sheets->authorize($user,$exam,'answer-key-scan');$this->sheets->authorize($user,$exam,'answer-key');
        $bytes=$image->get();$identity=$this->layout->read($bytes);
        return DB::transaction(function()use($user,$exam,$paper,$bytes,$identity,$version){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);$this->writable($exam);
            $paper=ScholarshipPaper::lockForUpdate()->findOrFail($paper->id);
            if($paper->version!==$version)$this->fail('The paper or draft key changed. Refresh before scanning a teacher answer key.');
            $sheet=ScholarshipTeacherKeySheet::where('token',$identity['token'])->where('paper_id',$paper->id)->first();
            if(!$sheet)$this->fail('This is not a teacher answer key for the selected grade, subject and paper. Check the sheet and selected paper.');
            if(!$this->current($sheet))$this->fail('This teacher answer key uses an old paper setup. Print a new teacher answer-key sheet.');
            $result=$this->layout->read($bytes,['question_count'=>$paper->question_count,'choices'=>$paper->answer_choices,'layout_version'=>$sheet->snapshot['layout_version']??1]);
            ScholarshipEvent::create(['exam_id'=>$exam->id,'user_id'=>$user->id,'action'=>'teacher-answer-key-scanned',
                'details'=>['paper_id'=>$paper->id,'teacher_key_sheet_id'=>$sheet->id,'image_hash'=>hash('sha256',$bytes),
                    'detected'=>$result['responses'],'flags'=>$result['flags'],'paper_version'=>$paper->version]]);
            return ['paper_id'=>$paper->id,'version'=>$paper->version,'answers'=>$result['responses'],'flags'=>$result['flags'],
                'teacher_key_sheet_id'=>$sheet->id];
        });
    }
}
