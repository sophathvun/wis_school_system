<?php

namespace App\Http\Controllers;

use App\Models\{WmiRegistration,WmiYear,WmiSchool,WmiExamCenter,StudentEnrollment,SchoolInfo,AcademicYear,Grade};
use App\Support\G9ExamSpreadsheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule,ValidationException};

class WmiRegistrationController
{
    public const GRADES=['K','1','2','3','4','5','6','7','8','9','10','11','12'];

    public function query(Request $request)
    {
        $request->validate(['wmi_year_id'=>['nullable','integer','exists:wmi_years,id'],'wmi_exam_center_id'=>['nullable','integer','exists:wmi_exam_centers,id'],
            'q'=>['nullable','string','max:100'],'per_page'=>['nullable','in:all,15,50,75,100'],
            'campus_study'=>['nullable','string','max:255'],'campus_registered'=>['nullable','string','max:255'],
            'grade_contest'=>['nullable',Rule::in(self::GRADES)],'registered_by'=>['nullable','integer','min:1'],
            'registered_date'=>['nullable','date_format:Y-m-d']]);
        return WmiRegistration::with(['year','center','school','registeredBy'])
            ->when($request->filled('wmi_year_id'),fn($q)=>$q->where('wmi_year_id',$request->integer('wmi_year_id')))
            ->when($request->filled('wmi_exam_center_id'),fn($q)=>$q->where('wmi_exam_center_id',$request->integer('wmi_exam_center_id')))
            ->when($request->filled('campus_study'),fn($q)=>$q->where('campus_study',$request->query('campus_study')))
            ->when($request->filled('campus_registered'),fn($q)=>$q->where('campus_registered',$request->query('campus_registered')))
            ->when($request->filled('grade_contest'),fn($q)=>$q->where('grade_contest',$request->query('grade_contest')))
            ->when($request->filled('registered_by'),fn($q)=>$q->where('registered_by',$request->integer('registered_by')))
            ->when($request->filled('registered_date'),fn($q)=>$q->whereDate('created_at',$request->query('registered_date')))
            ->when($request->filled('q'),fn($q)=>$q->where(function($q) use ($request){$term='%'.$request->query('q').'%';$q->where('student_name','like',$term)->orWhere('student_code','like',$term)->orWhere('wmi_id_number','like',$term)->orWhere('reference','like',$term);}))
            ->orderBy('student_name')->orderBy('id');
    }

    public function pageData(Request $request): array
    {
        $query=$this->query($request);$size=(string)($request->query('per_page')??'15');
        $summary=(clone $query)->reorder()->selectRaw('COUNT(*) as total, COUNT(wmi_id_number) as assigned_ids, COUNT(seat_no) as seated')->first();
        return ['registrations'=>$query->paginate($size==='all'?max(1,(clone $query)->count()):(int)$size,['*'],'page',$size==='all'?1:null)->withQueryString(),
            'registrationSummary'=>$summary,'registrationYears'=>WmiYear::orderByDesc('year')->get(),
            'registrationCenters'=>WmiExamCenter::orderBy('name')->get(),'registrationSchools'=>WmiSchool::orderBy('name_en')->get(),
            'registrationCampuses'=>SchoolInfo::where('status',1)->orderBy('campus_name_en')->get(),
            'registrationGrades'=>Grade::orderBy('grade')->get(),
            'studyCampusOptions'=>WmiRegistration::select('campus_study')->distinct()->orderBy('campus_study')->pluck('campus_study'),
            'registrarCampusOptions'=>WmiRegistration::select('campus_registered')->distinct()->orderBy('campus_registered')->pluck('campus_registered'),
            'registrationUsers'=>\App\Models\User::whereIn('id',WmiRegistration::select('registered_by')->whereNotNull('registered_by'))->orderBy('name')->get(['id','name']),
            'registrationAcademicYears'=>AcademicYear::where(fn($q)=>$q->whereNull('period_type')->orWhere('period_type','regular'))->orderByDesc('academic_year')->get()];
    }

