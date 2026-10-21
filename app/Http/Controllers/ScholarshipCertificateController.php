<?php
namespace App\Http\Controllers;

use App\Models\{AcademicYear,ScholarshipCertificate,ScholarshipCertificateSetting,Staff};
use App\Services\ScholarshipCertificates;
use App\Support\{ScholarshipPermissions,ScholarshipCertificateLayout,ScholarshipCertificateReport};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\{Rule,ValidationException};

class ScholarshipCertificateController
{
    public function __construct(private ScholarshipCertificates $certificates,private ScholarshipCertificateReport $reports){}
    public function index(Request $request,array $tabs)
    {
        $scope=$this->certificates->scope($request->user());
        $years=AcademicYear::regular()->orderByDesc('academic_year')->get();
        $year=$request->filled('academic_year_id')?$years->firstWhere('id',(int)$request->academic_year_id):($years->firstWhere('lifecycle_status','started')??$years->first());
        if($request->filled('academic_year_id')&&!$year)abort(404);
        $settings=$year?$this->certificates->settings($year):null;$exam=$year?$this->certificates->exam($year):null;
        $campuses=ScholarshipPermissions::campuses($request->user())->whereIn('id',$scope)->orderBy('campus_name_en')->get();
        $input=$request->validate(['campus'=>['nullable','integer',Rule::in($scope)],'grade'=>['nullable','integer','between:1,12'],
            'type'=>['nullable',Rule::in(array_keys(ScholarshipCertificateLayout::TYPES))],'q'=>['nullable','string','max:120'],
            'per_page'=>['nullable',Rule::in(['all',15,25,50,100])],'page'=>['nullable','integer','min:1','max:1000000']]);
        $eligible=$year?$this->certificates->eligible($year):collect();
        $issued=$year?ScholarshipCertificate::where('academic_year_id',$year->id)->get()->keyBy('student_id'):collect();
        // An issued certificate always displays its immutable recipient/award snapshot.
        $rows=$issued->sortBy('sequence')->filter(fn($c)=>in_array($c->campus_id,$scope))->map(fn($c)=>$c->snapshot+['certificate'=>$c])->values();
        $rows=$rows->concat($eligible->whereIn('campus_id',$scope)->whereNotIn('student_id',$issued->keys())->map(fn($row)=>$row+['certificate'=>null]));
        $fingerprints=$eligible->pluck('fingerprint','student_id');
        $rows=$rows->filter(fn($r)=>(empty($input['campus'])||$r['campus_id']==$input['campus'])&&(empty($input['grade'])||$r['grade']==$input['grade'])
            &&(empty($input['type'])||$r['type']===$input['type']));
        $studentOptions=$rows->pluck('student_name')->unique()->sort(SORT_NATURAL|SORT_FLAG_CASE)->values();
        $rows=$rows->filter(fn($r)=>empty($input['q'])||str_contains(mb_strtolower($r['student_name']),mb_strtolower($input['q'])))
            ->map(function($row)use($fingerprints){$c=$row['certificate'];$row['stale']=$c&&($fingerprints[$row['student_id']]??null)!==($c->snapshot['fingerprint']??null);return $row;})->values();
        $permissions=collect(['certificate-settings','certificate-template','certificate-assign','certificate-print','certificate-cancel'])->mapWithKeys(fn($a)=>[$a=>ScholarshipPermissions::allows($request->user(),$a)])->all();
        $pageSize=($input['per_page']??15)==='all'?'all':(int)($input['per_page']??15);
        $perPage=$pageSize==='all'?max(1,$rows->count()):$pageSize;
        $page=min((int)($input['page']??1),max(1,(int)ceil($rows->count()/$perPage)));
        $certificateRows=new LengthAwarePaginator($rows->forPage($page,$perPage)->values(),$rows->count(),$perPage,$page,[
            'path'=>route('western-scholarship.index'),'query'=>array_filter(array_replace($input,['tab'=>'certificates','academic_year_id'=>$year?->id,'per_page'=>$pageSize]),fn($v)=>$v!==null&&$v!=='')]);
        $pageData=['current_page'=>$page,'last_page'=>$certificateRows->lastPage(),'from'=>$certificateRows->firstItem(),'to'=>$certificateRows->lastItem(),'total'=>$certificateRows->total(),
            'per_page'=>$pageSize,'printable_ids'=>$exam?->published_at?$rows->filter(fn($r)=>$r['certificate']?->status==='active'&&!$r['stale'])->pluck('certificate.id')->values()->all():[]];
        if($request->ajax()&&$request->boolean('certificate_list'))return response()->json([
            'html'=>view('western-scholarship._certificate-list',compact('certificateRows','permissions','exam','pageData'))->render(),'pagination'=>$pageData,'url'=>$certificateRows->url($page)]);
        $staff=$permissions['certificate-settings']?Staff::with('position')->where('status',1)->orderBy('name_en')->get(['id','name_en','position_id'])->map(fn($s)=>['name'=>$s->name_en,'position'=>$s->position?->name??'']):collect();
        $assets=$year?$this->reports->assets($year):[];
        $samples=collect(ScholarshipCertificateLayout::TYPES)->mapWithKeys(function($label,$type)use($rows,$year,$settings){
            $sample=$rows->firstWhere('type',$type)??['student_name'=>'STUDENT NAME','campus'=>'BCH','grade'=>1,'class'=>'1A','place'=>'3rd Place','subject'=>'English','award_percent'=>'25','academic_year'=>$year?->academic_year??''];
            return [$type=>$year?$this->certificates->values($sample,$settings,($sample['certificate']??null)?->certificate_number??''):[]];
        });
        $editor=$year?['settings'=>$settings,'assets'=>$assets,'samples'=>$samples,
            'defaults'=>collect(ScholarshipCertificateLayout::TYPES)->mapWithKeys(fn($v,$k)=>[$k=>ScholarshipCertificateLayout::defaults($k)]),'fonts'=>ScholarshipCertificateLayout::fonts(),
            'urls'=>collect(['settings','layout','image','plan','assign','report'])->mapWithKeys(fn($a)=>[$a=>route('western-scholarship.certificates.'.$a,$year)]),
            'canEdit'=>$permissions['certificate-template']]:null;
        return view('western-scholarship.certificates',compact('tabs','years','year','exam','campuses','input','rows','permissions','settings','staff','editor','studentOptions','certificateRows','pageData','pageSize')+['tab'=>'certificates']);
    }
    public function settings(Request $request,AcademicYear $year)
    {
        $this->certificates->authorize($request->user(),'certificate-settings');
        $data=$request->validate(['version'=>['required','integer','min:0'],'issue_date'=>['required','date_format:Y-m-d'],
            'location'=>['required','string','max:150'],'scholarship_year'=>['required','regex:/^\d{4}-\d{4}$/'],
            'signers'=>['required','array','size:2'],'signers.*'=>['required','array:name,position'],
            'signers.*.name'=>['required','string','max:150'],'signers.*.position'=>['required','string','max:150']]);
        $version=$data['version'];unset($data['version']);
        return response()->json(['settings'=>$this->certificates->update($request->user(),$year,$version,$data,'certificate-settings')]);
    }
    public function layout(Request $request,AcademicYear $year)
    {
        $this->certificates->authorize($request->user(),'certificate-template');
        $data=$request->validate(['version'=>['required','integer','min:0'],'type'=>['required',Rule::in(array_keys(ScholarshipCertificateLayout::TYPES))],'layout'=>['required','array']]);
        $layout=ScholarshipCertificateLayout::validate($data['type'],$data['layout']);
        $saved=ScholarshipCertificateSetting::where('academic_year_id',$year->id)->first()?->layouts??[];$saved[$data['type']]=$layout;
        return response()->json(['settings'=>$this->certificates->update($request->user(),$year,$data['version'],['layouts'=>$saved],'certificate-template')]);
    }
    public function image(Request $request,AcademicYear $year)
    {
        $this->certificates->authorize($request->user(),'certificate-settings');
        $data=$request->validate(['version'=>['required','integer','min:0'],'kind'=>['required',Rule::in(['frame','logo'])],'image'=>['required','file','mimes:png,jpg,jpeg,webp','max:10240']]);
        $path=$this->reports->upload($request->file('image'));
        $settings=$this->certificates->update($request->user(),$year,(int)$data['version'],[$data['kind'].'_path'=>$path],'certificate-settings');
        return response()->json(['settings'=>$settings,'assets'=>$this->reports->assets($year)]);
    }
    public function plan(Request $request,AcademicYear $year)
    {
        $this->certificates->authorize($request->user(),'certificate-assign');return response()->json($this->certificates->plan($year));
    }
    public function assign(Request $request,AcademicYear $year)
    {
        $data=$request->validate(['hash'=>['required','string','size:64']]);
        return response()->json(['count'=>$this->certificates->assign($request->user(),$year,$data['hash'])]);
    }
    public function cancel(Request $request,ScholarshipCertificate $certificate)
    {
        $data=$request->validate(['reason'=>['required','string','min:5','max:1000']]);$this->certificates->cancel($request->user(),$certificate,$data['reason']);return response()->json(['ok'=>true]);
    }
    public function report(Request $request,AcademicYear $year)
    {
        $scope=$this->certificates->scope($request->user(),'certificate-print');$this->certificates->requirePublished($year);
        $data=$request->validate(['ids'=>['required','array','min:1','max:1000'],'ids.*'=>['required','integer','distinct'],
            'format'=>['required',Rule::in(['print','pdf'])],'frame'=>['required','boolean']]);
        $records=ScholarshipCertificate::where('academic_year_id',$year->id)->whereIn('campus_id',$scope)->whereIn('id',$data['ids'])->orderBy('sequence')->get();
        abort_unless($records->count()===count($data['ids']),403);
        $eligible=$this->certificates->eligible($year)->keyBy('student_id');$settings=$this->certificates->settings($year);
        foreach($records as $c)if($c->status!=='active'||($eligible[$c->student_id]['fingerprint']??null)!==($c->snapshot['fingerprint']??null))throw ValidationException::withMessages(['certificates'=>'A selected certificate is cancelled or its source rank changed. Review the assignment before printing.']);
        if(!ScholarshipCertificateSetting::where('academic_year_id',$year->id)->exists()||collect($settings['signers'])->contains(fn($s)=>blank($s['name']??'')))throw ValidationException::withMessages(['certificates'=>'Central staff must save the issue date and both signers before printing.']);
        $pages=$records->map(fn($c)=>['layout'=>$settings['layouts'][$c->type],'values'=>$this->certificates->values($c->snapshot,$settings,$c->certificate_number)])->all();$assets=$this->reports->assets($year);$frame=(bool)$data['frame'];
        foreach($pages as $page)if($page['layout']['fields']['logo']['visible']&&empty($assets[$page['layout']['logo_source']]))throw ValidationException::withMessages(['certificates'=>'The selected logo is missing. Choose a Branding logo or upload a logo.']);
        if($data['format']==='pdf')return response($this->reports->pdf($pages,$assets,$frame),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="wis-scholarship-certificates-'.$year->id.'.pdf"']);
        return view('western-scholarship.certificate-report',compact('pages','assets','frame'));
    }
}
