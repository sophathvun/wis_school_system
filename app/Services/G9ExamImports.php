<?php

namespace App\Services;

use App\Models\{AcademicYear,G9ExamCandidate,SchoolInfo,User};
use App\Support\{G9ExamFields,G9ExamSpreadsheet};
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Validation\ValidationException;

class G9ExamImports
{
    public function campusIds(User $user,string $action): array
    {
        return $user->accessibleCampuses()->get()->filter(fn($c)=>$user->hasPermission('g9-national-exams.view',$c->id)&&$user->hasPermission('g9-national-exams.'.$action,$c->id))->modelKeys();
    }
    public function authorize(User $user,string $action): void {abort_unless($this->campusIds($user,$action),403);}
    private function key(string $value): string {return mb_strtolower(trim(preg_replace('/\s+/u',' ',str_replace(["\u{200B}","\u{FEFF}"],'',$value))));}
    public function date(string $value): string
    {
        if($value==='')return '';
        $value=str_replace(['០','១','២','៣','៤','៥','៦','៧','៨','៩'],range(0,9),$value);
        foreach(['មករា','កុម្ភៈ','មីនា','មេសា','ឧសភា','មិថុនា','កក្កដា','សីហា','កញ្ញា','តុលា','វិច្ឆិកា','ធ្នូ'] as $index=>$month)$value=str_replace($month,sprintf('%02d',$index+1),$value);
        foreach(['Y-m-d','d/m/Y','d-m-Y','d-M-Y','d m Y'] as $format){
            try{$date=\Carbon\CarbonImmutable::createFromFormat('!'.$format,trim($value));$errors=\DateTimeImmutable::getLastErrors();if($date&&(!$errors||(!$errors['warning_count']&&!$errors['error_count'])))return $date->format('Y-m-d');}catch(\Throwable){}
        }
        return $value; // Validation will report an invalid value rather than guessing.
    }
    public function preview(User $user,array $rows): array
    {
        $this->authorize($user,'import');$allowed=$this->campusIds($user,'import');
        $campuses=SchoolInfo::where('status',true)->get();$years=AcademicYear::all();$seen=[];$payload=[];$errors=[];$warnings=[];$yearSettings=[];$sourceSettings=[];$preservedSettings=[];$certificates=[];$new=0;$updates=0;
        foreach($rows as $source){
            $number=$source['_row'];unset($source['_row']);$rowErrors=[];$legacy=[];$shared=[];
            foreach([...G9ExamSpreadsheet::YEAR_FIELDS,...G9ExamSpreadsheet::TRACKING_FIELDS] as $field){
                if(!array_key_exists('legacy_'.$field,$source))continue;
                $value=$source['legacy_'.$field];unset($source['legacy_'.$field]);
                if(in_array($field,G9ExamSpreadsheet::YEAR_FIELDS,true))$shared[$field]=$value;else $legacy[$field]=$value;
            }
            $year=$years->first(fn($year)=>$this->key($year->academic_year)===$this->key((string)($source['academic_year_id']??'')));
            $campusName=(string)($source['campus_id']??'');
            $campusName=['btb'=>'BTB3','ckd'=>'CKD1'][$this->key($campusName)]??$campusName;
            $matches=$campuses->filter(fn($campus)=>$this->key($campus->campus_name_en)===$this->key($campusName)||($campus->campus_name_kh&&$this->key($campus->campus_name_kh)===$this->key($campusName)));
            $campus=$matches->count()===1?$matches->first():null;
            if(!$year)$rowErrors[]='Academic year not found. Configure the academic year first.';
            if(!$campus)$rowErrors[]='Campus name is missing, unknown, or ambiguous.';
            elseif(!in_array($campus->id,$allowed,true))$rowErrors[]='You cannot import records for this campus.';
            $source['academic_year_id']=$year?->id;$source['campus_id']=$campus?->id;
            foreach(['date_of_birth','exam_date'] as $field)if(isset($source[$field]))$source[$field]=$this->date($source[$field]);
            foreach($source as $field=>$value)if($value==='')$source[$field]=null;
            $record=$year?G9ExamCandidate::where('academic_year_id',$year->id)->where('student_code',$source['student_code']??'')->first():null;
            if($year&&$shared){
                if(!isset($yearSettings[$year->id])){
                    $existing=DB::table('tb_g9_exam_year_setting')->where('academic_year_id',$year->id)->first();
                    $saved=json_decode($existing?->data??'{}',true);
                    $yearSettings[$year->id]=['data'=>$saved,'original'=>$saved,'version'=>$existing?->version??0];
                }
                foreach($shared as $field=>$value){
                    if(trim($value)==='')continue;
                    $previous=$sourceSettings[$year->id][$field]??null;
                    if($previous!==null&&$this->key($previous)!==$this->key($value)){
                        $rowErrors[]='Conflicting academic-year setting within the file: '.$field.'. Review the source dates / signers.';
                        continue;
                    }
                    $sourceSettings[$year->id][$field]=$value;
                    if(array_key_exists($field,$yearSettings[$year->id]['original'])){
                        if($this->key((string)$yearSettings[$year->id]['original'][$field])!==$this->key($value)){
                            $preservedSettings[$year->id]['row']??=$number;
                            $preservedSettings[$year->id]['year']=$year->academic_year;
                            $preservedSettings[$year->id]['fields'][$field]=true;
                        }
                    }else $yearSettings[$year->id]['data'][$field]=$value;
                }
            }
            if(isset($legacy['received_cert_Date'])){
                $legacy['received_cert_Date']=$this->date($legacy['received_cert_Date']);
                if($legacy['received_cert_Date']!==''&&!Validator::make(['date'=>$legacy['received_cert_Date']],['date'=>'date_format:Y-m-d'])->passes())$rowErrors[]='Invalid certificate received date.';
            }
            $legacy=array_replace($record?->legacy_data??[],$legacy);
            $certificate=trim((string)($legacy['certificate_number']??''));
            if($certificate!==''&&$year){
                $certificateKey=$year->id.':'.$certificate;
                if(isset($certificates[$certificateKey]))$rowErrors[]='Duplicate certificate number in this file.';
                $certificates[$certificateKey]=true;
                if(G9ExamCandidate::where('academic_year_id',$year->id)->where('legacy_data->certificate_number',$certificate)->when($record,fn($q)=>$q->where('id','!=',$record->id))->exists())$rowErrors[]='This certificate number is already assigned to another candidate.';
            }
            $received=mb_strtolower(trim($legacy['received_cert']??''));
            if($received==='withdrew'&&(!($legacy['received_cert_Date']??'')||!($legacy['received_by']??'')))$warnings[]=['row'=>$number,'message'=>'Received certificate has no received date or recipient. Original values are preserved for review.'];
            if($received==='failed'&&$certificate!=='')$warnings[]=['row'=>$number,'message'=>'Failed candidate has a historical certificate number. Preserved for review; no new certificate is issued.'];
            $action=$record?'edit':'create';
            if($record&&!in_array($record->campus_id,$allowed,true))$rowErrors[]='An existing record for this student belongs to another campus.';
            if($campus&&!$user->hasPermission('g9-national-exams.'.$action,$campus->id))$rowErrors[]='Missing permission to '.$action.' candidates in this campus.';
            $key=($year?->id??'').':'.($source['student_code']??'');if(isset($seen[$key]))$rowErrors[]='Duplicate student and academic year in this file (row '.$seen[$key].').';$seen[$key]=$number;
            $input=$record?array_replace($record->data,$record->only(['academic_year_id','campus_id','student_code','name_kh','name_en']),$source):$source;
            $validation=Validator::make($input,G9ExamFields::rules());if($validation->fails())$rowErrors=array_merge($rowErrors,$validation->errors()->all());
            if($rowErrors){$errors[]=['row'=>$number,'student_code'=>$source['student_code']??'','messages'=>array_values(array_unique($rowErrors))];continue;}
            $input=$validation->validated();$payload[]=['row'=>$number,'id'=>$record?->id,'version'=>$record?->version,'input'=>$input,'legacy'=>$legacy];
            $record?$updates++:$new++;
        }
        foreach($preservedSettings as $setting)$warnings[]=['row'=>$setting['row'],'message'=>'Existing G9 Setting values for '.$setting['year'].' will be kept: '.implode(', ',array_keys($setting['fields'])).'. The imported candidates will use the saved academic-year settings.'];
        foreach($yearSettings as $id=>&$setting){
            if($setting['version']>0&&$setting['data']===$setting['original']){unset($yearSettings[$id]);continue;}
            unset($setting['original']);
        }unset($setting);
        // Only new or missing shared settings travel once per year in the payload.
        foreach($payload as &$row){$yearId=$row['input']['academic_year_id'];if(isset($yearSettings[$yearId])){$row['year_setting']=$yearSettings[$yearId];unset($yearSettings[$yearId]);}}unset($row);
        return ['payload'=>$payload,'summary'=>['total'=>count($rows),'new'=>$new,'updates'=>$updates,'errors'=>$errors,'warnings'=>$warnings]];
    }
}