    public static function enrollmentData(StudentEnrollment $enrollment): array
    {
        $student=$enrollment->student;
        return ['enrollment_id'=>$enrollment->id,'student_code'=>$student->student_id,'student_name'=>$student->full_name_en,
            'student_name_kh'=>$student->full_name_kh,'date_of_birth'=>$student->date_of_birth?->format('Y-m-d'),
            'gender'=>in_array(strtolower((string)$student->gender),['m','male'],true)?'M':(in_array(strtolower((string)$student->gender),['f','female'],true)?'F':''),
            'current_grade'=>$enrollment->schoolClass?->class_name?:($enrollment->grade?->grade??''),
            'campus_study'=>$enrollment->campus?->campus_name_en??'', 'phone_number'=>$student->home_phone,'email_address'=>$student->email,
            'academic_year'=>$enrollment->academicYear?->academic_year];
    }

    public function enrollments(Request $request)
    {
        $data=$request->validate(['q'=>['nullable','string','max:100'],'academic_year_id'=>['required','integer','exists:tb_academic_year,id'],
            'campus_id'=>['nullable','integer','exists:tb_school_info,id'],'grade_id'=>['nullable','integer','exists:tb_grade,id']]);
        $records=StudentEnrollment::with(['student','campus','grade','schoolClass','academicYear'])->where('academic_year_id',$data['academic_year_id'])
            ->when($request->filled('campus_id'),fn($q)=>$q->where('campus_id',$data['campus_id']))
            ->when($request->filled('grade_id'),fn($q)=>$q->where('grade_id',$data['grade_id']))
            ->whereHas('student',fn($q)=>$q->when($request->filled('q'),fn($q)=>$q->where(fn($q)=>$q->where('student_id','like','%'.$data['q'].'%')->orWhere('full_name_en','like','%'.$data['q'].'%')->orWhere('full_name_kh','like','%'.$data['q'].'%'))))
            ->orderBy('student_id')->orderBy('id')->limit(25)->get();
        return response()->json(['data'=>$records->map(fn($record)=>self::enrollmentData($record))]);
    }

