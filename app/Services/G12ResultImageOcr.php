<?php
namespace App\Services;

use App\Models\{G12ExamImport,G12ExamRecord,G12OcrSetting,G12OcrUsage,User};
use App\Support\G12NationalExamPermissions as Access;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Crypt,DB,Http,Storage};
use Illuminate\Validation\ValidationException;

class G12ResultImageOcr {
    public function setting(): G12OcrSetting {return G12OcrSetting::firstOrCreate(['id'=>1],['enabled'=>false,'monthly_limit'=>999]);}
    public function status(User $user): array
    {
        $setting=$this->setting();$month=now()->format('Y-m');$used=G12OcrUsage::where('billing_month',$month)->count();
        return ['enabled'=>$setting->enabled,'monthly_limit'=>$setting->monthly_limit,'used'=>$used,'remaining'=>max(0,$setting->monthly_limit-$used),
            'project_id'=>$setting->project_id,'service_account_email'=>$setting->service_account_email,'school_aliases'=>$setting->school_aliases,
            'configured'=>filled($setting->credentials),'can_configure'=>Access::allows($user,'scoring-ocr-settings')];
    }
    public function saveSetting(User $user,array $input,?UploadedFile $credentials): array
    {
        abort_unless(Access::allows($user,'scoring-ocr-settings'),403);$setting=$this->setting();
        if($credentials){
            $json=json_decode($credentials->get(),true);
            if(!is_array($json)||($json['type']??'')!=='service_account'||blank($json['client_email']??null)||blank($json['private_key']??null)||($json['token_uri']??'')!=='https://oauth2.googleapis.com/token')throw ValidationException::withMessages(['credentials'=>'Choose a valid Google Cloud service-account JSON key.']);
            $setting->credentials=Crypt::encryptString(json_encode($json,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));$setting->project_id=$json['project_id']??null;$setting->service_account_email=$json['client_email'];
        }
        if(($input['enabled']??false)&&blank($setting->credentials))throw ValidationException::withMessages(['enabled'=>'Upload a service-account JSON key before enabling image scanning.']);
        $setting->fill(['enabled'=>(bool)($input['enabled']??false),'monthly_limit'=>(int)($input['monthly_limit']??999),'school_aliases'=>trim((string)($input['school_aliases']??''))?:null,'updated_by'=>$user->id])->save();
        app(G12NationalExams::class)->event($user,'ocr-settings-updated',['enabled'=>$setting->enabled,'monthly_limit'=>$setting->monthly_limit,'project_id'=>$setting->project_id]);return $this->status($user);
    }
    public function upload(User $user,array $context,array $images): G12ExamImport
    {
        abort_unless(Access::allows($user,'scoring-ocr-upload'),403);$setting=$this->setting();
        if(!$setting->enabled)throw ValidationException::withMessages(['images'=>'Image scanning is currently turned off. Use manual entry or CSV import.']);
        if(blank($setting->credentials))throw ValidationException::withMessages(['images'=>'Google Cloud Vision is not configured.']);
        $count=count($images);if(!$count||$count>250)throw ValidationException::withMessages(['images'=>'Choose between 1 and 250 images per batch.']);
        $month=now()->format('Y-m');$batch=null;$stored=[];
        try{
            DB::transaction(function()use($user,$context,$images,$setting,$count,$month,&$batch,&$stored){
                $locked=G12OcrSetting::lockForUpdate()->findOrFail($setting->id);$used=G12OcrUsage::where('billing_month',$month)->count();
                if($used+$count>$locked->monthly_limit)throw ValidationException::withMessages(['images'=>'This batch needs '.$count.' OCR units, but only '.max(0,$locked->monthly_limit-$used).' remain this month.']);
                $entries=[];foreach($images as $index=>$image){$path=$image->store('g12_ocr/'.now()->format('Y-m'),'local');$stored[]=$path;$entries[]=['index'=>$index,'name'=>$image->getClientOriginalName(),'path'=>$path,'status'=>'pending','rows'=>[],'error'=>null];}
                $batch=G12ExamImport::create(['user_id'=>$user->id,'kind'=>'score_ocr','filename'=>$count.' result image'.($count===1?'':'s'),'payload'=>['context'=>$context,'entries'=>$entries,'rows'=>[]],'expires_at'=>now()->addDays(7)]);
                foreach($entries as $entry)G12OcrUsage::create(['billing_month'=>$month,'import_id'=>$batch->id,'image_index'=>$entry['index'],'user_id'=>$user->id,'status'=>'reserved','sha256'=>hash_file('sha256',Storage::disk('local')->path($entry['path']))]);
            });
        }catch(\Throwable $e){foreach($stored as $path)Storage::disk('local')->delete($path);throw $e;}
        app(G12NationalExams::class)->event($user,'ocr-batch-uploaded',['batch_id'=>$batch->id,'images'=>$count,'context'=>$context]);return $batch;
    }
    public function processNext(User $user,G12ExamImport $batch): array
    {
        abort_unless($batch->user_id===$user->id||Access::allows($user,'scoring-ocr-review'),403);abort_unless(Access::allows($user,'scoring-ocr-upload'),403);
        if($batch->kind!=='score_ocr'||$batch->status!=='preview'||$batch->expires_at->isPast())throw ValidationException::withMessages(['images'=>'This image batch expired or was already confirmed.']);
        $payload=$batch->payload;$position=collect($payload['entries'])->search(fn($entry)=>$entry['status']==='pending');
        if($position===false)return $this->preview($user,$batch);
        $entry=$payload['entries'][$position];$usage=G12OcrUsage::where('import_id',$batch->id)->where('image_index',$entry['index'])->firstOrFail();$usage->update(['status'=>'processing']);
        try{
            $annotation=$this->annotate(Storage::disk('local')->get($entry['path']));$rows=$this->parse($annotation,$payload['context'],$user);
            $entry['status']='processed';$entry['rows']=$rows;$usage->update(['status'=>'used']);
        }catch(\Throwable $e){$entry['status']='failed';$entry['error']=mb_substr($e->getMessage(),0,1000);$usage->update(['status'=>'failed','error'=>$entry['error']]);}
        $payload['entries'][$position]=$entry;$payload['rows']=collect($payload['entries'])->flatMap(fn($item)=>$item['rows']??[])->values()->all();$batch->update(['payload'=>$payload]);return $this->preview($user,$batch->fresh());
    }
    private function annotate(string $bytes): array
    {
        $setting=$this->setting();$key=json_decode(Crypt::decryptString($setting->credentials),true,512,JSON_THROW_ON_ERROR);$now=time();
        $encode=fn(string $value)=>rtrim(strtr(base64_encode($value),'+/','-_'),'=');
        $header=$encode(json_encode(['alg'=>'RS256','typ'=>'JWT'],JSON_THROW_ON_ERROR));$claims=$encode(json_encode(['iss'=>$key['client_email'],'scope'=>'https://www.googleapis.com/auth/cloud-platform','aud'=>$key['token_uri'],'iat'=>$now,'exp'=>$now+3600],JSON_THROW_ON_ERROR));
        if(!openssl_sign($header.'.'.$claims,$signature,$key['private_key'],OPENSSL_ALGO_SHA256))throw new \RuntimeException('The Google service-account key could not sign an OCR request.');
        $token=Http::asForm()->timeout(30)->post($key['token_uri'],['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$header.'.'.$claims.'.'.$encode($signature)])->throw()->json('access_token');
        if(!$token)throw new \RuntimeException('Google did not return an access token.');
        $response=Http::withToken($token)->timeout(60)->post('https://vision.googleapis.com/v1/images:annotate',['requests'=>[['image'=>['content'=>base64_encode($bytes)],'features'=>[['type'=>'DOCUMENT_TEXT_DETECTION']],'imageContext'=>['languageHints'=>['km','en']]]]])->throw()->json('responses.0');
        if($message=data_get($response,'error.message'))throw new \RuntimeException($message);return $response??[];
    }
    private function parse(array $annotation,array $context,User $user): array
    {
        $words=[];foreach(data_get($annotation,'fullTextAnnotation.pages',[]) as $page)foreach($page['blocks']??[] as $block)foreach($block['paragraphs']??[] as $paragraph)foreach($paragraph['words']??[] as $word){
            $text=collect($word['symbols']??[])->pluck('text')->implode('');$vertices=data_get($word,'boundingBox.vertices',[]);$x=collect($vertices)->avg(fn($v)=>$v['x']??0);$y=collect($vertices)->avg(fn($v)=>$v['y']??0);$h=max(8,(collect($vertices)->max(fn($v)=>$v['y']??0)-collect($vertices)->min(fn($v)=>$v['y']??0)));if($text!=='')$words[]=compact('text','x','y','h');
        }
        usort($words,fn($a,$b)=>$a['y']<=>$b['y']?:$a['x']<=>$b['x']);$lines=[];
        foreach($words as $word){$match=null;foreach($lines as $i=>$line)if(abs($line['y']-$word['y'])<=max($line['h'],$word['h'])*.65){$match=$i;break;}if($match===null)$lines[]=['y'=>$word['y'],'h'=>$word['h'],'words'=>[$word]];else{$lines[$match]['words'][]=$word;$lines[$match]['y']=($lines[$match]['y']+$word['y'])/2;}}
        $records=G12ExamRecord::where('academic_year',$context['academic_year'])->where('center_id',$context['center_id'])->whereIn('campus_id',Access::campusIds($user,'scoring-ocr-review'))->get();
        if(filled($context['room']??null))$records=$records->filter(fn($r)=>trim((string)($r->data['g12_exam_room']??''))===trim((string)$context['room']))->values();
        $subjectService=app(G12ScoringImports::class);$rows=[];
        foreach($lines as $line){usort($line['words'],fn($a,$b)=>$a['x']<=>$b['x']);$tokens=array_column($line['words'],'text');$letters=array_values(array_filter(array_map(fn($v)=>preg_match('/^[A-F]$/i',trim($v))?mb_strtoupper(trim($v)):null,$tokens)));if(count($letters)<3)continue;
            $text=implode(' ',$tokens);$seat=null;foreach($tokens as $token){$number=$this->latinDigits(trim($token));if(preg_match('/^\d{1,4}$/',$number)){$seat=ltrim($number,'0')?:'0';break;}}
            $matches=$seat===null?collect():$records->filter(fn($r)=>(ltrim($this->latinDigits(trim((string)($r->data['g12_exam_seat']??''))),'0')?:'0')===$seat);$record=$matches->count()===1?$matches->first():null;
            $subjects=$record?$subjectService->subjects($record):$this->subjectsForContext($context);$needed=count($subjects);if(count($letters)>$needed)$overall=array_pop($letters);else $overall='';$letters=array_slice($letters,-$needed);$scores=[];foreach(array_keys($subjects) as $i=>$key)$scores[$key]=$letters[$i]??'';
            $rows[]=['image_line'=>$text,'seat'=>$seat,'record_id'=>$record?->id,'student_id'=>$record?->student_code,'student_name'=>$record?->name_kh??$record?->name_en,'match_status'=>$record?'matched':($matches->count()>1?'ambiguous':'unmatched'),'scores'=>$scores,'score_rank'=>'','letter_grade'=>$overall,'version'=>$record?->version,'issues'=>$record ? [] : ['Select the matching student before import']];
        }return $rows;
    }
    private function subjectsForContext(array $context): array
    {
        $language=mb_strtolower((string)($context['language_exam']??''));$selected=mb_strtolower((string)($context['class_selected']??''));$social=str_contains($selected,'social')||str_contains($selected,'សង្គម');$keys=$social?['khmer','math','earthscience','geography','history','values']:['khmer','math','biology','history','chemistry','physics'];if(str_contains($language,'khmer')||str_contains($language,'ខ្មែរ'))$keys[]='english';return collect($keys)->mapWithKeys(fn($key)=>[$key=>G12ScoringImports::SUBJECTS[$key]])->all();
    }
    private function latinDigits(string $value): string {return strtr($value,['០'=>'0','១'=>'1','២'=>'2','៣'=>'3','៤'=>'4','៥'=>'5','៦'=>'6','៧'=>'7','៨'=>'8','៩'=>'9']);}
    public function preview(User $user,G12ExamImport $batch): array
    {
        abort_unless($batch->user_id===$user->id||Access::allows($user,'scoring-ocr-review'),403);$payload=$batch->payload;$entries=$payload['entries']??[];
        return ['id'=>$batch->id,'status'=>$batch->status,'context'=>$payload['context']??[],'total_images'=>count($entries),'processed_images'=>collect($entries)->whereIn('status',['processed','failed'])->count(),'failed_images'=>collect($entries)->where('status','failed')->count(),'pending_images'=>collect($entries)->where('status','pending')->count(),'rows'=>$payload['rows']??[],'entries'=>collect($entries)->map(fn($e)=>collect($e)->only(['index','name','status','error']))->values(),'usage'=>$this->status($user)];
    }
    public function confirm(User $user,G12ExamImport $batch,array $rows): array
    {
        abort_unless(Access::allows($user,'scoring-ocr-review'),403);
        return DB::transaction(function()use($user,$batch,$rows){$batch=G12ExamImport::lockForUpdate()->findOrFail($batch->id);if($batch->kind!=='score_ocr'||$batch->status!=='preview')throw ValidationException::withMessages(['rows'=>'This OCR batch was already confirmed.']);$updated=0;$seen=[];$context=$batch->payload['context']??[];
            foreach($rows as $row){if(empty($row['record_id']))continue;$record=G12ExamRecord::lockForUpdate()->findOrFail((int)$row['record_id']);
                if(isset($seen[$record->id]))throw ValidationException::withMessages(['rows'=>'The same student is selected more than once. Review duplicate result rows.']);$seen[$record->id]=true;
                if($record->academic_year!==($context['academic_year']??null)||(int)$record->center_id!==(int)($context['center_id']??0))throw ValidationException::withMessages(['rows'=>'Student '.$record->student_code.' is outside the selected academic year or exam center.']);
                if(filled($context['room']??null)&&trim((string)($record->data['g12_exam_room']??''))!==trim((string)$context['room']))throw ValidationException::withMessages(['rows'=>'Student '.$record->student_code.' is outside the selected exam room.']);
                $expected=array_keys(app(G12ScoringImports::class)->subjects($record));$provided=array_keys($row['scores']??[]);sort($expected);sort($provided);if($expected!==$provided)throw ValidationException::withMessages(['rows'=>'The subject columns do not match student '.$record->student_code.' exam language and selected class.']);
                app(G12ScoringImports::class)->apply($user,$record,$row,'scores-ocr-imported');$updated++;}
            if(!$updated)throw ValidationException::withMessages(['rows'=>'Select at least one matched student to import.']);$summary=['images'=>count($batch->payload['entries']??[]),'rows'=>count($rows),'updated'=>$updated];$batch->update(['status'=>'confirmed','summary'=>$summary,'confirmed_at'=>now()]);app(G12NationalExams::class)->event($user,'scores-ocr-confirmed',['batch_id'=>$batch->id,'summary'=>$summary]);return $summary;});
    }
}
