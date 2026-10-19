<?php

namespace App\Services;

use App\Models\{ScholarshipExam,ScholarshipInvigilator,ScholarshipInvigilatorDuty,ScholarshipEvent,SchoolInfo,Staff,User};
use App\Support\ScholarshipPermissions;
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Validation\ValidationException;

class ScholarshipInvigilators
{
    public function authorize(User $user,ScholarshipExam $exam,bool $write=true): void
    {
        abort_unless(app(ScholarshipWorkflow::class)->canView($user,$exam) && app(ScholarshipWorkflow::class)->central($user,$exam,'committee'),403);
        if($write && ($exam->published_at||$exam->isHistorical()))$this->fail('exam','Published and historical exams are read only.');
    }

    private function fail(string $field,string $message): never {throw ValidationException::withMessages([$field=>$message]);}

    public function staffOptions(User $user)
    {
        $campuses=ScholarshipPermissions::campuses($user)->pluck('id');
        return Staff::with(['position','primaryCampus'])->where('status',1)->where('employment_status','active')
            ->where(fn($q)=>$q->whereIn('primary_campus_id',$campuses)->orWhereHas('campuses',fn($c)=>$c->whereIn('tb_school_info.id',$campuses)->where('hrm_staff_campuses.status',1)))
            ->orderBy('name_en')->get()->map(fn($staff)=>[
                'id'=>$staff->id,'staff_code'=>$staff->staff_code,'name_en'=>$staff->name_en,'gender'=>$this->gender($staff->gender),
                'campus'=>$staff->primaryCampus?->campus_name_en?:$staff->primaryCampus?->campus_name_kh,
                'position'=>$staff->position?->name,'phone'=>$staff->phone,'email'=>$staff->email,
            ]);
    }

    private function gender(?string $value): string
    {return match(mb_strtolower(trim((string)$value))){'m','male'=>'Male','f','female'=>'Female','other'=>'Other',default=>''};}

    private function personData(array $input): array
    {
        foreach(['staff_code','name_en','campus','position','phone','email','organization','notes'] as $key){$value=trim((string)($input[$key]??''));$input[$key]=$value===''?null:$value;}
        $input['gender']=$this->gender($input['gender']??null);
        return Validator::make($input,[
            'staff_code'=>'nullable|string|max:80','name_en'=>'required|string|max:160','gender'=>'required|in:Male,Female,Other',
            'campus'=>'required|string|max:120','position'=>'required|string|max:160','phone'=>'required|string|max:50',
            'email'=>'nullable|email|max:160','organization'=>'nullable|string|max:160','notes'=>'nullable|string|max:2000',
        ],[],['name_en'=>'Staff Name English','staff_code'=>'Staff ID'])->validate();
    }

