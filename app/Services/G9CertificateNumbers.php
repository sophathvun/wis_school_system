<?php

namespace App\Services;

use App\Models\{AcademicYear,G9ExamCandidate,G9ExamCertificateTracking,User};
use App\Support\{G9NationalExamPermissions as Access,G9ScoreSummary};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Crypt,DB,Validator};
use Illuminate\Validation\{Rule,ValidationException};

class G9CertificateNumbers
{
    public const GROUPS=[
        'pp_khmer'=>'Phnom Penh · Khmer','pp_english'=>'Phnom Penh · English',
        'province_english'=>'Province · English','province_khmer'=>'Province · Khmer',
    ];

    public static function ascii(string $value): string {return strtr(trim($value),array_combine(mb_str_split('០១២៣៤៥៦៧៨៩'),range(0,9)));}
    public static function khmer(string $value): string {return strtr($value,array_combine(range(0,9),mb_str_split('០១២៣៤៥៦៧៨៩')));}

    public static function prefix(string $year): string
    {
        $year=self::ascii($year);
        if(!preg_match('/^(\d{4})\s*[-–\/]\s*(\d{4})$/u',$year,$m)||(int)$m[2]!==((int)$m[1]+1))
            throw ValidationException::withMessages(['academic_year'=>'Choose an academic year in YYYY-YYYY format, such as 2025-2026.']);
        return '9'.substr($m[2],-2);
    }

    public function authorize(User $user,int $year): void
    {
        $allowed=Access::campusIds($user,'certificates-number-manage');abort_unless($allowed,403);
        $campuses=G9ExamCandidate::where('academic_year_id',$year)->distinct()->pluck('campus_id')->map(fn($id)=>(int)$id)->all();
        abort_if(array_diff($campuses,$allowed),403,'Certificate numbering requires permission for every campus in this academic year.');
    }

    private function collator(): \Collator
    {
        if(!class_exists(\Collator::class))throw ValidationException::withMessages(['sorting'=>'Khmer name sorting requires the PHP intl extension.']);
        $sort=new \Collator('km_KH');$sort->setStrength(\Collator::SECONDARY);return $sort;
    }

    private function name(string $value): string
    {
        return \Normalizer::normalize(trim(preg_replace('/\s+/u',' ',str_replace(["\u{200B}","\u{FEFF}"],'',$value))),\Normalizer::FORM_C);
    }

    private function candidates(int $year,bool $locked=false): Collection
    {
        return G9ExamCandidate::where('academic_year_id',$year)->orderBy('id')->when($locked,fn($q)=>$q->lockForUpdate())->with(['campus','certificateTracking','certificateNumber'])->get();
    }

    public function configuration(User $user,int $year): array
    {
        $this->authorize($user,$year);
        return $this->config($year,$this->candidates($year));
    }

    private function config(int $year,Collection $candidates): array
    {
        $label=AcademicYear::findOrFail($year)->academic_year;$prefix=self::prefix($label);
        $saved=DB::table('tb_g9_exam_certificate_number_setting')->where('academic_year_id',$year)->first();
        $config=json_decode($saved?->config??'{}',true);$configured=collect($config['centers']??[])->keyBy('exam_center');
        $dates=json_decode(DB::table('tb_g9_exam_year_setting')->where('academic_year_id',$year)->value('data')??'{}',true);
        $headings=collect($dates['report_centers']??[])->keyBy('exam_center');$sort=$this->collator();
        $centers=$candidates->pluck('data.exam_center')->filter(fn($v)=>trim((string)$v)!=='')->unique()
            ->sort(fn($a,$b)=>$sort->compare($this->name($a),$this->name($b)))->values()->map(function($center,$i)use($configured,$headings){
                $saved=$configured->get($center,[]);$location=$headings->get($center)['location']??'';$text=mb_strtolower($location.' '.$center);
                $region=preg_match('/ខេត្ត|province/iu',$text)?'province':(preg_match('/ភ្នំពេញ|phnom\s*penh/iu',$text)?'pp':'');
                return ['exam_center'=>$center,'location'=>$location,'region'=>$saved['region']??$region,'priority'=>$saved['priority']??$i+1];
            })->all();
        return ['academic_year'=>$year,'year_label'=>$label,'prefix'=>self::khmer($prefix),'starting_sequence'=>(int)($saved?->starting_sequence??1),
            'sequence_digits'=>4,'version'=>(int)($saved?->version??0),'group_order'=>$config['group_order']??array_keys(self::GROUPS),
            'group_labels'=>self::GROUPS,'centers'=>$centers];
    }

