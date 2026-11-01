<?php
namespace App\Services;

use App\Models\{G12ExamImport,G12ExamRecord,User};
use App\Support\G12NationalExamPermissions as Access;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;

class G12ScoringImports {
    public const SUBJECTS=['khmer'=>'Khmer','math'=>'Math','biology'=>'Biology','history'=>'History','chemistry'=>'Chemistry','physics'=>'Physics','earthscience'=>'Earth Science','geography'=>'Geography','values'=>'Values','english'=>'English'];
    public function headers(): array {return array_merge(['Record ID','Version','Student ID','Student Name Khmer','Student Name English','Academic Year','Campus','Grade','Exam Language','Selected Class','Exam Center','Room','Seat'],array_values(self::SUBJECTS),['Score Rank','Letter Grade']);}

    public function subjects(G12ExamRecord $record): array
    {
        $language=mb_strtolower(trim((string)($record->data['language_exam']??'')));
        $selected=mb_strtolower(trim((string)($record->data['class_sellected']??$record->data['class_selected']??'')));
        $isKhmer=str_contains($language,'khmer')||str_contains($language,'ខ្មែរ');
        $isSocial=str_contains($selected,'social')||str_contains($selected,'សង្គម');
        $isScience=!$isSocial&&(str_contains($selected,'science')||str_contains($selected,'វិទ្យា'));
        if($isSocial)$keys=['khmer','math','earthscience','geography','history','values'];
        elseif($isScience)$keys=['khmer','math','biology','history','chemistry','physics'];
        else $keys=[];
        if($isKhmer)$keys[]='english';
        return collect($keys)->mapWithKeys(fn($key)=>[$key=>self::SUBJECTS[$key]])->all();
    }

    public function exportRows(iterable $records): array
    {
        $rows=[];
        foreach($records as $record){
            $data=$record->data;$row=[
                'Record ID'=>$record->id,'Version'=>$record->version,'Student ID'=>$record->student_code,'Student Name Khmer'=>$record->name_kh,
                'Student Name English'=>$record->name_en,'Academic Year'=>$record->academic_year,'Campus'=>$record->campus?->campus_name_en,
                'Grade'=>$record->grade,'Exam Language'=>$data['language_exam']??'','Selected Class'=>$data['class_sellected']??$data['class_selected']??'',
                'Exam Center'=>$record->center?->name,'Room'=>$data['g12_exam_room']??'','Seat'=>$data['g12_exam_seat']??'',
            ];
            foreach(self::SUBJECTS as $key=>$label)$row[$label]=array_key_exists($key,$this->subjects($record))?($data['lettergrade_'.$key]??''):'';
            $row['Score Rank']=$data['g12_score_rank']??'';$row['Letter Grade']=$record->overall_grade??'';$rows[]=$row;
        }
        return $rows;
    }

    public function preview(User $user,UploadedFile $file): G12ExamImport
    {
        abort_unless(Access::allows($user,'scoring-csv-import'),403);
        $handle=fopen($file->getRealPath(),'rb');
        if(!$handle)throw ValidationException::withMessages(['file'=>'The CSV file could not be read.']);
        try{
            $header=fgetcsv($handle);if(!$header)throw ValidationException::withMessages(['file'=>'The CSV file is empty.']);
            $header=array_map(fn($value)=>trim(preg_replace('/^\xEF\xBB\xBF/','',(string)$value)),$header);
            $required=['Record ID','Version','Student ID','Academic Year','Letter Grade'];
            if($missing=array_diff($required,$header))throw ValidationException::withMessages(['file'=>'Missing CSV columns: '.implode(', ',$missing).'. Export a fresh scoring list and use that file.']);
            $records=[];$line=1;
            while(($values=fgetcsv($handle))!==false){
                $line++;if($line>5001)throw ValidationException::withMessages(['file'=>'A scoring CSV may contain at most 5,000 students.']);
                if(!array_filter($values,fn($v)=>trim((string)$v)!==''))continue;
                $values=array_pad($values,count($header),'');$records[]=array_combine($header,array_slice($values,0,count($header)))+['_line'=>$line];
            }
        }finally{fclose($handle);}
        if(!$records)throw ValidationException::withMessages(['file'=>'The CSV file has no student rows.']);
        $path=$file->store('g12_scoring_csv_imports','local');
        $batch=G12ExamImport::create(['user_id'=>$user->id,'kind'=>'score_csv','filename'=>$file->getClientOriginalName(),'source_path'=>$path,'payload'=>['rows'=>$records],'expires_at'=>now()->addHours(4)]);
        $batch->summary=$this->inspect($user,$batch);$batch->save();return $batch;
    }