    public function save(User $user,ScholarshipExam $exam,array $input,?ScholarshipInvigilator $person=null): ScholarshipInvigilator
    {
        return DB::transaction(function()use($user,$exam,$input,$person){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);$this->authorize($user,$exam);
            if($person)abort_unless($person->exam_id===$exam->id,404);
            $source=$person?->source??($input['source']??'external');
            if(!$person && $source==='hrm'){
                $staff=$this->staffOptions($user)->firstWhere('id',(int)($input['staff_id']??0));
                if(!$staff)$this->fail('staff_id','Select an active HRM staff member in an authorized campus.');
                $input=array_replace($staff,array_filter($input,fn($value)=>$value!==null && $value!==''));
                $input['staff_code']=$staff['staff_code'];
            }
            if(!in_array($source,['hrm','external','import'],true))$this->fail('source','Choose HRM staff or external invigilator.');
            $values=$this->personData($input);$this->uniqueStaff($exam,$values['staff_code'],$person?->id);
            if(!$person){
                $sequence=$exam->invigilators()->pluck('code')->map(fn($code)=>preg_match('/^INV-(\d{1,9})$/i',$code,$m)?(int)$m[1]:0)->max()??0;
                $person=$exam->invigilators()->create($values+['code'=>'INV-'.str_pad((string)($sequence+1),3,'0',STR_PAD_LEFT),'source'=>$source,'created_by'=>$user->id]);
            }else $person->update($values);
            $this->changed($exam,$user,'invigilator-saved',['invigilator_id'=>$person->id]);return $person;
        });
    }

    private function uniqueStaff(ScholarshipExam $exam,?string $code,?int $except=null): void
    {
        if($code && $exam->invigilators()->whereRaw('LOWER(staff_code) = ?',[mb_strtolower($code)])->when($except,fn($q)=>$q->where('id','!=',$except))->exists())$this->fail('staff_code','This Staff ID is already in this academic year’s Invigilator List.');
    }

    public function remove(User $user,ScholarshipExam $exam,ScholarshipInvigilator $person): void
    {
        DB::transaction(function()use($user,$exam,$person){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);$this->authorize($user,$exam);abort_unless($person->exam_id===$exam->id,404);
            if($person->duties()->exists())$this->fail('invigilator','Remove this invigilator’s room assignments first.');
            $details=$person->toArray();$person->delete();$this->changed($exam,$user,'invigilator-removed',$details);
        });
    }

    public function rooms(ScholarshipExam $exam)
    {
        return $exam->candidates()->whereNotNull('room_number')->get(['exam_center_id','room_number','grade_number'])
            ->unique(fn($c)=>$c->exam_center_id.':'.$c->room_number)->map(function($c)use($exam){
                return ['center_id'=>$c->exam_center_id,'room'=>$c->room_number,'grade'=>$c->grade_number,
                    'subjects'=>app(ScholarshipWorkflow::class)->applicableSubjects($exam,$c->grade_number)->pluck('name')->values()->all(),
                    'schedules'=>app(ScholarshipWorkflow::class)->applicableSubjects($exam,$c->grade_number)->map(fn($s)=>app(\App\Support\ScholarshipSubjectSchedule::class)->forSubject($exam,$c->grade_number,$s->id))->values()->all()];
            })->values();
    }

    public function saveSessions(User $user,ScholarshipExam $exam,array $rows): void
    {
        DB::transaction(function()use($user,$exam,$rows){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);$this->authorize($user,$exam);
            Validator::make(['sessions'=>$rows],['sessions'=>'required|array|max:100','sessions.*.number'=>'required|integer|between:1,100|distinct',
                'sessions.*.exam_date'=>'required|date_format:Y-m-d','sessions.*.starts_at'=>'required|date_format:H:i','sessions.*.ends_at'=>'required|date_format:H:i'])->validate();
            foreach($rows as $i=>$row){
                if($row['ends_at']<=$row['starts_at'])$this->fail('sessions.'.$i.'.ends_at','End time must be after start time.');
                $exam->examSessions()->updateOrCreate(['number'=>$row['number']],collect($row)->only(['exam_date','starts_at','ends_at'])->all());
            }
            // Validate all existing duties against the proposed schedule before committing it.
            $this->validateAllDuties($exam);
            $this->changed($exam,$user,'invigilator-sessions-saved',['sessions'=>$rows]);
        });
    }

    private function validateDuty(ScholarshipExam $exam,int $personId,int $sessionId,int $center,int $room): void
    {
        if(!$exam->seats_locked_at)$this->fail('room_number','Confirm the seat plan before assigning invigilators to rooms.');
        $session=$exam->examSessions()->find($sessionId);if(!$session)$this->fail('session_id','Save the subject schedules in Scholarship Settings, then select an exam session for this academic year.');
        $roomData=$this->rooms($exam)->first(fn($r)=>$r['center_id']===$center && $r['room']===$room);
        if(!$roomData)$this->fail('room_number','Select a room belonging to this exam center’s confirmed seat plan.');
        if(!isset($roomData['subjects'][$session->number-1]))$this->fail('session_id','This room’s grade has no subject in the selected session.');
        $schedule=app(\App\Support\ScholarshipSubjectSchedule::class)->forSession($exam,$roomData['grade'],$session->number);
        if(!$schedule)$this->fail('session_id','Set this subject’s exam date, start time and end time in Scholarship Settings for Grade '.$roomData['grade'].'.');
        $duties=$exam->invigilatorDuties()->with('session')->where('invigilator_id',$personId)->get();
        foreach($duties as $duty){
            if($duty->session_id===$sessionId && $duty->exam_center_id===$center && $duty->room_number===$room)continue;
            $other=$duty->session;$otherRoom=$this->rooms($exam)->first(fn($r)=>$r['center_id']===$duty->exam_center_id&&$r['room']===$duty->room_number);
            $otherSchedule=$otherRoom?app(\App\Support\ScholarshipSubjectSchedule::class)->forSession($exam,$otherRoom['grade'],$other->number):null;
            if(!$otherSchedule)$this->fail('session_id','An existing room duty has no complete subject schedule. Complete its schedule in Scholarship Settings.');
            if($otherSchedule['exam_date']===$schedule['exam_date'] && $otherSchedule['starts_at']<$schedule['ends_at'] && $otherSchedule['ends_at']>$schedule['starts_at'])
                $this->fail('invigilator_id','This invigilator already has a room assignment during this session or an overlapping session.');
        }
    }

    public function linkedSessionIds(ScholarshipExam $exam,int $center,int $session): array
    {
        $link=$exam->sessionLinks()->where('exam_center_id',$center)->get()->first(fn($l)=>in_array($session,$l->session_ids,true));
        return $link?->session_ids??[$session];
    }

    public function linkSessions(User $user,ScholarshipExam $exam,array $input): void
    {
        DB::transaction(function()use($user,$exam,$input){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);$this->authorize($user,$exam);
            Validator::make($input,['version'=>'required|integer','exam_center_id'=>'required|integer','session_ids'=>'required|array|min:2|max:100',
                'session_ids.*'=>'required|integer|distinct','source_session_id'=>'required|integer','confirm'=>'accepted'])->validate();
            if($exam->version!==(int)$input['version'])$this->fail('version','This exam changed. Refresh before linking sessions.');
            $center=(int)$input['exam_center_id'];$ids=array_map('intval',$input['session_ids']);sort($ids);$source=(int)$input['source_session_id'];
            if(!$exam->seats_locked_at)$this->fail('exam_center_id','Confirm the seat plan before linking sessions.');
            if(!$exam->centers()->whereKey($center)->exists())$this->fail('exam_center_id','Choose an exam center from this academic year.');
            if(!in_array($source,$ids,true)||$exam->examSessions()->whereIn('id',$ids)->count()!==count($ids))$this->fail('session_ids','Choose saved sessions from this academic year and include the source session.');
            foreach($exam->sessionLinks()->where('exam_center_id',$center)->get() as $link)if(array_intersect($ids,$link->session_ids))$this->fail('session_ids','One of these sessions is already linked for this center. Unlink its group first.');
            $before=$exam->invigilatorDuties()->where('exam_center_id',$center)->whereIn('session_id',$ids)->get();
            $sourceDuties=$before->where('session_id',$source);
            $exam->invigilatorDuties()->where('exam_center_id',$center)->whereIn('session_id',$ids)->delete();
            foreach($sourceDuties as $duty)foreach($ids as $session){
                $this->validateDuty($exam,$duty->invigilator_id,$session,$center,$duty->room_number);
                $exam->invigilatorDuties()->create(['invigilator_id'=>$duty->invigilator_id,'session_id'=>$session,'exam_center_id'=>$center,'room_number'=>$duty->room_number]);
            }
            $link=$exam->sessionLinks()->create(['exam_center_id'=>$center,'session_ids'=>$ids]);
            $this->changed($exam,$user,'invigilator-sessions-linked',['link_id'=>$link->id,'source_session_id'=>$source,'previous_assignments'=>$before->toArray()]);
        });
    }

    public function unlinkSessions(User $user,ScholarshipExam $exam,\App\Models\ScholarshipSessionLink $link,int $version): void
    {
        DB::transaction(function()use($user,$exam,$link,$version){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);$this->authorize($user,$exam);abort_unless($link->exam_id===$exam->id,404);
            if($exam->version!==$version)$this->fail('version','This exam changed. Refresh before unlinking sessions.');
            $details=$link->toArray();$link->delete();$this->changed($exam,$user,'invigilator-sessions-unlinked',$details);
        });
    }

    /** All affected sessions are validated together; a failed move rolls back every duty. */
    private function putDuty(ScholarshipExam $exam,int $person,int $session,int $center,int $room,bool $replace,bool $import=false): array
    {
        return DB::transaction(function()use($exam,$person,$session,$center,$room,$replace,$import){
            $ids=$this->linkedSessionIds($exam,$center,$session);
            $existing=$exam->invigilatorDuties()->where('invigilator_id',$person)->whereIn('session_id',$ids)->get();
            foreach($existing as $duty){
                if($duty->exam_center_id!==$center && !($import&&$replace&&count($this->linkedSessionIds($exam,$duty->exam_center_id,$duty->session_id))===1))
                    $this->fail('room_number','This invigilator has an assignment at another center. Remove its linked assignment first, or use Replace Matching Records for an independent session.');
                if(!$replace&&($duty->room_number!==$room||$duty->exam_center_id!==$center))
                    $this->fail('room_number','This invigilator already has a different room assignment. Select Replace Matching Records to change it.');
            }
            $exam->invigilatorDuties()->whereIn('id',$existing->modelKeys())->delete();
            foreach($ids as $id){
                $this->validateDuty($exam,$person,$id,$center,$room);
                $exam->invigilatorDuties()->create(['invigilator_id'=>$person,'session_id'=>$id,'exam_center_id'=>$center,'room_number'=>$room]);
            }
            return $ids;
        });
    }

    public function assign(User $user,ScholarshipExam $exam,array $input): void
    {
        DB::transaction(function()use($user,$exam,$input){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);$this->authorize($user,$exam);
            Validator::make($input,['invigilator_id'=>'required|integer','exam_center_id'=>'required|integer','room_number'=>'required|integer|min:1','session_id'=>'nullable|integer','all_sessions'=>'nullable|boolean'])->validate();
            if(!$exam->invigilators()->whereKey($input['invigilator_id'])->exists())$this->fail('invigilator_id','Choose an invigilator from this academic year.');
            $room=$this->rooms($exam)->first(fn($r)=>$r['center_id']===(int)$input['exam_center_id'] && $r['room']===(int)$input['room_number']);
            $sessions=!empty($input['all_sessions'])?$exam->examSessions()->where('number','<=',count($room['subjects']??[]))->get():$exam->examSessions()->whereKey($input['session_id']??0)->get();
            if($sessions->isEmpty())$this->fail('session_id','Save the subject schedules in Scholarship Settings and select a session.');
            if(!empty($input['all_sessions']) && $sessions->count()!==count($room['subjects']??[]))$this->fail('session_id','Save every session for this room’s subjects before applying to all sessions.');
            foreach($sessions as $session){
                $this->putDuty($exam,(int)$input['invigilator_id'],$session->id,(int)$input['exam_center_id'],(int)$input['room_number'],empty($input['all_sessions']));
            }
            $this->changed($exam,$user,'invigilator-room-assigned',$input);
        });
    }

    public function removeDuty(User $user,ScholarshipExam $exam,ScholarshipInvigilatorDuty $duty): void
    {
        DB::transaction(function()use($user,$exam,$duty){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);$this->authorize($user,$exam);abort_unless($duty->exam_id===$exam->id,404);
            $ids=$this->linkedSessionIds($exam,$duty->exam_center_id,$duty->session_id);
            $query=$exam->invigilatorDuties()->where('exam_center_id',$duty->exam_center_id)->where('invigilator_id',$duty->invigilator_id)->whereIn('session_id',$ids);
            $details=['assignments'=>$query->get()->toArray()];$query->delete();$this->changed($exam,$user,'invigilator-room-removed',$details);
        });
    }

    private function validateAllDuties(ScholarshipExam $exam): void
    {foreach($exam->invigilatorDuties()->get() as $duty)$this->validateDuty($exam,$duty->invigilator_id,$duty->session_id,$duty->exam_center_id,$duty->room_number);}
    public function validateSchedules(ScholarshipExam $exam): void {$this->validateAllDuties($exam);}

    private function changed(ScholarshipExam $exam,User $user,string $action,array $details): void
    {
        $exam->increment('version');ScholarshipEvent::create(['exam_id'=>$exam->id,'user_id'=>$user->id,'action'=>$action,'details'=>$details]);
    }

    /** Preview runs the same validations in a rolled-back transaction; nothing is retained. */
    public function import(User $user,ScholarshipExam $exam,array $book,bool $replace,bool $preview,int $version): array
    {
        try{return DB::transaction(function()use($user,$exam,$book,$replace,$preview,$version){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);$this->authorize($user,$exam);
            if($exam->version!==$version)$this->fail('file','This exam changed. Validate the Excel file again.');
            $result=['created'=>0,'updated'=>0,'assignments'=>0,'errors'=>[],'people'=>[],'duties'=>[]];$seen=[];
            foreach($book['Invigilators'] as $index=>$row){
                try{
                    $code=trim((string)($row['invigilator_code']??''));
                    Validator::make(['code'=>$code],['code'=>'required|string|max:40|regex:/^[A-Za-z0-9_-]+$/'])->validate();
                    $key=mb_strtolower($code);if(isset($seen[$key]))$this->fail('code','Duplicate Invigilator Code in the file.');$seen[$key]=true;
                    $values=$this->personData(['staff_code'=>$row['staff_id']??null,'name_en'=>$row['staff_name_english']??null]+$row);
                    $person=$exam->invigilators()->whereRaw('LOWER(code) = ?',[$key])->first();
                    if($person && !$replace && collect($values)->contains(fn($value,$field)=>(string)$person->{$field}!==(string)$value))$this->fail('code','This invigilator exists with different information. Select Replace Matching Records to update it.');
                    $this->uniqueStaff($exam,$values['staff_code'],$person?->id);
                    if($person){$person->update($values);$result['updated']++;}
                    else{$person=$exam->invigilators()->create($values+['code'=>$code,'source'=>'import','created_by'=>$user->id]);$result['created']++;}
                    $result['people'][]=['code'=>$person->code,'name'=>$person->name_en,'phone'=>$person->phone];
                }catch(ValidationException $e){$result['errors'][]='Invigilators row '.($index+2).': '.implode(' ',array_merge(...array_values($e->errors())));}
            }
            $dutyKeys=[];$linkedTargets=[];
            foreach($book['Room Assignments'] as $index=>$row){
                try{
                    $code=mb_strtolower(trim((string)($row['invigilator_code']??'')));
                    $person=$exam->invigilators()->whereRaw('LOWER(code) = ?',[$code])->first();if(!$person)$this->fail('code','Unknown Invigilator Code.');
                    Validator::make($row,['exam_center'=>'required|string|max:160','session'=>'required|integer|between:1,100','room'=>'required|integer|min:1|max:100000'])->validate();
                    $center=$exam->centers()->whereRaw('LOWER(name) = ?',[mb_strtolower(trim($row['exam_center']))])->first();if(!$center)$this->fail('exam_center','Unknown exam center.');
                    $session=$exam->examSessions()->where('number',$row['session'])->first();if(!$session)$this->fail('session','Save this exam session’s date and times first.');
                    $key=$person->id.':'.$session->id;if(isset($dutyKeys[$key]))$this->fail('session','Duplicate invigilator/session assignment in the file.');$dutyKeys[$key]=true;
                    $affected=$this->linkedSessionIds($exam,$center->id,$session->id);
                    foreach($affected as $id){
                        $targetKey=$person->id.':'.$id;
                        if(isset($linkedTargets[$targetKey]) && $linkedTargets[$targetKey]!==[$center->id,(int)$row['room']])$this->fail('room','Linked sessions contain different rooms for this invigilator in the file. Use the same room for every linked session.');
                    }
                    $this->putDuty($exam,$person->id,$session->id,$center->id,(int)$row['room'],$replace,true);
                    foreach($affected as $id){
                        $targetKey=$person->id.':'.$id;$linkedTargets[$targetKey]=[$center->id,(int)$row['room']];
                        $number=$exam->examSessions()->findOrFail($id)->number;
                        $result['duties'][$targetKey]=['code'=>$person->code,'name'=>$person->name_en,'center'=>$center->name,'session'=>$number,'room'=>(int)$row['room']];
                    }
                    $result['assignments']=count($result['duties']);
                }catch(ValidationException $e){$result['errors'][]='Room Assignments row '.($index+2).': '.implode(' ',array_merge(...array_values($e->errors())));}
            }
            $result['duties']=array_values($result['duties']);
            if($preview)throw new ScholarshipInvigilatorPreview($result);
            if($result['errors'])$this->fail('file',implode(' ',$result['errors']));
            $this->changed($exam,$user,'invigilators-imported',collect($result)->only(['created','updated','assignments'])->all());return $result;
        });}catch(ScholarshipInvigilatorPreview $e){return $e->result;}
    }
}

class ScholarshipInvigilatorPreview extends \RuntimeException
{
    public function __construct(public readonly array $result){parent::__construct('Import preview');}
}
