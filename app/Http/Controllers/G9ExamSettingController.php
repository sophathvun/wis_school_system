<?php

namespace App\Http\Controllers;

use App\Models\{AcademicYear,User};
use App\Services\G9ExamImports;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class G9ExamSettingController
{
    public const SECTIONS=[
        'listDate'=>['label'=>'Candidate List Issue Date','lunar'=>'k_listdate'],
        'resultDate'=>['label'=>'Result Issue Date','lunar'=>'k_resultDate'],
        'resultDateDOEYS'=>['label'=>'DoEYS Result Issue Date','lunar'=>'k_resultDateDOEYS'],
        'cardDate'=>['label'=>'Student Card Issue Date','lunar'=>'k_cardDate'],
        'certDate'=>['label'=>'Certificate Issue Date','lunar'=>'k_certDate'],
    ];

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
            'configuredYear'=>DB::table('tb_g9_exam_year_setting')->orderByDesc('updated_at')->value('academic_year_id')]);
    }
    public function show(Request $request)
    {
        $this->authorize($request);$input=$request->validate(['academic_year_id'=>['required','integer','exists:tb_academic_year,id']]);
        $setting=DB::table('tb_g9_exam_year_setting')->where('academic_year_id',$input['academic_year_id'])->first();$data=json_decode($setting?->data??'{}',true);$dates=[];
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
        $configured=collect($data['report_centers']??[])->keyBy('exam_center');
        $centers=\App\Models\G9ExamCandidate::where('academic_year_id',$input['academic_year_id'])->whereIn('campus_id',app(G9ExamImports::class)->campusIds($request->user(),'settings'))->get(['data'])
            ->map(fn($r)=>trim((string)($r->data['exam_center']??'')))->filter()->merge($configured->keys())->unique()->sort()->values()
            ->map(fn($center)=>$configured->get($center,['exam_center'=>$center,'location'=>'','department'=>'','signer'=>'ប្រធានមណ្ឌល']));
        return response()->json(['version'=>$setting?->version??0,'location'=>$data['date_location']??'រាជធានីភ្នំពេញ','dates'=>$dates,'report_centers'=>$centers,'can_manage'=>$this->canManage($request->user())]);
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
            $value=$center['photo_signature'];
            if(!$value){$input['report_centers'][$index]['photo_signature']=null;continue;}
            $field='report_centers.'.$index.'.photo_signature';
            $fail=fn()=>throw \Illuminate\Validation\ValidationException::withMessages([$field=>'Choose a valid PNG or JPG signature image, up to 2 MB and 4 megapixels.']);
            if(!preg_match('~^data:image/(png|jpeg);base64,([A-Za-z0-9+/=]+)$~',$value,$matches))$fail();
            $bytes=base64_decode($matches[2],true);$size=$bytes?@getimagesizefromstring($bytes):false;
            if(!$size||strlen($bytes)>2*1024*1024||$size[0]*$size[1]>4000000||!in_array($size[2],[IMAGETYPE_PNG,IMAGETYPE_JPEG],true))$fail();
            $image=@imagecreatefromstring($bytes);if(!$image)$fail();
            $scale=min(1,1200/max($size[0],$size[1]));$width=max(1,(int)round($size[0]*$scale));$height=max(1,(int)round($size[1]*$scale));
            $output=imagecreatetruecolor($width,$height);imagealphablending($output,false);imagesavealpha($output,true);
            imagefill($output,0,0,imagecolorallocatealpha($output,255,255,255,127));imagecopyresampled($output,$image,0,0,0,0,$width,$height,$size[0],$size[1]);
            ob_start();imagepng($output,null,9);$png=ob_get_clean();imagedestroy($image);imagedestroy($output);
            $input['report_centers'][$index]['photo_signature']='data:image/png;base64,'.base64_encode($png);
        }
        DB::transaction(function()use($input,$request){
            DB::table('tb_academic_year')->where('id',$input['academic_year_id'])->lockForUpdate()->first();
            $row=DB::table('tb_g9_exam_year_setting')->where('academic_year_id',$input['academic_year_id'])->lockForUpdate()->first();
            abort_if(($row?->version??0)!==$input['version'],409,'These shared dates changed in another window. Reload the academic year before saving.');
            $before=json_decode($row?->data??'{}',true);$data=$before;$data['date_location']=$input['location'];
            if(array_key_exists('report_centers',$input)){
                $existing=collect($before['report_centers']??[])->keyBy('exam_center');
                $data['report_centers']=array_map(fn($center)=>['exam_center'=>$center['exam_center'],'location'=>$center['location']??'','department'=>$center['department']??'','signer'=>($center['signer']??'')?:'ប្រធានមណ្ឌល',
                    'photo_signature'=>array_key_exists('photo_signature',$center)?$center['photo_signature']:($existing->get($center['exam_center'])['photo_signature']??null)],$input['report_centers']);
            }
            foreach(self::SECTIONS as $key=>$section){$date=$input['dates'][$key];$data[$key.'_iso']=$date['date']??'';$data[$key]=$date['solar']??'';$data[$section['lunar']]=$date['lunar']??'';}
            DB::table('tb_g9_exam_year_setting')->updateOrInsert(['academic_year_id'=>$input['academic_year_id']],['data'=>json_encode($data,JSON_THROW_ON_ERROR),'version'=>$input['version']+1,'updated_by'=>$request->user()->id,'created_at'=>$row?->created_at??now(),'updated_at'=>now()]);
            DB::table('tb_g9_exam_setting_event')->insert(['academic_year_id'=>$input['academic_year_id'],'user_id'=>$request->user()->id,'action'=>'save-dates','details'=>json_encode(['before'=>$before,'after'=>$data],JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
        });
        return $this->show($request);
    }
}