    public function inspect(User $user,G12ExamImport $batch): array
    {
        abort_unless($batch->user_id===$user->id,403);abort_unless(Access::allows($user,'scoring-csv-import'),403);
        if($batch->kind!=='score_csv'||$batch->status!=='preview'||$batch->expires_at->isPast())throw ValidationException::withMessages(['file'=>'This CSV preview expired or was already imported.']);
        $rows=[];$errors=0;$seen=[];$campuses=Access::campusIds($user,'scoring-csv-import');
        foreach($batch->payload['rows'] as $source){
            $issues=[];$record=G12ExamRecord::find((int)($source['Record ID']??0));$id=(int)($source['Record ID']??0);
            if(isset($seen[$id]))$issues[]='Duplicate Record ID';$seen[$id]=true;
            if(!$record)$issues[]='Student record not found';
            elseif(!in_array($record->campus_id,$campuses,true))$issues[]='Campus access denied';
            elseif($record->student_code!==trim((string)$source['Student ID'])||$record->academic_year!==trim((string)$source['Academic Year']))$issues[]='Student ID or academic year does not match';
            elseif((int)$source['Version']!==$record->version)$issues[]='Record changed after CSV export';
            $subjects=$record?$this->subjects($record):[];$scores=[];
            foreach(self::SUBJECTS as $key=>$label){
                $value=mb_strtoupper(trim((string)($source[$label]??'')));
                if($value!==''&&!isset($subjects[$key]))$issues[]=$label.' is not assigned to this student';
                if($value!==''&&!preg_match('/^[A-F]$/',$value))$issues[]=$label.' must be A-F';
                if(isset($subjects[$key]))$scores[$key]=$value;
            }
            $rank=trim((string)($source['Score Rank']??''));$grade=mb_strtoupper(trim((string)($source['Letter Grade']??'')));
            if($rank!==''&&!preg_match('/^(?:\d{1,3})(?:\.\d{1,3})?$/',$rank))$issues[]='Score Rank must be 0-999.999';
            if($grade!==''&&!preg_match('/^[A-F]$/',$grade))$issues[]='Letter Grade must be A-F';
            if($issues)$errors++;
            $rows[]=['line'=>$source['_line'],'record_id'=>$id,'student_id'=>$source['Student ID']??'','name'=>$record?->name_en??($source['Student Name English']??''),'scores'=>$scores,'score_rank'=>$rank,'letter_grade'=>$grade,'version'=>(int)($source['Version']??0),'issues'=>$issues];
        }
        return ['total'=>count($rows),'valid'=>count($rows)-$errors,'errors'=>$errors,'rows'=>$rows];
    }

    public function confirm(User $user,G12ExamImport $batch): array
    {
        return DB::transaction(function()use($user,$batch){
            $batch=G12ExamImport::lockForUpdate()->findOrFail($batch->id);$preview=$this->inspect($user,$batch);
            if($preview['errors'])throw ValidationException::withMessages(['file'=>'Resolve all CSV errors before importing. Export a fresh list if records changed.']);
            foreach($preview['rows'] as $row){$record=G12ExamRecord::lockForUpdate()->findOrFail($row['record_id']);$this->apply($user,$record,$row,'scores-csv-imported');}
            $summary=['total'=>$preview['total'],'updated'=>$preview['valid']];$batch->update(['status'=>'confirmed','summary'=>$summary,'confirmed_at'=>now()]);
            app(G12NationalExams::class)->event($user,'scores-csv-confirmed',['batch_id'=>$batch->id,'summary'=>$summary]);return $summary;
        });
    }

    public function apply(User $user,G12ExamRecord $record,array $row,string $event='scores-imported'): G12ExamRecord
    {
        abort_unless(Access::allows($user,'scoring-csv-import',$record->campus_id)||Access::allows($user,'scoring-ocr-review',$record->campus_id),403);
        if(isset($row['version'])&&(int)$row['version']!==$record->version)throw ValidationException::withMessages(['record'=>'Student '.$record->student_code.' changed after preview.']);
        $data=$record->data;$before=['scores'=>[],'score_rank'=>$data['g12_score_rank']??'','letter_grade'=>$record->overall_grade??''];$subjects=$this->subjects($record);
        foreach($subjects as $key=>$label){$before['scores'][$key]=$data['lettergrade_'.$key]??'';$value=mb_strtoupper(trim((string)($row['scores'][$key]??'')));$data['lettergrade_'.$key]=$value;}
        $rank=trim((string)($row['score_rank']??''));$grade=mb_strtoupper(trim((string)($row['letter_grade']??'')));$data['g12_score_rank']=$rank;$data['g12_letter_grade']=$grade;
        $after=['scores'=>collect($subjects)->mapWithKeys(fn($label,$key)=>[$key=>$data['lettergrade_'.$key]])->all(),'score_rank'=>$rank,'letter_grade'=>$grade];
        $record->update(['data'=>$data,'overall_grade'=>$grade?:null,'result_status'=>app(G12NationalExams::class)->status($grade),'version'=>$record->version+1]);
        app(G12NationalExams::class)->event($user,$event,['before'=>$before,'after'=>$after],$record->id);return $record;
    }
}