    private function validated(Request $request,?WmiRegistration $record=null): array
    {
        $data=$request->validate([
            'wmi_year_id'=>['required','integer','exists:wmi_years,id'],
            'wmi_exam_center_id'=>['required','integer',Rule::exists('wmi_exam_centers','id')->where('wmi_year_id',$request->input('wmi_year_id'))],
            'student_source'=>['required','in:western,other'],'enrollment_id'=>['nullable','required_if:student_source,western','integer','exists:tb_student_enrollment,id'],
            'wmi_school_id'=>['nullable','required_if:student_source,other','integer','exists:wmi_schools,id'],
            'student_name'=>['nullable','required_if:student_source,other','string','max:255'],'student_name_kh'=>['nullable','string','max:255'],
            'date_of_birth'=>['nullable','date_format:Y-m-d','before_or_equal:today'],'gender'=>['nullable','required_if:student_source,other','in:M,F'],
            'current_grade'=>['nullable','required_if:student_source,other','string','max:100'],'grade_contest'=>['required',Rule::in(self::GRADES)],
            'registration_channel'=>['required','in:campus,online'],'registrar_campus_id'=>['nullable','required_if:registration_channel,campus','integer','exists:tb_school_info,id'],
            'phone_number'=>['nullable','string','max:40'],'telegram_number'=>['nullable','string','max:40'],'email_address'=>['nullable','email','max:255'],
            'room_no'=>['nullable','required_with:seat_no','integer','min:1','max:9999'],'seat_no'=>['nullable','required_with:room_no','integer','min:1','max:9999'],
            'remark'=>['nullable','string','max:2000'],
        ]);
        if($record && (int)$data['wmi_year_id']!==(int)$record->wmi_year_id) throw ValidationException::withMessages(['wmi_year_id'=>'The registration year cannot be changed. Create a separate registration for another WMI year.']);
        // Lock the center for every writer so automatic allocation cannot race with manual assignments.
        WmiExamCenter::whereKey($data['wmi_exam_center_id'])->lockForUpdate()->firstOrFail();
        if($data['student_source']==='western') {
            $enrollment=StudentEnrollment::with(['student','campus','grade','schoolClass','academicYear'])->findOrFail($data['enrollment_id']);
            $snapshot=self::enrollmentData($enrollment);
            unset($snapshot['academic_year'],$snapshot['phone_number'],$snapshot['email_address']);
            $data=array_replace($data,$snapshot);$data['wmi_school_id']=null;
            validator($data,['student_name'=>['required'],'gender'=>['required','in:M,F'],'current_grade'=>['required'],'campus_study'=>['required']])->validate();
            validator($data,['enrollment_id'=>[Rule::unique('wmi_registrations')->where('wmi_year_id',$data['wmi_year_id'])->ignore($record?->id)]],['enrollment_id.unique'=>'This Western student is already registered for this WMI year.'])->validate();
            // Different enrollment rows for the same student must not create duplicate registrations.
            abort_if(WmiRegistration::where('wmi_year_id',$data['wmi_year_id'])->where('student_code',$data['student_code'])->when($record,fn($q)=>$q->where('id','!=',$record->id))->exists(),422,'This student is already registered for this WMI year.');
        } else {
            $data['enrollment_id']=null;$data['student_code']=null;$data['campus_study']='Other';
            $data['student_name']=trim($data['student_name']);
            if($data['student_name']==='')throw ValidationException::withMessages(['student_name'=>'Enter the student name.']);
        }
        if($data['registration_channel']==='online'){$data['registrar_campus_id']=null;$data['campus_registered']='Online';}
        else $data['campus_registered']=SchoolInfo::findOrFail($data['registrar_campus_id'])->campus_name_en;
        unset($data['registration_channel']);
        if(!empty($data['room_no'])) validator($data,['seat_no'=>[Rule::unique('wmi_registrations')->where('wmi_exam_center_id',$data['wmi_exam_center_id'])->where('room_no',$data['room_no'])->ignore($record?->id)]],['seat_no.unique'=>'This room and seat are already assigned at the selected exam center.'])->validate();
        return $data;
    }

    public function store(Request $request)
    {
        DB::transaction(function() use($request){$data=$this->validated($request);WmiRegistration::create($data+['reference'=>'WMI-'.Str::upper((string)Str::uuid()),'registered_by'=>$request->user()->id]);});
        return response()->json(['message'=>'Candidate registered successfully.'],201);
    }
    public function update(Request $request,WmiRegistration $registration)
    {
        DB::transaction(function() use($request,$registration){$record=WmiRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();$record->update($this->validated($request,$record));});
        return response()->json(['message'=>'Registration updated successfully.']);
    }

    public function destroy(WmiRegistration $registration)
    {
        try {
            DB::transaction(fn()=>WmiRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail()->delete());
        } catch (\Illuminate\Database\QueryException $error) {
            if(in_array((string)$error->getCode(),['23000','23503'],true))abort(409,'This registration is linked to exam data and cannot be deleted.');
            throw $error;
        }
        return response()->json(['message'=>'Registration deleted successfully.']);
    }