    public function saveSettings(User $user,int $year,array $input): array
    {
        $this->authorize($user,$year);
        $data=Validator::make($input,[
            'version'=>'required|integer|min:0','starting_sequence'=>'required|integer|min:1|max:9999',
            'group_order'=>'required|array|size:4','group_order.*'=>['required','distinct',Rule::in(array_keys(self::GROUPS))],
            'centers'=>'present|array|max:300','centers.*.exam_center'=>'required|string|max:255|distinct',
            'centers.*.region'=>['nullable',Rule::in(['pp','province'])],'centers.*.priority'=>'required|integer|min:1|max:9999',
        ])->validate();
        return DB::transaction(function()use($user,$year,$data){
            DB::table('tb_academic_year')->where('id',$year)->lockForUpdate()->firstOrFail();
            $this->authorize($user,$year);$records=$this->candidates($year);$current=$this->config($year,$records);
            abort_unless($current['version']===(int)$data['version'],409,'These number settings changed. Reload them before saving.');
            $known=array_column($current['centers'],'exam_center');
            foreach($data['centers'] as $center)if(!in_array($center['exam_center'],$known,true))$this->fail('centers','An exam center changed. Reload settings before saving.');
            $centers=collect($current['centers'])->keyBy('exam_center');
            foreach($data['centers'] as $center)$centers->put($center['exam_center'],['exam_center'=>$center['exam_center'],'region'=>$center['region']??'','priority'=>(int)$center['priority']]);
            $config=['group_order'=>$data['group_order'],'centers'=>$centers->values()->all()];
            $row=DB::table('tb_g9_exam_certificate_number_setting')->where('academic_year_id',$year)->first();
            DB::table('tb_g9_exam_certificate_number_setting')->updateOrInsert(['academic_year_id'=>$year],[
                'starting_sequence'=>(int)$data['starting_sequence'],'config'=>json_encode($config,JSON_UNESCAPED_UNICODE),'version'=>$current['version']+1,
                'updated_by'=>$user->id,'created_at'=>$row?->created_at??now(),'updated_at'=>now(),
            ]);
            $this->event($user,$year,'settings-saved',['before'=>$current,'after'=>$config,'starting_sequence'=>$data['starting_sequence']]);
            return $this->config($year,$records);
        });
    }

    private function existing(G9ExamCandidate $candidate): string
    {
        return trim((string)($candidate->certificateNumber?->number?:($candidate->certificateTracking?->number?:($candidate->legacy_data['certificate_number']??''))));
    }

    private function plan(int $year,Collection $records): array
    {
        $settings=$this->config($year,$records);$prefix=self::ascii($settings['prefix']);$centers=collect($settings['centers'])->keyBy('exam_center');
        $order=array_flip($settings['group_order']);$sort=$this->collator();$highest=0;$reserved=[];$input=[];
        foreach($records as $r){
            $number=$this->existing($r);$normalized=self::ascii($number);
            if($normalized!==''){
                if(isset($reserved[$normalized]))$this->fail('number','Existing certificate numbers are duplicated in this year. Resolve the duplicates before generating new numbers.');
                $reserved[$normalized]=$r->id;
                if(preg_match('/^'.preg_quote($prefix,'/').'(\d{3,4})$/',$normalized,$m))$highest=max($highest,(int)$m[1]);
            }
            // Include non-eligible records in the fingerprint, but never in the numbered list.
            $input[]=[$r->id,$r->campus_id,$r->name_kh,$r->data,$r->attendance_status,$number,$r->certificateTracking?->version];
        }
        $highest=max($highest,(int)DB::table('tb_g9_exam_certificate_number')->where('academic_year_id',$year)->max('sequence'));
        $eligible=$records->filter(fn($r)=>app(G9CertificateTracking::class)->outcome($r)==='passed')->map(function($r)use($centers,$order){
            $center=$centers->get($r->data['exam_center']??'');
            if(!$center)$this->fail('centers','Assign an exam center to every passed student before previewing certificate numbers.');
            if(!in_array($center['region'],['pp','province'],true))$this->fail('centers','Select Phnom Penh or Province for '.$center['exam_center'].'.');
            if($this->name($r->name_kh??'')==='')$this->fail('name_kh','Enter a Khmer name for every passed student before previewing certificate numbers.');
            $language=G9ScoreSummary::calculate($r->data)['language'];$group=$center['region'].'_'.$language;
            return ['candidate'=>$r,'group'=>$group,'rank'=>$order[$group],'priority'=>$center['priority']];
        })->sort(function($a,$b)use($sort){
            return ($a['rank']<=>$b['rank'])?:($a['priority']<=>$b['priority'])
                ?:($sort->compare($this->name($a['candidate']->data['exam_center']),$this->name($b['candidate']->data['exam_center'])))
                ?:($sort->compare($this->name($a['candidate']->name_kh),$this->name($b['candidate']->name_kh)))
                ?:strnatcasecmp($a['candidate']->student_code,$b['candidate']->student_code)?:($a['candidate']->id<=>$b['candidate']->id);
        })->values();
        $next=max($highest+1,$settings['starting_sequence']);$rows=[];$new=0;$existing=0;
        foreach($eligible as $entry){
            $r=$entry['candidate'];$number=$this->existing($r);$isNew=$number==='';$sequence=null;
            if($isNew){
                if($next>9999)$this->fail('starting_sequence','The four-digit sequence is exhausted for this academic year (maximum ៩៩៩៩).');
                $sequence=$next++;$number=self::khmer($prefix.str_pad((string)$sequence,4,'0',STR_PAD_LEFT));$new++;
            }else $existing++;
            $rows[]=['id'=>$r->id,'student_code'=>$r->student_code,'name_kh'=>$r->name_kh,'campus'=>$r->campus?->campus_name_en??'',
                'exam_center'=>$r->data['exam_center'],'group'=>$entry['group'],'group_label'=>self::GROUPS[$entry['group']],
                'number'=>$number,'sequence'=>$sequence,'is_new'=>$isNew];
        }
        return ['settings'=>$settings,'rows'=>$rows,'summary'=>['passed'=>$eligible->count(),'new'=>$new,'existing'=>$existing,'excluded'=>$records->count()-$eligible->count()],
            'fingerprint'=>hash('sha256',json_encode([$settings,$input,$highest,$rows],JSON_UNESCAPED_UNICODE))];
    }

