<?php
namespace App\Http\Controllers;
use App\Models\{BrandingSetting,G12ExamRecord,G12ExamCenter,G12ExamFile,G12ExamImport,SchoolInfo,User};
use App\Support\{G12NationalExamPermissions as Access,G12NationalExamFields as Fields,ScholarshipExport};
use App\Services\{G12NationalExams,G12NationalExamFiles};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,File,Storage,Validator};
use Illuminate\Validation\{Rule,ValidationException};
class G12NationalExamController {
    public function __construct(private G12NationalExams $exams,private G12NationalExamFiles $files){}
    private function tab(Request $request): string {
        $tab=$request->route('section')??$request->input('section',$request->input('tab'));$tabs=array_keys(Access::submenus());
        if(!$tab)$tab=collect($tabs)->first(fn($t)=>Access::allows($request->user(),$t));
        abort_unless(in_array($tab,$tabs,true)&&Access::allows($request->user(),$tab),403);return $tab;
    }
    public function index(Request $request){
        $tab=$this->tab($request);$permissions=[];foreach(Access::catalog() as $action=>$label)$permissions[$action]=Access::allows($request->user(),$action);
        return view('g12-national-exams.index',compact('tab','permissions'));
    }
    private function query(Request $request,string $action){
        $data=$request->validate(['academic_year'=>'nullable|string|max:9','language_exam'=>'nullable|string|max:80','class_selected'=>'nullable|string|max:150','campus_id'=>'nullable|integer','center_id'=>'nullable|integer','record_id'=>'nullable|integer','grade'=>'nullable|string|max:80','result_status'=>'nullable|in:pending,passed,failed','certificate_status'=>'nullable|in:awaiting,available,collected,not_applicable,review','certificate_type'=>'nullable|in:soft,hard','search'=>'nullable|string|max:150']);
        return G12ExamRecord::whereIn('campus_id',Access::campusIds($request->user(),$action))
            ->when($data['academic_year']??null,fn($q,$v)=>$q->where('academic_year',$v))->when($data['campus_id']??null,fn($q,$v)=>$q->where('campus_id',$v))
            ->when($data['center_id']??null,fn($q,$v)=>$q->where('center_id',$v))->when($data['record_id']??null,fn($q,$v)=>$q->whereKey($v))->when($data['grade']??null,fn($q,$v)=>$q->where('grade',$v))
            ->when($data['language_exam']??null,fn($q,$v)=>$q->where('data->language_exam',$v))
            ->when($data['class_selected']??null,fn($q,$v)=>$q->where(fn($classes)=>$classes->where('data->class_sellected',$v)->orWhere('data->class_selected',$v)))
            ->when($data['result_status']??null,fn($q,$v)=>$q->where('result_status',$v))
            ->when($data['search']??null,fn($q,$v)=>$q->where(fn($s)=>$s->where('student_code','like','%'.$v.'%')->orWhere('name_en','like','%'.$v.'%')->orWhere('name_kh','like','%'.$v.'%')->orWhere('data->phone_number','like','%'.$v.'%')))
            ->when(($data['certificate_status']??null)||($data['certificate_type']??null),fn($q)=>$q->whereHas('certificates',fn($s)=>$s->when($data['certificate_status']??null,fn($t,$v)=>$t->where('status',$v))->when($data['certificate_type']??null,fn($t,$v)=>$t->where('type',$v))));
    }
    public function meta(Request $request){
        $tab=$this->tab($request);$ids=Access::campusIds($request->user(),$tab);
        $students=G12ExamRecord::whereIn('campus_id',$ids);
        $examData=$students->clone()->get(['data']);
        $examLanguages=$examData->map(fn($record)=>trim((string)($record->data['language_exam']??'')))->filter()->unique()->sortBy(fn($value)=>mb_strtolower($value))->values();
        $selectedClasses=$examData->map(fn($record)=>trim((string)($record->data['class_sellected']??$record->data['class_selected']??'')))->filter()->unique()->sortBy(fn($value)=>mb_strtolower($value))->values();
        return response()->json(['campuses'=>SchoolInfo::whereIn('id',$ids)->orderBy('campus_name_en')->get(['id','campus_name_en']),
            'centers'=>G12ExamCenter::orderBy('name')->get(),'years'=>$students->clone()->distinct()->orderByDesc('academic_year')->pluck('academic_year'),
            'grades'=>$students->clone()->whereNotNull('grade')->distinct()->orderBy('grade')->pluck('grade'),
            'exam_languages'=>$examLanguages,'selected_classes'=>$selectedClasses,
            'students'=>$students->orderBy('name_kh')->orderBy('name_en')->get(['id','name_kh','name_en','student_code','academic_year']),
            'fields'=>Fields::GROUPS,'document_fields'=>Fields::DOCUMENT_FIELDS]);
    }
    public function list(Request $request){
        $tab=$this->tab($request);abort_unless(in_array($tab,['records','assign-centers','certificates','reports'],true),403);
        $request->validate(['per_page'=>['nullable',Rule::in(['15','25','50','100','all'])],'page'=>'nullable|integer|min:1']);
        $q=$this->query($request,$tab)->with(['center','campus','certificates']);
        if($tab==='assign-centers'){
            $q->orderBy(SchoolInfo::select('campus_name_en')->whereColumn('tb_school_info.id','tb_g12_exam_record.campus_id'))
                ->orderBy('grade')->orderBy('name_kh')->orderBy('id');
        }else{
            $q->orderBy('name_en')->orderBy('id');
        }
        $summary=match($tab){
            'records'=>$this->recordSummary(clone $q),
            'assign-centers'=>$this->assignmentSummary(clone $q),
            default=>null,
        };
        $size=$request->input('per_page','15');$perPage=$size==='all'?max(1,$q->clone()->count()):(int)$size;
        $page=min(max(1,$request->integer('page',1)),max(1,(int)ceil($q->clone()->count()/$perPage)));
        $result=$q->paginate($perPage,['*'],'page',$page);$records=$result->getCollection();
        $creatorEvents=DB::table('tb_g12_exam_event')->whereIn('record_id',$records->modelKeys())->whereIn('action',['create-record','import-record'])->orderBy('id')->get(['record_id','user_id','created_at'])->unique('record_id')->keyBy('record_id');
        $creators=User::with(['position','activeCampus','staffProfile.position','staffProfile.primaryCampus'])->whereIn('id',$creatorEvents->pluck('user_id')->filter()->unique())->get()->keyBy('id');
        $records->transform(function($record)use($request,$creatorEvents,$creators){
            $event=$creatorEvents->get($record->id);$creator=$event?$creators->get($event->user_id):null;$staff=$creator?->staffProfile;
            return $this->summary($record,$request)+['added_by'=>[
                'name'=>$staff?->name_en?:$creator?->name?:'System',
                'position'=>$staff?->position?->name?:$creator?->position?->name?:'—',
                'campus'=>$staff?->primaryCampus?->campus_name_en?:$creator?->activeCampus?->campus_name_en?:'—',
                'date'=>$event?->created_at?:$record->created_at?->toISOString(),
            ]];
        });
        $payload=$result->toArray();if($summary!==null)$payload['summary']=$summary;return response()->json($payload);
    }
    private function assignmentSummary($query): array {
        $records=$query->reorder()->get(['id','center_id','data']);
        $summary=['total'=>$records->count(),'male'=>0,'female'=>0,
            'centers'=>['assigned'=>0,'blank'=>0],'rooms'=>['assigned'=>0,'blank'=>0],'seats'=>['assigned'=>0,'blank'=>0]];
        foreach($records as $record){
            $gender=mb_strtolower(trim((string)($record->data['gender']??'')));
            if(in_array($gender,['male','m','ប','ប្រុស'],true))$summary['male']++;
            elseif(in_array($gender,['female','f','ស','ស្រី'],true))$summary['female']++;
            $summary['centers'][filled($record->center_id)?'assigned':'blank']++;
            $summary['rooms'][filled(trim((string)($record->data['g12_exam_room']??'')))?'assigned':'blank']++;
            $summary['seats'][filled(trim((string)($record->data['g12_exam_seat']??'')))?'assigned':'blank']++;
        }
        return $summary;
    }
    private function recordSummary($query): array {
        $query->setEagerLoads(['campus'=>fn($campus)=>$campus->select('id','campus_name_en')]);
        $records=$query->reorder()->get(['id','campus_id','data']);
        $summary=['total'=>$records->count(),'male'=>0,'female'=>0,'groups'=>['science_khmer'=>0,'science_english'=>0,'social_khmer'=>0,'social_english'=>0],'campuses'=>[]];
        foreach($records as $record){
            $gender=mb_strtolower(trim((string)($record->data['gender']??'')));
            if(in_array($gender,['male','m','ប','ប្រុស'],true))$summary['male']++;elseif(in_array($gender,['female','f','ស','ស្រី'],true))$summary['female']++;
            $language=mb_strtolower(trim((string)($record->data['language_exam']??'')));
            $languageGroup=str_contains($language,'english')||str_contains($language,'អង់គ្លេស')?'english':(str_contains($language,'khmer')||str_contains($language,'ខ្មែរ')?'khmer':null);
            $class=mb_strtolower(trim((string)($record->data['class_sellected']??$record->data['class_selected']??'')));
            $classGroup=str_contains($class,'social')||str_contains($class,'សង្គម')?'social':(str_contains($class,'science')||str_contains($class,'វិទ្យា')?'science':null);
            if($classGroup&&$languageGroup)$summary['groups'][$classGroup.'_'.$languageGroup]++;
            $campus=trim((string)($record->campus?->campus_name_en??$record->data['campus']??'Other Campus'))?:'Other Campus';
            $summary['campuses'][$campus]=($summary['campuses'][$campus]??0)+1;
        }
        uksort($summary['campuses'],fn($left,$right)=>strnatcasecmp($left,$right));
        return $summary;
    }
    private function summary(G12ExamRecord $r,Request $request): array {
        return ['id'=>$r->id,'version'=>$r->version,'name_en'=>$r->name_en,'name_kh'=>$r->name_kh,'student_code'=>$r->student_code,'academic_year'=>$r->academic_year,
            'campus'=>$r->campus?->campus_name_en,'center'=>$r->center?->name,'grade'=>$r->grade,'overall_grade'=>$r->overall_grade,'result_status'=>$r->result_status,
            'room'=>$r->data['g12_exam_room']??'','seat'=>$r->data['g12_exam_seat']??'','photo_url'=>$r->photo_path?route('g12-national-exams.photo',$r):null,
            'dob'=>$r->data['dob']??'','mother_name'=>$r->data['mother_name']??'','mother_occupation'=>$r->data['mother_occupation']??'',
            'father_name'=>$r->data['father_name']??'','father_occupation'=>$r->data['father_occupation']??'',
            'g9_exam_center'=>$r->data['g9_exam_center']??'','g9_exam_date'=>$r->data['g9_exam_date']??'',
            'g9_exam_room'=>$r->data['g9_exam_room']??'','g9_exam_seat'=>$r->data['g9_exam_seat']??'',
            'language_exam'=>$r->data['language_exam']??'','class_selected'=>$r->data['class_sellected']??$r->data['class_selected']??'','g12_exam_date'=>$r->data['g12_exam_date']??'',
            'phone_number'=>$r->data['phone_number']??'','campus_id'=>$r->campus_id,'center_id'=>$r->center_id,
            'certificates'=>$r->certificates,'can_edit'=>Access::allows($request->user(),'edit',$r->campus_id),'can_assign'=>Access::allows($request->user(),'assign-centers-manage',$r->campus_id)];
    }
    public function show(Request $request,G12ExamRecord $record){
        abort_unless(Access::allows($request->user(),'records',$record->campus_id)||Access::allows($request->user(),'certificates',$record->campus_id)||Access::allows($request->user(),'reports',$record->campus_id),403);
        $record->load(['center','campus','certificates']);$data=$this->summary($record,$request)+['data'=>$record->data,'campus_id'=>$record->campus_id,'center_id'=>$record->center_id,'photo_filename'=>$record->photo_filename,'document_folder'=>$record->document_folder];
        $data['files']=Access::allows($request->user(),'documents-view',$record->campus_id)?$record->files()->latest('id')->get()->map(fn($f)=>$f->only(['id','kind','original_name','mime','size','created_at'])+['url'=>route('g12-national-exams.file',$f),'download'=>route('g12-national-exams.file',[$f,'download'=>1])]):[];
        $data['history']=DB::table('tb_g12_exam_event')->where('record_id',$record->id)->latest('id')->limit(30)->get(['action','user_id','created_at']);
        foreach(['collection','collection-correct','documents-upload','documents-view'] as $action)$data['permissions'][$action]=Access::allows($request->user(),$action,$record->campus_id);
        return response()->json($data)->header('Cache-Control','no-store, private');
    }
    public function save(Request $request,?G12ExamRecord $record=null){
        $input=$request->validate(['data'=>'required|array','campus_id'=>'required|integer','center_id'=>'nullable|integer','version'=>'nullable|integer']);
        $allowed=array_merge(array_keys(Fields::labels()),array_values(Fields::DOCUMENT_FIELDS));$data=collect($input['data'])->only($allowed)->all();
        $saved=$this->exams->save($request->user(),$data+collect($input)->only(['campus_id','center_id','version'])->all(),$record);
        return response()->json(['message'=>'Student exam record saved.','id'=>$saved->id]);
    }
    public function saveExamAssignment(Request $request,G12ExamRecord $record){
        $this->exams->authorize($request->user(),'assign-centers-manage',$record->campus_id);
        $input=$request->validate([
            'center_id'=>['nullable','integer',Rule::exists('tb_g12_exam_center','id')->where('active',true)],
            'room'=>'nullable|string|max:80','seat'=>'nullable|string|max:80','version'=>'required|integer|min:1',
        ]);
        $saved=DB::transaction(function()use($request,$record,$input){
            $record=G12ExamRecord::lockForUpdate()->findOrFail($record->id);
            $this->exams->authorize($request->user(),'assign-centers-manage',$record->campus_id);
            if((int)$input['version']!==$record->version)$this->exams->fail('This assignment was changed in another window. Refresh the list and try again.');
            $center=filled($input['center_id']??null)?G12ExamCenter::where('active',true)->findOrFail((int)$input['center_id']):null;
            $before=['center_id'=>$record->center_id,'room'=>$record->data['g12_exam_room']??'','seat'=>$record->data['g12_exam_seat']??''];
            $data=$record->data;$data['g12_exam_center']=$center?->name??'';$data['g12_exam_room']=trim((string)($input['room']??''));$data['g12_exam_seat']=trim((string)($input['seat']??''));
            $record->update(['center_id'=>$center?->id,'data'=>$data,'version'=>$record->version+1]);
            $this->exams->event($request->user(),'exam-center-assigned',['before'=>$before,'after'=>['center_id'=>$record->center_id,'room'=>$data['g12_exam_room'],'seat'=>$data['g12_exam_seat']]],$record->id);
            return $record->fresh('center');
        });
        return response()->json(['message'=>'Exam assignment saved.','version'=>$saved->version,'center'=>$saved->center?->name,'room'=>$saved->data['g12_exam_room']??'','seat'=>$saved->data['g12_exam_seat']??'']);
    }
    public function saveCenter(Request $request,?G12ExamCenter $center=null){
        $this->exams->authorize($request->user(),'centers-manage');$data=$request->validate(['name'=>'required|string|max:255','active'=>'required|boolean']);
        $data['name_key']=Fields::centerKey($data['name']);if(G12ExamCenter::where('name_key',$data['name_key'])->when($center,fn($q)=>$q->where('id','!=',$center->id))->exists())$this->exams->fail('That exam center already exists.');
        $center??=new G12ExamCenter();$center->fill($data)->save();$this->exams->event($request->user(),'center-saved',$center->toArray());return response()->json(['message'=>'Exam center saved.']);
    }
    public function centerStatus(Request $request,G12ExamCenter $center){
        $this->exams->authorize($request->user(),'centers-manage');$data=$request->validate(['active'=>'required|boolean']);$previous=$center->active;
        $center->active=$data['active'];$center->save();$this->exams->event($request->user(),'center-status-updated',['center_id'=>$center->id,'source_id'=>$center->source_id,'name'=>$center->name,'from'=>$previous,'to'=>$center->active]);
        return response()->json(['message'=>$center->active?'Exam center activated.':'Exam center deactivated.','active'=>$center->active]);
    }
    public function centers(Request $request){
        $this->exams->authorize($request->user(),'centers');
        $data=$request->validate(['search'=>'nullable|string|max:150','per_page'=>['nullable',Rule::in(['10','25','50','100'])],'page'=>'nullable|integer|min:1','sort_by'=>['nullable',Rule::in(['source_id','name'])],'sort_dir'=>['nullable',Rule::in(['asc','desc'])]]);
        $sortBy=$data['sort_by']??'name';$sortDir=$data['sort_dir']??'asc';
        $query=G12ExamCenter::query()->when($data['search']??null,fn($q,$search)=>$q->where(fn($match)=>$match->where('name','like','%'.$search.'%')->orWhere('source_id','like','%'.$search.'%')))->orderBy($sortBy,$sortDir)->orderBy('id');
        $perPage=(int)($data['per_page']??10);$lastPage=max(1,(int)ceil($query->clone()->count()/$perPage));$page=min($request->integer('page',1),$lastPage);
        return response()->json($query->paginate($perPage,['id','source_id','name','active'],'page',$page));
    }
    public function preview(Request $request){
        $request->validate(['file'=>'required|file|extensions:xlsx|max:20480']);$batch=$this->exams->preview($request->user(),$request->file('file'));
        return response()->json(['id'=>$batch->id,'preview'=>$this->exams->inspect($request->user(),$batch)]);
    }
    public function template(Request $request){
        $this->exams->authorize($request->user(),'import');
        $campus=SchoolInfo::whereIn('id',Access::campusIds($request->user(),'import'))->orderBy('campus_name_en')->value('campus_name_en')??'';
        $centers=G12ExamCenter::where('active',true)->orderBy('name')->get(['source_id','name'])->toArray();
        $bytes=app(\App\Support\G12NationalExamTemplate::class)->generate($campus,$centers);
        return response($bytes,200,['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Content-Disposition'=>'attachment; filename="g12-national-exams-import-template.xlsx"','Cache-Control'=>'private, no-store']);
    }
    public function inspect(Request $request,G12ExamImport $batch){
        $data=$request->validate(['maps'=>'nullable|array','maps.campuses'=>'nullable|array','maps.centers'=>'nullable|array']);
        return response()->json(['id'=>$batch->id,'preview'=>$this->exams->inspect($request->user(),$batch,$data['maps']??[])]);
    }
    public function confirm(Request $request,G12ExamImport $batch){
        $data=$request->validate(['confirmed'=>'required|accepted','maps'=>'nullable|array','versions'=>'required|array']);
        $result=$this->exams->confirm($request->user(),$batch,$data['maps']??[],$data['versions']);return response()->json(['message'=>'MoEYS import completed.','summary'=>$result]);
    }
    public function history(Request $request){
        $this->exams->authorize($request->user(),'imports');return response()->json(G12ExamImport::where('user_id',$request->user()->id)->latest('id')->limit(100)->get(['id','kind','filename','status','summary','created_at','confirmed_at']));
    }
    public function upload(Request $request,G12ExamRecord $record){
        $data=$request->validate(['kind'=>['required',Rule::in(G12NationalExamFiles::KINDS)],'file'=>'required|file|max:20480','replace'=>'nullable|boolean']);
        $file=$this->files->store($request->user(),$record,$data['kind'],$request->file('file')->get(),$request->file('file')->getClientOriginalName(),$request->boolean('replace'));
        return response()->json(['message'=>'File uploaded; previous versions are retained.','id'=>$file->id]);
    }
    public function collection(Request $request,G12ExamRecord $record,string $type){
        $request->validate(['file'=>'nullable|file|max:20480']);$this->files->collection($request->user(),$record,$type,$request->except('file'),$request->file('file'));
        return response()->json(['message'=>'Certificate tracking saved.']);
    }
    private function assertValidPhotoUploads(Request $request): void {
        foreach((array)$request->file('files',[]) as $index=>$file){
            if(!$file instanceof UploadedFile||$file->isValid())continue;
            $name=$file->getClientOriginalName()?:'The selected file';$message=match($file->getError()){
                UPLOAD_ERR_INI_SIZE=>"{$name} exceeds the upload limit used by the website's PHP-FPM process (PHP error 1). Check for PHP-FPM pool or per-directory overrides.",
                UPLOAD_ERR_FORM_SIZE=>"{$name} exceeds the upload limit declared by the upload form (PHP error 2).",
                UPLOAD_ERR_PARTIAL=>"{$name} was only partially uploaded (PHP error 3). Check the connection and retry, or use a smaller ZIP.",
                UPLOAD_ERR_NO_FILE=>"No file was received for {$name} (PHP error 4). Choose the file again.",
                UPLOAD_ERR_NO_TMP_DIR=>"The server has no usable temporary upload directory (PHP error 6). Ask the administrator to check PHP's temporary folder.",
                UPLOAD_ERR_CANT_WRITE=>"The server could not write {$name} to temporary storage (PHP error 7). Check free disk space and temporary-folder permissions.",
                UPLOAD_ERR_EXTENSION=>"A PHP extension stopped the upload of {$name} (PHP error 8). Check the PHP-FPM log.",
                default=>"The server rejected {$name} before the photo importer could read it (PHP upload error {$file->getError()}).",
            };
            throw ValidationException::withMessages(["files.{$index}"=>$message]);
        }
    }
    public function photoPreview(Request $request){
        $this->assertValidPhotoUploads($request);
        $request->validate(['academic_year'=>'required|string|max:9','files'=>'required|array|min:1|max:100','files.*'=>'required|file|max:204800'],[
            'files.required'=>'Choose photos or a ZIP before previewing.',
            'files.*.uploaded'=>'The server rejected this upload before the photo importer could read it. Try a smaller ZIP or ask your server administrator to increase the upload limit.',
            'files.*.max'=>'Each ZIP upload must be no larger than 200 MB.',
        ]);
        $batch=$this->files->preview($request->user(),$request->input('academic_year'),$request->file('files'));
        $entries=collect($batch->payload['entries'])->map(fn($e,$i)=>collect($e)->only(['name','record_id','student','status','size'])->all()+['index'=>$i])->values();
        return response()->json(['id'=>$batch->id,'entries'=>$entries,'year'=>$batch->payload['year']]);
    }
    public function photoProcess(Request $request,G12ExamImport $batch,int $index){
        $data=$request->validate(['record_id'=>'nullable|integer','replace'=>'required|boolean']);return response()->json($this->files->process($request->user(),$batch,$index,$data['record_id']??null,$data['replace']));
    }
    public function file(Request $request,G12ExamFile $file){
        if($file->kind==='portrait')abort_unless(collect(['records','assign-centers','certificates','photos','reports'])->contains(fn($a)=>Access::allows($request->user(),$a,$file->record->campus_id)),403);
        else $this->exams->authorize($request->user(),'documents-view',$file->record->campus_id);
        abort_unless(Storage::disk('local')->exists($file->path),404);
        return Storage::disk('local')->response($file->path,$file->kind==='portrait'?basename($file->path):$file->original_name,['Content-Type'=>$file->mime,'X-Content-Type-Options'=>'nosniff','Cache-Control'=>'no-store, private'],$request->boolean('download')?'attachment':'inline');
    }
    public function photo(Request $request,G12ExamRecord $record){
        abort_unless(Access::allows($request->user(),'records',$record->campus_id)||Access::allows($request->user(),'assign-centers',$record->campus_id)||Access::allows($request->user(),'certificates',$record->campus_id)||Access::allows($request->user(),'photos',$record->campus_id)||Access::allows($request->user(),'reports',$record->campus_id),403);
        abort_unless($record->photo_path&&Storage::disk('local')->exists($record->photo_path),404);
        return Storage::disk('local')->response($record->photo_path,null,['X-Content-Type-Options'=>'nosniff','Cache-Control'=>'no-store, private']);
    }
    public function report(Request $request){
        $this->exams->authorize($request->user(),'export');$request->validate(['format'=>['required',Rule::in(['print','xlsx','pdf'])]]);
        $records=$this->query($request,'export')->with(['center','campus','certificates'])->orderBy('academic_year')->orderBy('name_en')->get();
        $format=$request->input('format');
        if(in_array($format,['print','pdf'],true)){
            $imageData=function(string $disk,?string $path): ?string {
                if(!$path||!Storage::disk($disk)->exists($path))return null;
                $mime=Storage::disk($disk)->mimeType($path)?:'application/octet-stream';
                return 'data:'.$mime.';base64,'.base64_encode(Storage::disk($disk)->get($path));
            };
            $branding=BrandingSetting::current();$reportLogo=$imageData('public',$branding->report_logo_1_path);
            $photoUrls=$records->mapWithKeys(fn($record)=>[$record->id=>$imageData('local',$record->photo_path)]);
            $filters=$request->only(['academic_year','language_exam','class_selected','campus_id','grade','search']);
            $reportCampus=filled($filters['campus_id']??null)?SchoolInfo::find($filters['campus_id']):null;
            $fontPath=public_path('fonts/khmer/KhmerOSsiemreap.ttf');
            $khmerFontData=is_file($fontPath)?'data:font/truetype;base64,'.base64_encode(file_get_contents($fontPath)):null;
            $data=compact('records','reportLogo','photoUrls','filters','reportCampus','khmerFontData')+['pdfExport'=>$format==='pdf','reportDate'=>now('Asia/Bangkok')];
            if($format==='print')return view('g12-national-exams.report',$data);
            File::ensureDirectoryExists(storage_path('fonts'),0755,true);
            return Pdf::loadView('g12-national-exams.report',$data)->setPaper('a4','landscape')->download('g12-list-of-candidates.pdf');
        }
        $labels=Fields::labels();$rows=[array_values($labels)+[]];$rows[0]=array_merge(array_values($labels),['Soft Certificate Status','Soft Collected Date','Hard Certificate Status','Hard Collected Date']);
        foreach($records as $r){$values=$r->data;foreach(['academic_year'=>$r->academic_year,'student_id'=>$r->student_code,'campus'=>$r->campus?->campus_name_en,'g12_exam_center'=>$r->center?->name] as $k=>$v)$values[$k]=$v;
            $soft=$r->certificates->firstWhere('type','soft');$hard=$r->certificates->firstWhere('type','hard');
            $rows[]=array_merge(array_map(fn($key)=>(string)($values[$key]??''),array_keys($labels)),[$soft?->status??'',$soft?->collected_date??'',$hard?->status??'',$hard?->collected_date??'']);}
        return response(app(ScholarshipExport::class)->xlsx($rows),200,['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Content-Disposition'=>'attachment; filename="g12-national-exams.xlsx"']);
    }
}