    public function export(Request $request)
    {
        $query=$this->query($request);
        return response()->streamDownload(function() use($query){
            $handle=fopen('php://output','wb');fwrite($handle,"\xEF\xBB\xBF");
            fputcsv($handle,['registration_reference','wmi_year','wmi_id_number','student_id','student_name','date_of_birth','gender','current_grade','grade_contest','campus_study','campus_registered','school_name','school_type','exam_center','phone_number','telegram_number','email_address','room_no','seat_no'],',','"','');
            foreach($query->cursor() as $row) fputcsv($handle,array_map([G9ExamSpreadsheet::class,'csvCell'],[$row->reference,'WMI '.$row->year->year,$row->wmi_id_number,$row->student_code,$row->student_name,$row->date_of_birth?->format('Y-m-d'),$row->gender,$row->current_grade,$row->grade_contest,$row->campus_study,$row->campus_registered,$row->school?->name_en??'WESTERN INTERNATIONAL SCHOOL',$row->school?->school_type??'Private',$row->center->name,$row->phone_number,$row->telegram_number,$row->email_address,$row->room_no,$row->seat_no]),',','"','');
            fclose($handle);
        },'wmi-candidates-'.now()->format('Ymd-His').'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    public function importIds(Request $request,\App\Support\WmiIdSpreadsheet $reader)
    {
        $request->validate(['wmi_year_id'=>['required','integer','exists:wmi_years,id'],'file'=>['required','file','max:5120']]);
        $file=$request->file('file');$rows=$reader->read($file->getRealPath(),strtolower($file->getClientOriginalExtension()));
        $errors=[];$updates=[];$references=[];$ids=[];
        // Validate the whole file and commit atomically. Blanks never erase an issued ID.
        DB::transaction(function() use($request,$rows,&$errors,&$updates,&$references,&$ids){
            WmiYear::whereKey($request->integer('wmi_year_id'))->lockForUpdate()->firstOrFail();
            foreach($rows as $row){
                $ref=trim($row['registration_reference']??'');$id=trim($row['wmi_id_number']??'');$line=$row['_row'];
                if($id==='')continue;
                if($ref===''||strlen($id)>80||preg_match('/^[=+@\-]/',$id)){ $errors[]="Row $line: provide a registration reference and a valid committee ID (up to 80 characters).";continue; }
                $key=mb_strtolower($id);
                if(isset($references[$ref])||isset($ids[$key])){$errors[]="Row $line: duplicate registration reference or WMI ID in this file.";continue;}
                $references[$ref]=true;$ids[$key]=true;
                $record=WmiRegistration::where('wmi_year_id',$request->integer('wmi_year_id'))->where('reference',$ref)->first();
                if(!$record){$errors[]="Row $line: registration reference does not belong to the selected WMI year.";continue;}
                if($record->wmi_id_number!==null && $record->wmi_id_number!==$id){$errors[]="Row $line: an existing committee ID cannot be overwritten through import.";continue;}
                if(WmiRegistration::where('wmi_year_id',$record->wmi_year_id)->whereRaw('LOWER(wmi_id_number) = ?',[$key])->where('id','!=',$record->id)->exists()){$errors[]="Row $line: this WMI ID belongs to another candidate.";continue;}
                $updates[]=[$record,$id];
            }
            if($errors)throw ValidationException::withMessages(['file'=>array_slice($errors,0,50)]);
            foreach($updates as [$record,$id])$record->update(['wmi_id_number'=>$id]);
        });
        return response()->json(['message'=>count($updates).' committee WMI ID(s) updated. Blank IDs were left unchanged.']);
    }

    public function allocate(Request $request)
    {
        $data=$request->validate(['wmi_exam_center_id'=>['required','integer','exists:wmi_exam_centers,id'],'capacity'=>['required','integer','min:1','max:100']]);
        $count=DB::transaction(function() use($data){
            WmiExamCenter::whereKey($data['wmi_exam_center_id'])->lockForUpdate()->firstOrFail();
            $base=WmiRegistration::where('wmi_exam_center_id',$data['wmi_exam_center_id']);
            $room=(int)(clone $base)->max('room_no')+1;$seat=1;$count=0;
            $records=(clone $base)->whereNull('room_no')->whereNull('seat_no')->orderByRaw("CASE WHEN grade_contest = 'K' THEN 0 ELSE CAST(grade_contest AS SIGNED) END")->orderBy('student_name')->orderBy('id')->get();
            foreach($records as $record){$record->update(['room_no'=>$room,'seat_no'=>$seat]);$count++;if(++$seat>$data['capacity']){$room++;$seat=1;}}
            return $count;
        });
        return response()->json(['message'=>"$count candidate(s) assigned rooms and seats. Existing assignments were preserved."]);
    }
}
