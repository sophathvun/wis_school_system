<?php
namespace App\Http\Controllers;

use App\Models\{ScholarshipExam,ScholarshipInvigilator,ScholarshipInvigilatorDuty};
use App\Services\ScholarshipInvigilators;
use App\Support\{ScholarshipInvigilatorWorkbook,ScholarshipPermissions};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScholarshipInvigilatorController
{
    public function __construct(private readonly ScholarshipInvigilators $service){}
    private function done(Request $request,ScholarshipExam $exam,string $message)
    {
        $url=route('western-scholarship.index',['tab'=>'exam-committee','exam_id'=>$exam->id]);
        $request->session()->flash('success',$message);
        return $request->expectsJson()?response()->json(['message'=>$message,'redirect'=>$url]):redirect($url)->with('success',$message);
    }
    public function store(Request $request,ScholarshipExam $exam)
    {$this->service->save($request->user(),$exam,$request->all());return $this->done($request,$exam,'Invigilator saved successfully.');}
    public function update(Request $request,ScholarshipExam $exam,ScholarshipInvigilator $person)
    {$this->service->save($request->user(),$exam,$request->all(),$person);return $this->done($request,$exam,'Invigilator information updated.');}
    public function remove(Request $request,ScholarshipExam $exam,ScholarshipInvigilator $person)
    {$this->service->remove($request->user(),$exam,$person);return $this->done($request,$exam,'Invigilator removed.');}
    public function sessions(Request $request,ScholarshipExam $exam)
    {$this->service->authorize($request->user(),$exam);throw ValidationException::withMessages(['sessions'=>'Manage exam dates and times by subject in Scholarship Settings.']);}
    public function link(Request $request,ScholarshipExam $exam)
    {$this->service->linkSessions($request->user(),$exam,$request->all());return $this->done($request,$exam,'Sessions linked. Room assignments now update together.');}
    public function unlink(Request $request,ScholarshipExam $exam,\App\Models\ScholarshipSessionLink $link)
    {$data=$request->validate(['version'=>'required|integer']);$this->service->unlinkSessions($request->user(),$exam,$link,(int)$data['version']);return $this->done($request,$exam,'Sessions unlinked. Existing assignments have been kept.');}
    public function assign(Request $request,ScholarshipExam $exam)
    {$this->service->assign($request->user(),$exam,$request->all());return $this->done($request,$exam,'Invigilator room assignment saved.');}
    public function unassign(Request $request,ScholarshipExam $exam,ScholarshipInvigilatorDuty $duty)
    {$this->service->removeDuty($request->user(),$exam,$duty);return $this->done($request,$exam,'Room assignment removed.');}

    public function download(Request $request,ScholarshipExam $exam,ScholarshipInvigilatorWorkbook $workbook)
    {
        $this->service->authorize($request->user(),$exam,false);$sample=$request->boolean('sample');
        if(!$sample)abort_unless(app(\App\Services\ScholarshipWorkflow::class)->central($request->user(),$exam,'export-committee'),403);
        if($sample){
            $book=['Invigilators'=>[
                ['invigilator_code'=>'INV-001','staff_id'=>'SAMPLE-001','staff_name_english'=>'SAMPLE STAFF A','gender'=>'Male','campus'=>'BCH','position'=>'Teacher','phone'=>'012345678','organization'=>'WIS'],
                ['invigilator_code'=>'INV-002','staff_id'=>'','staff_name_english'=>'SAMPLE EXTERNAL B','gender'=>'Female','campus'=>'External','position'=>'Examiner','phone'=>'098765432','organization'=>'External School'],
            ],'Room Assignments'=>[]];
            $center=$exam->centers()->first()?->name??'Main Exam Center';
            foreach([1,2] as $session)$book['Room Assignments'][]=['invigilator_code'=>$session===1?'INV-001':'INV-002','exam_center'=>$center,'session'=>$session,'room'=>1];
        }else{
            $book=['Invigilators'=>$exam->invigilators()->get()->map(fn($p)=>['invigilator_code'=>$p->code,'staff_id'=>$p->staff_code,'staff_name_english'=>$p->name_en]+$p->only(['gender','campus','position','phone','email','organization','notes']))->all(),
                'Room Assignments'=>$exam->invigilatorDuties()->with(['invigilator','session','examCenter'])->get()->sortBy(fn($d)=>sprintf('%010d:%05d:%05d:%s',$d->exam_center_id,$d->session->number,$d->room_number,$d->invigilator->code))->map(fn($d)=>[
                    'invigilator_code'=>$d->invigilator->code,'exam_center'=>$d->examCenter->name,'session'=>$d->session->number,'room'=>$d->room_number])->values()->all()];
        }
        return response($workbook->write($book))->header('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition','attachment; filename="scholarship-invigilators'.($sample?'-sample':'').'.xlsx"')->header('Cache-Control','private, no-store');
    }

    public function preview(Request $request,ScholarshipExam $exam,ScholarshipInvigilatorWorkbook $workbook)
    {
        $this->service->authorize($request->user(),$exam);
        $request->validate(['file'=>'required|file|extensions:xlsx|max:5120','replace'=>'nullable|boolean']);
        $book=$workbook->read($request->file('file')->getRealPath());$version=$exam->fresh()->version;
        $result=$this->service->import($request->user(),$exam,$book,$request->boolean('replace'),true,$version);$token=null;
        $request->session()->forget('scholarship_invigilator_preview');
        if(!$result['errors']){
            $token=Str::random(64);$request->session()->put('scholarship_invigilator_preview',[
                'token'=>$token,'user_id'=>$request->user()->id,'exam_id'=>$exam->id,'version'=>$version,'book'=>$book,'replace'=>$request->boolean('replace'),'expires'=>now()->addMinutes(20)->timestamp,
            ]);
        }
        return response()->json(['token'=>$token,'created'=>$result['created'],'updated'=>$result['updated'],'assignments'=>$result['assignments'],
            'error_count'=>count($result['errors']),'errors'=>array_slice($result['errors'],0,100),'people'=>array_slice($result['people'],0,100),'duties'=>array_slice($result['duties'],0,100)]);
    }

    public function import(Request $request,ScholarshipExam $exam)
    {
        $data=$request->validate(['token'=>'required|string|size:64','acknowledge'=>'accepted']);$plan=$request->session()->get('scholarship_invigilator_preview');
        if(!$plan||$plan['exam_id']!==$exam->id||$plan['user_id']!==$request->user()->id||$plan['expires']<now()->timestamp||!hash_equals($plan['token'],$data['token']))
            throw ValidationException::withMessages(['file'=>'This preview expired or belongs to another exam. Validate the Excel file again.']);
        $result=$this->service->import($request->user(),$exam,$plan['book'],$plan['replace'],false,$plan['version']);
        $request->session()->forget('scholarship_invigilator_preview');
        return $this->done($request,$exam,$result['created'].' invigilator(s) added, '.$result['updated'].' updated and '.$result['assignments'].' assignment(s) saved.');
    }
}
