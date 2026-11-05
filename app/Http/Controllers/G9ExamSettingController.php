<?php

namespace App\Http\Controllers;

use App\Models\{AcademicYear,User};
use App\Services\G9ExamImports;
use App\Support\G9ExamCenterSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class G9ExamSettingController
{
    public const SECTIONS=G9ExamCenterSettings::DATES;

    private function authorize(Request $request): void
    {
        app(G9ExamImports::class)->authorize($request->user(),'settings');
    }
    private function canManage(User $user): bool
    {
        $central=$user->isSuperAdmin()||$user->is_global||$user->roles()->where('is_global',true)->where('access_roles.status',true)->exists();
        return $central&&(bool)app(G9ExamImports::class)->campusIds($user,'settings-manage');
    }
    public function index(Request $request)
    {
        $this->authorize($request);
        return view('g9-national-exams.settings',['years'=>AcademicYear::orderByDesc('academic_year')->get(),'sections'=>self::SECTIONS,'canManage'=>$this->canManage($request->user()),
            'configuredYear'=>DB::table('tb_g9_exam_year_setting')->orderByDesc('updated_at')->value('academic_year_id'),
            'eoyCampuses'=>\App\Models\SchoolInfo::whereIn('id',app(G9ExamImports::class)->campusIds($request->user(),'settings'))->orderBy('campus_name_en')->get()]);
    }
    public function show(Request $request)
    {
        $this->authorize($request);$input=$request->validate(['academic_year_id'=>['required','integer','exists:tb_academic_year,id']]);
        $setting=DB::table('tb_g9_exam_year_setting')->where('academic_year_id',$input['academic_year_id'])->first();$data=json_decode($setting?->data??'{}',true);
        $configured=collect($data['report_centers']??[])->keyBy('exam_center');
        $centers=\App\Models\G9ExamCandidate::where('academic_year_id',$input['academic_year_id'])->whereIn('campus_id',app(G9ExamImports::class)->campusIds($request->user(),'settings'))->get(['data'])
            ->map(fn($r)=>trim((string)($r->data['exam_center']??'')))->filter()->merge($configured->keys())->unique()->sort()->values()
            ->map(fn($center)=>$configured->get($center,['exam_center'=>$center,'location'=>'','department'=>'','signer'=>'ប្រធានមណ្ឌល']));
        return response()->json(['version'=>$setting?->version??0,'location'=>$data['date_location']??'រាជធានីភ្នំពេញ','dates'=>$this->dateValues($data),'report_centers'=>$centers,'can_manage'=>$this->canManage($request->user())]);
    }

    private function dateValues(array $data): array
    {
        $dates=[];
        foreach(self::SECTIONS as $key=>$section){
            $value=$data[$key.'_iso']??'';
            if(!$value){
                $text=str_replace(['០','១','២','៣','៤','៥','៦','៧','៨','៩'],range(0,9),$data[$key]??'');
                if(preg_match('/ថ្ងៃទី\s*(\d{1,2})\s*ខែ\s*([^\s]+)\s*ឆ្នាំ\s*(\d{4})/u',$text,$match))$value=app(G9ExamImports::class)->date($match[1].' '.$match[2].' '.$match[3]);
                elseif(preg_match('/^\d{4}-\d{2}-\d{2}$/',$text))$value=$text;
                if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$value))$value='';
            }
            $dates[$key]=['date'=>$value,'solar'=>$data[$key]??'','lunar'=>$data[$section['lunar']]??''];
        }
        return $dates;
    }
    public function save(Request $request)
    {
        $this->authorize($request);abort_unless($this->canManage($request->user()),403);
        $rules=['academic_year_id'=>['required','integer','exists:tb_academic_year,id'],'version'=>['required','integer','min:0'],'location'=>['required','string','max:150'],'dates'=>['required','array']];
        $rules+=['report_centers'=>['sometimes','array','max:200'],'report_centers.*.exam_center'=>['required','string','max:255','distinct'],
            'report_centers.*.location'=>['nullable','string','max:150'],'report_centers.*.department'=>['nullable','string','max:255'],'report_centers.*.signer'=>['nullable','string','max:150'],
            'report_centers.*.photo_signature'=>['sometimes','nullable','string','max:2800000']];
        foreach(self::SECTIONS as $key=>$section){$rules['dates.'.$key.'.date']=['nullable','date_format:Y-m-d'];$rules['dates.'.$key.'.solar']=['present','nullable','string','max:1000'];$rules['dates.'.$key.'.lunar']=['present','nullable','string','max:1000'];}
        $input=$request->validate($rules);
        foreach($input['report_centers']??[] as $index=>$center){
            if(!array_key_exists('photo_signature',$center))continue;
            $field='report_centers.'.$index.'.photo_signature';
            $input['report_centers'][$index]['photo_signature']=$this->signature($center['photo_signature'],$field);
        }
        DB::transaction(function()use($input,$request){
            DB::table('tb_academic_year')->where('id',$input['academic_year_id'])->lockForUpdate()->first();
            $row=DB::table('tb_g9_exam_year_setting')->where('academic_year_id',$input['academic_year_id'])->lockForUpdate()->first();
            abort_if(($row?->version??0)!==$input['version'],409,'These shared dates changed in another window. Reload the academic year before saving.');
            $before=json_decode($row?->data??'{}',true);$data=$before;$data['date_location']=$input['location'];
            if(array_key_exists('report_centers',$input)){
                $existing=collect($before['report_centers']??[])->keyBy('exam_center');
                foreach($input['report_centers'] as $center)$existing->put($center['exam_center'],array_replace($existing->get($center['exam_center'],[]),[
                    'exam_center'=>$center['exam_center'],'location'=>$center['location']??'','department'=>$center['department']??'','signer'=>($center['signer']??'')?:'ប្រធានមណ្ឌល',
                    'photo_signature'=>array_key_exists('photo_signature',$center)?$center['photo_signature']:($existing->get($center['exam_center'])['photo_signature']??null)]));
                $data['report_centers']=$existing->values()->all();
            }
            foreach(self::SECTIONS as $key=>$section){$date=$input['dates'][$key];$data[$key.'_iso']=$date['date']??'';$data[$key]=$date['solar']??'';$data[$section['lunar']]=$date['lunar']??'';}
            DB::table('tb_g9_exam_year_setting')->updateOrInsert(['academic_year_id'=>$input['academic_year_id']],['data'=>json_encode($data,JSON_THROW_ON_ERROR),'version'=>$input['version']+1,'updated_by'=>$request->user()->id,'created_at'=>$row?->created_at??now(),'updated_at'=>now()]);
            DB::table('tb_g9_exam_setting_event')->insert(['academic_year_id'=>$input['academic_year_id'],'user_id'=>$request->user()->id,'action'=>'save-dates','details'=>json_encode(['before'=>$before,'after'=>$data],JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
        });
        return $this->show($request);
    }

    private function signature(?string $value,string $field): ?string
    {
        if(!$value)return null;
        $fail=fn()=>throw \Illuminate\Validation\ValidationException::withMessages([$field=>'Choose a valid PNG or JPG signature image, up to 2 MB and 4 megapixels.']);
        if(!preg_match('~^data:image/(png|jpeg);base64,([A-Za-z0-9+/=]+)$~',$value,$matches))$fail();
        $bytes=base64_decode($matches[2],true);$size=$bytes?@getimagesizefromstring($bytes):false;
        if(!$size||strlen($bytes)>2*1024*1024||$size[0]*$size[1]>4000000||!in_array($size[2],[IMAGETYPE_PNG,IMAGETYPE_JPEG],true))$fail();
        $image=@imagecreatefromstring($bytes);if(!$image)$fail();
        $scale=min(1,1200/max($size[0],$size[1]));$width=max(1,(int)round($size[0]*$scale));$height=max(1,(int)round($size[1]*$scale));
        $output=imagecreatetruecolor($width,$height);imagealphablending($output,false);imagesavealpha($output,true);
        imagefill($output,0,0,imagecolorallocatealpha($output,255,255,255,127));imagecopyresampled($output,$image,0,0,0,0,$width,$height,$size[0],$size[1]);
        ob_start();imagepng($output,null,9);$png=ob_get_clean();imagedestroy($image);imagedestroy($output);
        return 'data:image/png;base64,'.base64_encode($png);
    }

    public function showCenter(Request $request)
    {
        $this->authorize($request);$input=$request->validate(['academic_year_id'=>['required','integer','exists:tb_academic_year,id'],'exam_center'=>['required','string','max:255']]);
        $year=$this->show($request)->getData(true);
        $center=collect($year['report_centers'])->firstWhere('exam_center',$input['exam_center']);
        abort_unless($center,422,'This exam center is not available in the selected academic year.');
        $row=DB::table('tb_g9_exam_year_setting')->where('academic_year_id',$input['academic_year_id'])->first();$data=json_decode($row?->data??'{}',true);
        $configured=collect($data['report_centers']??[])->firstWhere('exam_center',$input['exam_center'])??[];
        return response()->json(['version'=>$row?->version??0,'exam_center'=>$input['exam_center'],'can_manage'=>$year['can_manage'],
            'location'=>$configured['location']??$year['location'],'department'=>$configured['department']??'',
            'signer'=>$configured['signer']??'ប្រធានមណ្ឌល','signer_name'=>$configured['signer_name']??'',
            'photo_signature'=>$configured['photo_signature']??null,'defaults_dates'=>$year['dates'],
            'dates'=>$this->dateValues(G9ExamCenterSettings::resolve($data,$input['exam_center'])),
            'date_overrides'=>array_keys($configured['dates']??[])])->header('Cache-Control','private, no-store');
    }

    public function saveCenter(Request $request)
    {
        $this->authorize($request);abort_unless($this->canManage($request->user()),403);
        $rules=['academic_year_id'=>['required','integer','exists:tb_academic_year,id'],'exam_center'=>['required','string','max:255'],
            'version'=>['required','integer','min:0'],'location'=>['present','nullable','string','max:150'],
            'department'=>['present','nullable','string','max:255'],'signer'=>['present','nullable','string','max:150'],
            'signer_name'=>['present','nullable','string','max:255'],'photo_signature'=>['sometimes','nullable','string','max:2800000'],
            'dates'=>['required','array:'.implode(',',array_keys(self::SECTIONS))]];
        foreach(self::SECTIONS as $key=>$section){
            $rules['dates.'.$key]=['required','array:inherit,date,solar,lunar'];
            $rules['dates.'.$key.'.inherit']=['required','boolean'];$rules['dates.'.$key.'.date']=['nullable','date_format:Y-m-d'];
            $rules['dates.'.$key.'.solar']=['present','nullable','string','max:1000'];$rules['dates.'.$key.'.lunar']=['present','nullable','string','max:1000'];
        }
        $input=$request->validate($rules);$this->showCenter($request);
        if(array_key_exists('photo_signature',$input))$input['photo_signature']=$this->signature($input['photo_signature'],'photo_signature');
        DB::transaction(function()use($input,$request){
            DB::table('tb_academic_year')->where('id',$input['academic_year_id'])->lockForUpdate()->first();
            $row=DB::table('tb_g9_exam_year_setting')->where('academic_year_id',$input['academic_year_id'])->lockForUpdate()->first();
            abort_if(($row?->version??0)!==$input['version'],409,'These exam-center settings changed in another window. Reload before saving.');
            $data=json_decode($row?->data??'{}',true);$centers=collect($data['report_centers']??[])->keyBy('exam_center');
            $before=$centers->get($input['exam_center'],[]);$center=$before;
            foreach(['exam_center','location','department','signer','signer_name'] as $field)$center[$field]=$input[$field]??'';
            if(array_key_exists('photo_signature',$input))$center['photo_signature']=$input['photo_signature'];
            foreach(self::SECTIONS as $key=>$section){
                $date=$input['dates'][$key];
                if($date['inherit'])unset($center['dates'][$key]);
                else $center['dates'][$key]=['date'=>$date['date']??'','solar'=>$date['solar']??'','lunar'=>$date['lunar']??''];
            }
            $centers->put($input['exam_center'],$center);$data['report_centers']=$centers->values()->all();
            DB::table('tb_g9_exam_year_setting')->updateOrInsert(['academic_year_id'=>$input['academic_year_id']],['data'=>json_encode($data,JSON_THROW_ON_ERROR),'version'=>$input['version']+1,'updated_by'=>$request->user()->id,'created_at'=>$row?->created_at??now(),'updated_at'=>now()]);
            DB::table('tb_g9_exam_setting_event')->insert(['academic_year_id'=>$input['academic_year_id'],'user_id'=>$request->user()->id,'action'=>'save-center-settings',
                'details'=>json_encode(['exam_center'=>$input['exam_center'],'before'=>$before,'after'=>$center],JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
        });
        return $this->showCenter($request);
    }
}
