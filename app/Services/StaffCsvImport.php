<?php

namespace App\Services;

use App\Http\Controllers\StaffManagementController;
use App\Models\Staff;
use App\Models\User;
use App\Support\HrmPermissions;
use App\Support\StaffDataColumns;
use App\Support\StaffProfile;
use App\Support\StaffImportValidationException;
use App\Support\StaffImportSuggestions;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class StaffCsvImport
{
    public const MAX_ROWS=5000;
    private array $issues=[];
    private int $issueCount=0;
    private array $sourceRows=[];
    private array $failedRows=[];
    private array $headers=[];

    public function import(string $path,User $actor): array
    {
        $this->issues=[];
        $this->issueCount=0;
        $this->sourceRows=[];
        $this->failedRows=[];
        $stream=fopen($path,'rb');
        try {
            if (fread($stream,3)!=="\xEF\xBB\xBF") rewind($stream);
            $headers=fgetcsv($stream,0,',','"','');
            if (!$headers) throw ValidationException::withMessages(['file'=>'The CSV file is empty.']);
            $headers[0]=preg_replace('/^\xEF\xBB\xBF/','',$headers[0]);
            $headers=array_map('trim',$headers);
            $this->headers=$headers;
            if (!mb_check_encoding(implode('',$headers),'UTF-8') || str_contains(implode('',$headers),"\0")) throw ValidationException::withMessages(['file'=>'Save the file as CSV UTF-8.']);
            $schema=StaffDataColumns::fields(50,50);
            $keys=array_flip($schema);
            if (count(array_unique($headers))!==count($headers)) throw ValidationException::withMessages(['file'=>'The CSV has duplicate column headings.']);
            foreach($headers as $header) if (!isset($keys[$header])) throw ValidationException::withMessages(['file'=>'Unknown column: '.$header.'. Use the CSV template headings.']);
            foreach(['Staff ID','Staff Name (English)'] as $required) if (!in_array($required,$headers,true)) throw ValidationException::withMessages(['file'=>'Missing required column: '.$required]);
            $existing=Staff::withTrashed()->get(['id','staff_code','name_en','name_kh','deleted_at'])->keyBy(fn($staff)=>mb_strtolower($staff->staff_code));
            $lookup=new StaffDataLookup();
            $rows=[];$seen=[];$rowNumber=1;$recordCount=0;$supervisorRows=[];
            while(($cells=fgetcsv($stream,0,',','"',''))!==false) {
                $rowNumber++;
                if (!array_filter($cells,fn($cell)=>trim((string)$cell)!=='')) continue;
                if (++$recordCount>self::MAX_ROWS) throw ValidationException::withMessages(['file'=>'Import up to '.self::MAX_ROWS.' staff per file.']);
                $this->sourceRows[$rowNumber]=array_map(fn($cell)=>mb_convert_encoding((string)$cell,'UTF-8','UTF-8'),$cells);
                if (count($cells)!==count($headers)) { $this->issue($rowNumber,'','CSV','This row has a different number of columns than the header.');continue; }
                if (!mb_check_encoding(implode('',$cells),'UTF-8')) { $this->issue($rowNumber,'','CSV','Save the file as CSV UTF-8.');continue; }
                $flat=[];
                foreach($headers as $index=>$header) $flat[$keys[$header]]=trim((string)$cells[$index]);
                $code=$flat['staff_code']??'';$key=mb_strtolower($code);
                if ($code==='') { $this->issue($rowNumber,'','Staff ID','Staff ID is required.');continue; }
                if (isset($seen[$key])) { $this->issue($rowNumber,$code,'Staff ID','Duplicate Staff ID in this CSV (also on row '.$seen[$key].').');continue; }
                $seen[$key]=$rowNumber;
                if (count($seen)>self::MAX_ROWS) throw ValidationException::withMessages(['file'=>'Import up to '.self::MAX_ROWS.' staff per file.']);
                if ($existing->has($key)) { $this->issue($rowNumber,$code,'Staff ID','This Staff ID already exists. Existing staff are never overwritten.');continue; }
                $supervisorRows[$key]=['row'=>$rowNumber,'code'=>$code,'name'=>$flat['name_en']??'','supervisor'=>mb_strtolower($flat['supervisor_code']??'')];
                try {
                    $data=$this->payload($flat,$lookup);
                    if ($data['status']==='0' && !HrmPermissions::allows($actor,'staff','status')) throw ValidationException::withMessages(['status'=>'Profile Status permission is required to import inactive staff.']);
                    $rows[$key]=['row'=>$rowNumber,'code'=>$code,'data'=>$data,'supervisor'=>mb_strtolower($flat['supervisor_code']??'')];
                } catch(ValidationException $exception) { $this->validationIssues($rowNumber,$code,$exception,$schema); }
            }
        } finally { fclose($stream); }
        if (!$recordCount) throw ValidationException::withMessages(['file'=>'The CSV contains no staff records.']);
        $supervisorCandidates=$existing->filter(fn($staff)=>!$staff->deleted_at)->map(fn($staff)=>['staff_code'=>$staff->staff_code,'name_en'=>$staff->name_en,'name_kh'=>$staff->name_kh]);
        foreach($rows as $key=>$row) $supervisorCandidates->put($key,['staff_code'=>$row['code'],'name_en'=>$row['data']['name_en']??'','name_kh'=>$row['data']['name_kh']??'']);
        foreach($supervisorRows as $row) {
            if (!$row['supervisor'] || $supervisorCandidates->has($row['supervisor'])) continue;
            $eligible=$supervisorCandidates->reject(fn($candidate)=>mb_strtolower($candidate['staff_code'])===mb_strtolower($row['code']));
            $close=StaffImportSuggestions::rank($eligible,[$row['supervisor']],['staff_code','name_en','name_kh']);
            $suggestion=$close->isNotEmpty()?$close->map(fn($candidate)=>$candidate['record']['staff_code'].' ('.$candidate['record']['name_en'].')')->implode('; '):'No close supervisor match found. Enter an existing or successfully imported Staff ID.';
            $this->issue($row['row'],$row['code'],'Supervisor Staff ID','No available staff record matches Supervisor Staff ID: '.$row['supervisor'].'.',$suggestion);
        }
        $ordered=[];$visiting=[];$done=[];
        $visit=function(string $key)use(&$visit,&$ordered,&$visiting,&$done,$rows,$existing) {
            if (isset($done[$key])) return;
            $row=$rows[$key];
            if (isset($visiting[$key])) { $this->issue($row['row'],$row['code'],'Supervisor Staff ID','A supervisor cycle is present in this file.');return; }
            $visiting[$key]=true;
            $supervisor=$row['supervisor'];
            if ($supervisor) {
                if (isset($rows[$supervisor])) $visit($supervisor);
            }
            unset($visiting[$key]);$done[$key]=true;$ordered[]=$key;
        };
        foreach(array_keys($rows) as $key) $visit($key);
        $saved=$existing->filter(fn($staff)=>!$staff->deleted_at)->map(fn($staff)=>$staff->id)->all();
        $created=0;
        $controller=app(StaffManagementController::class);
        foreach($ordered as $key) {
            $row=$rows[$key];$data=$row['data'];
            if (isset($this->failedRows[$row['row']])) continue;
            if ($row['supervisor']) {
                if (!isset($saved[$row['supervisor']])) { $this->issue($row['row'],$row['code'],'Supervisor Staff ID','The supervisor row could not be imported.');continue; }
                $data['reports_to_staff_id']=$saved[$row['supervisor']];
            }
            $request=Request::create('/hrm/staff-management','POST',$data);
            $request->setUserResolver(fn()=>$actor);
            try {
                // The staff controller commits each profile and all its
                // related records together; one failure cannot undo others.
                $controller->save($request);
                $saved[$key]=Staff::where('staff_code',$row['code'])->value('id');
                $created++;
            } catch(ValidationException $exception) { $this->validationIssues($row['row'],$row['code'],$exception,$schema); }
            catch(\Illuminate\Database\QueryException $exception) {
                if (!in_array((string)$exception->getCode(),['23000','23505'],true)) throw $exception;
                $this->issue($row['row'],$row['code'],'Staff ID','A conflicting record was saved during import. Check the Staff ID and try again.');
            }
        }
        return $this->result($recordCount,$created);
    }

    private function payload(array $flat,StaffDataLookup $lookup): array
    {
        $lookupErrors=[];$lookupSuggestions=[];
        try { $resolved=$lookup->resolve(array_filter($flat,fn($value)=>$value!=='')); }
        catch(StaffImportValidationException $exception) { $lookupErrors=$exception->errors();$lookupSuggestions=$exception->suggestions; }
        foreach(StaffDataColumns::enumLabels() as $field=>$labels) {
            $value=$flat[$field]??'';
            if ($value==='') continue;
            $match=collect($labels)->filter(fn($label,$key)=>mb_strtolower($value)===mb_strtolower((string)$key) || mb_strtolower($value)===mb_strtolower($label));
            if ($field==='status' && in_array(strtolower($value),['on','yes','true'],true)) $match=collect(['1'=>'Active']);
            if ($field==='status' && in_array(strtolower($value),['off','no','false'],true)) $match=collect(['0'=>'Inactive']);
            if ($match->count()!==1) {
                $message='Use one of: '.implode(', ',$labels).'.';
                $lookupErrors[$field][]=$message;
                $lookupSuggestions[$field][$message]=StaffImportSuggestions::choices($value,$labels);
                continue;
            }
            $flat[$field]=(string)$match->keys()->first();
        }
        foreach($flat as $field=>$value) {
            if (!str_starts_with($field,'work_schedule.') || !str_ends_with($field,'.period') || $value==='') continue;
            $period=collect(StaffProfile::PERIODS)->filter(fn($label,$key)=>strcasecmp($value,$key)===0||strcasecmp($value,$label)===0);
            if ($period->count()!==1) {
                $message='Use Not Working, Morning Only, Afternoon Only, or Full Day.';
                $lookupErrors[$field][]=$message;
                $lookupSuggestions[$field][$message]=StaffImportSuggestions::choices($value,StaffProfile::PERIODS);
            }
        }
        if ($lookupErrors) throw new StaffImportValidationException($lookupErrors,$lookupSuggestions);
        $flat['staff_category']=$flat['staff_category']??'';
        $flat['staff_category']=$flat['staff_category']?:'staff';
        $flat['employment_type']=($flat['employment_type']??'')?:'FT';
        $flat['employment_status']=($flat['employment_status']??'')?:'active';
        $flat['status']=($flat['status']??'')===''?'1':$flat['status'];
        foreach($flat as $field=>$value) {
            if ($value!=='' && (in_array($field,['date_of_birth','joined_on','ended_on','identity_document_issued_on','identity_document_expires_on','contract_started_on','contract_ends_on','probation_ends_on'],true) || preg_match('/^(educations|experiences)\.\d+\.(started_on|ended_on)$/',$field))) {
                $parsed=false;
                foreach(['Y-m-d','d-M-Y'] as $format) {
                    $date=\DateTimeImmutable::createFromFormat('!'.$format,$value);
                    if ($date && strcasecmp($date->format($format),$value)===0) { $parsed=$date->format('Y-m-d');break; }
                }
                if (!$parsed) throw ValidationException::withMessages([$field=>'Enter a date as YYYY-MM-DD or DD-MMM-YYYY.']);
                $flat[$field]=$parsed;
            }
            if (str_starts_with($field,'work_schedule.') && str_ends_with($field,'.period') && $value!=='') {
                $period=collect(StaffProfile::PERIODS)->filter(fn($label,$key)=>strcasecmp($value,$key)===0||strcasecmp($value,$label)===0);
                if ($period->count()!==1) throw ValidationException::withMessages([$field=>'Use Not Working, Morning Only, Afternoon Only, or Full Day.']);
                $flat[$field]=$period->keys()->first();
            }
            if (preg_match('/^experiences\.\d+\.is_current$/',$field)) $flat[$field]=$value===''?'0':match(strtolower($value)) {'yes','true','1'=>'1','no','false','0'=>'0',default=>throw ValidationException::withMessages([$field=>'Use Yes or No.'])};
        }
        $data=array_replace(array_filter($flat,fn($value)=>$value!==''),array_intersect_key($resolved,array_flip(['birth_country_id','birth_province_id','nationality_country_id','address_country_id','address_province_id','address_district_id','address_commune_id','address_village_id','position_id','department_id','primary_campus_id','campuses'])));
        $payload=[];
        foreach($data as $field=>$value) if ($value!=='' && $value!==null) Arr::set($payload,$field,$value);
        foreach($payload['work_schedule']??[] as $day=>$schedule) $payload['work_schedule'][$day]['period']=$schedule['period']??'off';
        foreach(['educations','experiences'] as $section) {
            if (!isset($payload[$section])) continue;
            $payload[$section]=array_values(array_filter($payload[$section],fn($row)=>array_filter($row,fn($v)=>$v!==null && $v!=='' && $v!=='0')));
        }
        return $payload;
    }

    private function validationIssues(int $row,string $code,ValidationException $exception,array $schema): void
    {
        foreach($exception->errors() as $field=>$messages) foreach($messages as $message) {
            $suggestion=$exception instanceof StaffImportValidationException?($exception->suggestions[$field][$message]??''):'Review this field using the format or requirement described in Problem.';
            $this->issue($row,$code,$schema[$field]??$field,$message,$suggestion);
        }
    }
    private function issue(int $row,string $code,string $field,string $message,string $suggestion=''): void
    {
        if ($suggestion==='') {
            $suggestion=match($field) {
                'Staff ID'=>str_contains($message,'required')?'Enter a unique Staff ID.':'Check the Staff ID. Remove an already imported row or enter the correct unique Staff ID.',
                'Supervisor Staff ID'=>'Enter an existing or successfully imported supervisor Staff ID, and remove circular reporting relationships.',
                'CSV'=>str_contains($message,'UTF-8')?'Save this record as CSV UTF-8.':'Match the number and order of values to the CSV column headings.',
                default=>'Correct this field using the requirement described in Problem.',
            };
        }
        $this->issueCount++;
        $this->issues[]=compact('row','code','field','message','suggestion');
        $this->failedRows[$row]=$this->sourceRows[$row]??[];
    }
    private function result(int $total,int $created): array
    {
        ksort($this->failedRows);
        usort($this->issues,fn($a,$b)=>$a['row']<=>$b['row']);
        return ['total'=>$total,'created'=>$created,'skipped'=>count($this->failedRows),'error_count'=>$this->issueCount,'errors'=>$this->issues,
            'failed_headers'=>$this->headers,'failed_records'=>array_values($this->failedRows)];
    }
}
