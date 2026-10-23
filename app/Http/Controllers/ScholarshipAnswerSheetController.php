<?php
namespace App\Http\Controllers;

use App\Models\{AcademicYear,ScholarshipExam,ScholarshipPaper,ScholarshipAnswerSheet,ScholarshipAnswerScan,ScholarshipTeacherKeySheet};
use App\Services\{ScholarshipAnswerSheets,ScholarshipWorkflow,ScholarshipTeacherKeys};
use App\Support\{ScholarshipPermissions,ScholarshipAnswerSheetLayout,ScholarshipExport};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\Rule;

class ScholarshipAnswerSheetController
{
    public function __construct(private ScholarshipAnswerSheets $sheets,private ScholarshipWorkflow $workflow){}
    public function index(Request $request,array $tabs)
    {
        abort_unless(ScholarshipPermissions::allows($request->user(),'tab-answer-sheets'),403);
        $input=$request->validate(['academic_year_id'=>['nullable','integer'],'exam_id'=>['nullable','integer'],'paper_id'=>['nullable','integer'],
            'center'=>['nullable','integer'],'room'=>['nullable','integer','min:1'],'scan_page'=>['nullable','integer','min:1','max:1000000']]);
        $exams=ScholarshipExam::with('academicYear')->whereHas('academicYear',fn($q)=>$q->regular())->latest('academic_year_id')->get()
            ->filter(fn($e)=>$this->workflow->canView($request->user(),$e));
        $years=$exams->pluck('academicYear')->unique('id')->values();
        $year=$input['academic_year_id']??($years->firstWhere('lifecycle_status','started')?->id??$years->first()?->id);
        $exam=isset($input['exam_id'])?$exams->firstWhere('id',(int)$input['exam_id']):$exams->firstWhere('academic_year_id',(int)$year);
        if(isset($input['exam_id']))abort_unless($exam&&(!isset($input['academic_year_id'])||$exam->academic_year_id===(int)$year),404);
        $permissions=collect(['answer-paper','answer-key','answer-key-approve','answer-key-print','answer-key-scan','answer-link','answer-unlink','answer-print','answer-scan','answer-verify','answer-replace','answer-delete'])
            ->mapWithKeys(fn($a)=>[$a=>ScholarshipPermissions::allows($request->user(),$a)])->all();
        $papers=$exam?ScholarshipPaper::with(['subject','latestKey'])->withCount(['sheets as linked_students_count'=>fn($q)=>$q->select(DB::raw('count(distinct candidate_id)'))])->withExists(['scores','keys as has_scan_links'=>fn($q)=>$q->whereHas('scans'),
            'sheets as has_sheet_scan_links'=>fn($q)=>$q->whereHas('scans'),
            'sheets as has_subject_scores'=>fn($q)=>$q->whereHas('candidate.scores',fn($q)=>$q->whereColumn('tb_scholarship_score.subject_id','tb_scholarship_paper.subject_id')->whereNull('source_paper_id')
                ->where(fn($q)=>$q->whereNotNull('score')->orWhere('is_absent',true)))])->whereHas('subject',fn($q)=>$q->where('exam_id',$exam->id))
            ->orderBy('grade_number')->orderBy('subject_id')->orderBy('paper_version')->get():collect();
        $paper=isset($input['paper_id'])?$papers->firstWhere('id',(int)$input['paper_id']):$papers->first();
        if(isset($input['paper_id']))abort_unless($paper,404);
        $candidates=$exam?$this->workflow->candidates($request->user(),$exam)->when($paper,fn($q)=>$q->where('grade_number',$paper->grade_number))
            ->orderBy('exam_center_id')->orderBy('room_number')->orderBy('room_seat')->get():collect();
        $centers=$candidates->map(fn($c)=>['id'=>$c->exam_center_id??0,'name'=>$c->exam_center_name?:'Main Exam Center'])->unique('id')->values();
        $rooms=$candidates->when(isset($input['center']),fn($rows)=>$rows->filter(fn($c)=>(int)$c->exam_center_id===(int)$input['center']))->pluck('room_number')->filter()->unique()->sort()->values();
        $visible=$candidates->filter(fn($c)=>(!isset($input['center'])||(int)$c->exam_center_id===(int)$input['center'])&&(!isset($input['room'])||$c->room_number===(int)$input['room']));
        $scans=ScholarshipAnswerScan::with(['sheet.candidate','sheet.paper.subject','key'])->whereHas('sheet',fn($q)=>$q->whereIn('candidate_id',$visible->pluck('id'))->when($paper,fn($q)=>$q->where('paper_id',$paper->id)))
            ->when(!$permissions['answer-verify'],fn($q)=>$q->where('uploaded_by',$request->user()->id))->latest('id')->paginate(15,['*'],'scan_page')->withQueryString();
        $paperData=$papers->map(fn($p)=>['id'=>$p->id,'subject_id'=>$p->subject_id,'subject'=>$p->subject->name,'max_score'=>$p->subject->max_score,'grade'=>$p->grade_number,'paper_version'=>$p->paper_version,
            'question_count'=>$p->question_count,'choices'=>$p->answer_choices,'points'=>$p->points_per_question,'version'=>$p->version,
            'linked_sheets'=>$p->linked_students_count,'linked'=>$p->linked_students_count>0||$p->scores_exists||$p->has_scan_links,'has_draft'=>$p->draft_answers!==null,
            'answers'=>($permissions['answer-key']||$permissions['answer-key-approve'])?$p->draft_answers:null,
            'question_points'=>($permissions['answer-key']||$permissions['answer-key-approve'])?($p->draft_points??$this->sheets->defaultPoints($p)):null,
            'approved_points'=>($permissions['answer-key']||$permissions['answer-key-approve'])?($p->latestKey?->question_points??($p->latestKey?$this->sheets->defaultPoints($p):null)):null,
            'approved'=>($permissions['answer-key']||$permissions['answer-key-approve'])?$p->latestKey?->answers:null,'revision'=>$p->latestKey?->revision]);
        $data=['papers'=>$paperData,'exam_id'=>$exam?->id,'locked'=>!$exam||$exam->published_at||$exam->isHistorical(),'permissions'=>$permissions,'candidates'=>$candidates->map(fn($c)=>['id'=>$c->id,'center'=>$c->exam_center_name?:'Main Exam Center','center_id'=>$c->exam_center_id??0,'room'=>$c->room_number,'seat'=>$c->room_seat,'name'=>$c->snapshot['name_en']??$c->candidate_code])->values(),
            'base'=>url('/students/western-scholarship/answer-sheets')];
        return view('western-scholarship.answer-sheets',compact('tabs','years','year','exam','papers','paper','permissions','centers','rooms','input','scans','data')+['tab'=>'answer-sheets']);
    }
    public function paper(Request $request,ScholarshipExam $exam)
    { $paper=$this->sheets->savePaper($request->user(),$exam,$request->all());return response()->json(['id'=>$paper->id]); }
    public function update(Request $request,ScholarshipPaper $paper)
    {
        $paper=$this->sheets->savePaper($request->user(),$paper->subject->exam,$request->all(),$paper->id);
        return response()->json(['id'=>$paper->id]);
    }
    public function delete(Request $request,ScholarshipPaper $paper)
    {
        $data=$request->validate(['version'=>['required','integer','min:1']]);
        $this->sheets->deletePaper($request->user(),$paper,$data['version']);return response()->json(['message'=>'Unlinked answer sheet deleted.']);
    }
    public function key(Request $request,ScholarshipPaper $paper)
    {
        $data=$request->validate(['answers'=>['required','array','max:100'],'points'=>['sometimes','required','array','max:100'],'version'=>['required','integer','min:1']]);
        $this->sheets->saveKey($request->user(),$paper,$data['answers'],$data['version'],$data['points']??null);return response()->json(['ok'=>true]);
    }
    public function approve(Request $request,ScholarshipPaper $paper)
    {
        $data=$request->validate(['version'=>['required','integer','min:1']]);$key=$this->sheets->approveKey($request->user(),$paper,$data['version']);return response()->json(['revision'=>$key->revision]);
    }
    public function link(Request $request,ScholarshipPaper $paper)
    {
        $data=$request->validate(['version'=>['required','integer','min:1']]);
        $sheets=$this->sheets->linkStudents($request->user(),$paper,$data['version']);
        $created=$sheets->filter(fn($sheet)=>$sheet->wasRecentlyCreated)->count();
        return response()->json(['count'=>$sheets->count(),'created'=>$created,
            'message'=>$sheets->count().' student(s) linked to Grade '.$paper->grade_number.' · '.$paper->subject->name.' · Paper '.$paper->paper_version.'. '.$created.' new link(s) added.']);
    }
    public function unlink(Request $request,ScholarshipPaper $paper)
    {
        $data=$request->validate(['version'=>['required','integer','min:1']]);
        $result=$this->sheets->unlinkStudents($request->user(),$paper,$data['version']);
        return response()->json($result+['message'=>$result['count'].' student link(s) removed. '.$result['remaining'].' link(s) remain. '
            .($result['remaining']?'All links must be removed before editing this shared paper’s setup.':'You can now edit the paper setup and answer key, then link students and print new sheets.')]);
    }
    public function issue(Request $request,ScholarshipPaper $paper)
    {
        $data=$request->validate(['scope'=>['required',Rule::in(['all','rooms','students'])],'center'=>['nullable','integer'],'rooms'=>['nullable','array','max:1000'],
            'rooms.*'=>['integer','min:1'],'candidates'=>['nullable','array','max:1000'],'candidates.*'=>['integer','min:1']]);
        $exam=$paper->subject->exam;$this->sheets->authorize($request->user(),$exam,'answer-print');
        $query=$this->workflow->candidates($request->user(),$exam)->where('grade_number',$paper->grade_number);
        if(isset($data['center']))$query->where('exam_center_id',$data['center']?:null);
        if($data['scope']==='rooms'){
            abort_unless(isset($data['center'])&&!empty($data['rooms']),422,'Select an exam center and at least one room.');
            $available=(clone $query)->distinct()->pluck('room_number')->all();
            abort_if(array_diff($data['rooms'],$available),422,'A selected room is outside this paper / exam center or your permitted scope.');
            $query->whereIn('room_number',$data['rooms']);
        }
        if($data['scope']==='students'){
            abort_unless(!empty($data['candidates']),422,'Select at least one student.');$query->whereIn('id',$data['candidates']);
            abort_unless((clone $query)->count()===count(array_unique($data['candidates'])),403);
        }
        $sheets=$this->sheets->issue($request->user(),$paper,$query->pluck('id')->all());
        $token=bin2hex(random_bytes(16));$request->session()->put('answer-sheet-print.'.$token,['ids'=>$sheets->pluck('id')->all(),'expires'=>now()->addMinutes(30)->timestamp]);
        return response()->json(['count'=>$sheets->count(),'url'=>route('western-scholarship.answer-sheets.print',['token'=>$token])]);
    }
    public function teacherKeyIssue(Request $request,ScholarshipPaper $paper)
    {
        $data=$request->validate(['version'=>['required','integer','min:1']]);
        $sheet=app(ScholarshipTeacherKeys::class)->issue($request->user(),$paper,$data['version']);
        $token=bin2hex(random_bytes(16));$request->session()->put('teacher-key-print.'.$token,['sheet_id'=>$sheet->id,'expires'=>now()->addMinutes(30)->timestamp]);
        return response()->json(['url'=>route('western-scholarship.answer-sheets.teacher-key-print',['token'=>$token])]);
    }
    public function teacherKeyPrint(Request $request,string $token)
    {
        $batch=$request->session()->get('teacher-key-print.'.$token);abort_unless($batch&&$batch['expires']>now()->timestamp,404,'The teacher key print session expired.');
        $sheet=ScholarshipTeacherKeySheet::with('paper.subject.exam.academicYear')->findOrFail($batch['sheet_id']);
        $this->sheets->authorize($request->user(),$sheet->paper->subject->exam,'answer-key-print');
        abort_unless(app(ScholarshipTeacherKeys::class)->current($sheet),422,'The paper setup changed. Print a new teacher key.');
        $pages=[app(ScholarshipAnswerSheetLayout::class)->teacherSvg($sheet,app(ScholarshipExport::class)->reportLogo(1))];
        return response()->view('western-scholarship.answer-sheet-report',['pages'=>$pages,'reportTitle'=>'WIS Scholarship Teacher Answer Key'])->header('Cache-Control','no-store, private');
    }
    public function teacherKeyScan(Request $request,ScholarshipPaper $paper)
    {
        $data=$request->validate(['version'=>['required','integer','min:1'],'image'=>['required','image','mimes:png,jpg,jpeg,webp','max:10240','dimensions:max_width=2400,max_height=3400']]);
        return response()->json(app(ScholarshipTeacherKeys::class)->detect($request->user(),$paper,$request->file('image'),$data['version']));
    }
    public function print(Request $request,string $token)
    {
        $batch=$request->session()->get('answer-sheet-print.'.$token);abort_unless($batch&&$batch['expires']>now()->timestamp,404,'The print session expired. Issue or reprint the sheets again.');
        $rows=ScholarshipAnswerSheet::with(['candidate','paper.subject.exam.academicYear'])->whereIn('id',$batch['ids'])->get()->keyBy('id');
        $logo=app(ScholarshipExport::class)->reportLogo(1);$pages=[];
        foreach($batch['ids'] as $id){$sheet=$rows->get($id);abort_unless($sheet,404);$exam=$sheet->paper->subject->exam;
            $this->sheets->candidateAccess($request->user(),$exam,$sheet->candidate,'answer-print');
            abort_unless($exam->seats_locked_at&&$this->sheets->current($sheet),422,'A sheet is outdated. Issue it again.');
            $pages[]=app(ScholarshipAnswerSheetLayout::class)->svg($sheet,$logo);
        }
        return response()->view('western-scholarship.answer-sheet-report',compact('pages'))->header('Cache-Control','no-store, private');
    }
    public function scan(Request $request,ScholarshipExam $exam)
    {
        $this->sheets->authorize($request->user(),$exam,'answer-scan');
        $data=$request->validate(['paper_id'=>['required','integer'],'image'=>['required','image','mimes:png,jpg,jpeg,webp','max:10240','dimensions:max_width=2400,max_height=3400'],
            'original'=>['required','image','mimes:png,jpg,jpeg,webp','max:10240','dimensions:max_width=6000,max_height=6000']]);
        $dimensions=getimagesize($data['original']->getRealPath());
        abort_unless($dimensions&&$dimensions[0]*$dimensions[1]<=24000000,422,'Choose a photo up to 24 megapixels.');
        $scan=$this->sheets->upload($request->user(),$exam,ScholarshipPaper::findOrFail($data['paper_id']),$data['image'],$data['original']);
        return response()->json(['id'=>$scan->id,'student_name'=>$scan->sheet->candidate->snapshot['name_en']??'',
            'candidate_code'=>$scan->sheet->candidate->candidate_code,'flagged_count'=>count($scan->flags),'status'=>$scan->status,
            'message'=>'Sheet uploaded. Staff verification is required before scores are saved.']);
    }
    private function authorizeScan(Request $request,ScholarshipAnswerScan $scan): void
    {
        $sheet=$scan->sheet;$this->sheets->candidateAccess($request->user(),$sheet->paper->subject->exam,$sheet->candidate,'answer-verify');
    }
    public function details(Request $request,ScholarshipAnswerScan $scan)
    {
        $this->authorizeScan($request,$scan);$sheet=$scan->sheet;$paper=$sheet->paper;
        return response()->json(['id'=>$scan->id,'status'=>$scan->status,'name'=>$sheet->candidate->snapshot['name_en']??'',
            'code'=>$sheet->candidate->candidate_code,'subject'=>$paper->subject->name,'choices'=>$paper->answer_choices,'responses'=>$scan->responses??$scan->detected,
            'flags'=>$scan->flags,'score'=>$scan->score,'key_revision'=>$scan->key->revision,'latest_revision'=>$paper->latestKey->revision,
            'candidate_version'=>$scan->candidate_version,'current_version'=>$sheet->candidate->version,
            'existing_score'=>$sheet->candidate->scores()->where('subject_id',$paper->subject_id)->value('score'),
            'image'=>route('western-scholarship.answer-sheets.image',$scan),'original'=>route('western-scholarship.answer-sheets.image',[$scan,'original'=>1])])->header('Cache-Control','no-store, private');
    }
    public function image(Request $request,ScholarshipAnswerScan $scan)
    {
        $this->authorizeScan($request,$scan);$path=$request->boolean('original')?$scan->original_path:$scan->image_path;
        abort_unless(Storage::disk('local')->exists($path),404);
        return response()->file(Storage::disk('local')->path($path),['Cache-Control'=>'no-store, private','X-Content-Type-Options'=>'nosniff']);
    }
    public function verify(Request $request,ScholarshipAnswerScan $scan)
    {
        $this->authorizeScan($request,$scan);$data=$request->validate(['responses'=>['required','array','max:100'],'version'=>['required','integer','min:1'],
            'replace'=>['required','boolean'],'reason'=>['nullable','string','max:1000'],'confirmed'=>['required','accepted']]);
        $this->sheets->verify($request->user(),$scan,$data['responses'],$data['version'],$data['replace'],$data['reason']??'');return response()->json(['message'=>'Verified scan saved as a draft score. Review it in Scoring before publication.']);
    }
    public function recheck(Request $request,ScholarshipAnswerScan $scan)
    {
        $this->authorizeScan($request,$scan);$data=$request->validate(['reason'=>['required','string','min:5','max:1000']]);
        $new=$this->sheets->recheck($request->user(),$scan,$data['reason']);return response()->json(['id'=>$new->id]);
    }
}