    public function preview(User $user,int $year): array
    {
        $this->authorize($user,$year);$plan=$this->plan($year,$this->candidates($year));
        $plan['preview_token']=Crypt::encryptString(json_encode(['user'=>$user->id,'year'=>$year,'hash'=>$plan['fingerprint'],'expires'=>now()->addMinutes(20)->timestamp]));
        unset($plan['fingerprint']);return $plan;
    }

    public function generate(User $user,int $year,string $token): array
    {
        $this->authorize($user,$year);
        try{$preview=json_decode(Crypt::decryptString($token),true,512,JSON_THROW_ON_ERROR);}catch(\Throwable){$this->fail('preview','Preview the certificate numbers before generating them.');}
        abort_unless(($preview['user']??null)===$user->id&&($preview['year']??null)===$year&&($preview['expires']??0)>=now()->timestamp,409,'This preview expired. Preview the numbers again.');
        return DB::transaction(function()use($user,$year,$preview){
            DB::table('tb_academic_year')->where('id',$year)->lockForUpdate()->firstOrFail();
            $this->authorize($user,$year);$records=$this->candidates($year,true);$plan=$this->plan($year,$records);
            abort_unless(hash_equals($preview['hash'],$plan['fingerprint']),409,'Students, results, certificate numbers or settings changed. Review a fresh preview before generating.');
            foreach($plan['rows'] as $row){
                if(!$row['is_new'])continue;
                DB::table('tb_g9_exam_certificate_number')->insert(['candidate_id'=>$row['id'],'academic_year_id'=>$year,'sequence'=>$row['sequence'],
                    'number'=>$row['number'],'generated_by'=>$user->id,'created_at'=>now(),'updated_at'=>now()]);
                $candidate=$records->firstWhere('id',$row['id']);$tracking=$candidate->certificateTracking;
                if($tracking)$tracking->update(['number'=>$row['number'],'version'=>$tracking->version+1,'updated_by'=>$user->id]);
                DB::table('tb_g9_exam_event')->insert(['candidate_id'=>$row['id'],'user_id'=>$user->id,'action'=>'certificate-number-generated',
                    'details'=>json_encode(['number'=>$row['number'],'sequence'=>$row['sequence'],'group'=>$row['group']]),'created_at'=>now(),'updated_at'=>now()]);
            }
            $this->event($user,$year,'numbers-generated',['summary'=>$plan['summary'],'preview_hash'=>$preview['hash']]);
            return ['message'=>$plan['summary']['new'].' certificate numbers generated. Existing numbers were kept.','generated'=>$plan['summary']['new']];
        },3);
    }

    public function validateTrackingNumber(G9ExamCandidate $candidate,string $number,string $before): void
    {
        if($before!==''&&self::ascii($number)!==self::ascii($before))$this->fail('number','An assigned certificate number stays fixed.');
        if($number==='')return;
        foreach($this->candidates($candidate->academic_year_id) as $other)
            if($other->id!==$candidate->id&&self::ascii($this->existing($other))===self::ascii($number))$this->fail('number','This certificate number is already assigned to another student in this academic year.');
    }

    private function event(User $user,int $year,string $action,array $details): void
    {
        DB::table('tb_g9_exam_certificate_number_event')->insert(['academic_year_id'=>$year,'user_id'=>$user->id,'action'=>$action,'details'=>json_encode($details,JSON_UNESCAPED_UNICODE),'created_at'=>now(),'updated_at'=>now()]);
    }

    private function fail(string $field,string $message): never {throw ValidationException::withMessages([$field=>$message]);}
}
