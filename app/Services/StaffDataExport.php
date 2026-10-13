<?php

namespace App\Services;

use App\Models\Staff;
use App\Models\StaffEducation;
use App\Models\StaffExperience;
use App\Support\StaffDataColumns;
use App\Support\StaffProfile;
use Illuminate\Support\Arr;
use ZipArchive;

class StaffDataExport
{
    public function samples(): array
    {
        $locations=(new StaffDataLookup())->sampleLocations();
        $rows=[];
        foreach([['HRM-SAMPLE-001','SOK DARA','សុខ ដារា','Male','1990-09-12'],['HRM-SAMPLE-002','CHAN MALIS','ចាន់ មាលីស','Female','1995-03-20']] as [$code,$name,$khmer,$gender,$dob]) {
            $data=$locations+['staff_code'=>$code,'name_en'=>$name,'name_kh'=>$khmer,'gender'=>$gender,'gender_kh'=>StaffProfile::GENDER_KH[$gender],
                'date_of_birth'=>$dob,'date_of_birth_kh'=>StaffProfile::dateKhmer($dob),'nationality_note'=>'Cambodian','marital_status'=>'Single','marital_status_kh'=>StaffProfile::MARITAL_KH['single'],
                'phone'=>'+85512909909','secondary_phone'=>'+855979099897','email'=>strtolower($code).'@example.com','house_number_en'=>'12','house_number_kh'=>'១២','street_en'=>'271','street_kh'=>'២៧១',
                'current_address'=>'House 12, Street 271','current_address_kh'=>'ផ្ទះលេខ១២ ផ្លូវ២៧១','identity_document_type'=>'National ID','identity_document_number'=>'EXAMPLE-'.$code,
                'identity_document_issued_on'=>'2024-01-01','identity_document_expires_on'=>'2034-01-01','emergency_contact_name'=>'SOK CHAN','emergency_contact_phone'=>'+85512909909','emergency_contact_relationship'=>'Sibling',
                'joined_on'=>'2022-01-03','staff_category'=>'Staff','employment_type'=>'Full-Time','employment_status'=>'Active','status'=>'Active','contract_type'=>'Permanent',
                'contract_started_on'=>'2022-01-03','probation_ends_on'=>'2022-04-03','session_1_in'=>'07:30','session_1_out'=>'11:30','session_2_in'=>'13:00','session_2_out'=>'17:00',
                'educations'=>[['degree'=>'Bachelor','field_of_study'=>'Education','institution_name'=>'Example University','country'=>'Cambodia','graduated_year'=>2016]],
                'experiences'=>[['company_name'=>'Example School','position'=>'Teacher','started_on'=>'2017-01-01','ended_on'=>'2021-12-31','is_current'=>'No','responsibilities'=>'Teaching and student support']],
            ];
            foreach(StaffProfile::DAYS as $day=>$label) $data['work_schedule'][$day]=['period'=>in_array($day,['sat','sun'])?'Not Working':($gender==='Female'&&$day==='fri'?'Afternoon Only':'Full Day')];
            $rows[]=$data;
        }
        return $rows;
    }

    public function row(Staff $staff): array
    {
        $data=$staff->getAttributes();
        $data['gender_kh']=$staff->gender_kh;$data['date_of_birth_kh']=$staff->date_of_birth_kh;
        $data['marital_status_kh']=$staff->marital_status_kh;$data['nationality_note']=$staff->nationality_note;
        $data['birth_country_en']=$staff->birthCountry?->country_name_en;$data['birth_country_kh']=$staff->birthCountry?->country_name_kh;
        $data['place_of_birth_en']=$staff->birthProvince?->province_name_en??$staff->place_of_birth_en;
        $data['place_of_birth_kh']=$staff->birthProvince?->province_name_kh??$staff->place_of_birth_kh;
        $data['address_country_en']=$staff->addressCountry?->country_name_en;$data['address_country_kh']=$staff->addressCountry?->country_name_kh;
        foreach(['province','district','commune','village'] as $level) {
            $location=$staff->{'address'.ucfirst($level)};
            if ($location) foreach(['en','kh'] as $language) $data[$level.'_'.$language]=$location->{$level.'_name_'.$language};
        }
        $data['position']=$staff->position?->name;$data['department']=$staff->department?->hierarchy_name;
        $data['supervisor_code']=$staff->supervisor?->staff_code;
        $data['primary_campus']=$staff->primaryCampus?->campus_name_en;
        $data['working_campuses']=$staff->campuses->pluck('campus_name_en')->implode(' | ');
        foreach(StaffDataColumns::enumLabels() as $field=>$labels) $data[$field]=$labels[(string)$staff->{$field}]??$staff->{$field};
        $data['status']=$staff->status?'Active':'Inactive';
        foreach(['date_of_birth','joined_on','ended_on','identity_document_issued_on','identity_document_expires_on','contract_started_on','contract_ends_on','probation_ends_on'] as $field) $data[$field]=$staff->{$field}?->format('Y-m-d');
        $data['work_schedule']=$staff->work_schedule??[];
        foreach($data['work_schedule'] as $day=>&$schedule) $schedule['period']=StaffProfile::PERIODS[$schedule['period']??'off']??'';
        unset($schedule);
        foreach(['session_1_in','session_1_out','session_2_in','session_2_out'] as $field) $data[$field]=$staff->{$field}?substr($staff->{$field},0,5):'';
        $data['educations']=$staff->educations->map(function($row) {
            $data=$row->only(array_keys(StaffDataColumns::EDUCATION));
            foreach(['started_on','ended_on'] as $field) $data[$field]=$row->{$field}?->format('Y-m-d');
            return $data;
        })->all();
        $data['experiences']=$staff->experiences->map(function($row) {
            $data=$row->only(array_keys(StaffDataColumns::EMPLOYMENT));
            foreach(['started_on','ended_on'] as $field) $data[$field]=$row->{$field}?->format('Y-m-d');
            $data['is_current']=$row->is_current?'Yes':'No';
            return $data;
        })->all();
        return $data;
    }

    public function xlsx(): string
    {
        $educationCount=max(1,(int)StaffEducation::selectRaw('COUNT(*) AS total')->groupBy('staff_id')->pluck('total')->max());
        $employmentCount=max(1,(int)StaffExperience::selectRaw('COUNT(*) AS total')->groupBy('staff_id')->pluck('total')->max());
        $fields=StaffDataColumns::fields($educationCount,$employmentCount);
        $sheet=tempnam(storage_path('app'),'staff-sheet-');
        $file=$sheet.'.xlsx';$stream=fopen($sheet,'wb');$zip=new ZipArchive();
        try {
            $last=$this->column(count($fields));
            fwrite($stream,'<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane xSplit="1" ySplit="1" topLeftCell="B2" activePane="bottomRight" state="frozen"/></sheetView></sheetViews><cols>');
            foreach(array_values($fields) as $index=>$heading) fwrite($stream,'<col min="'.($index+1).'" max="'.($index+1).'" width="'.(str_contains($heading,'Address')||str_contains($heading,'Remarks')?38:24).'" customWidth="1"/>');
            fwrite($stream,'</cols><sheetData>'.$this->xmlRow(array_values($fields),1,1));
            $rowNumber=1;
            Staff::with(['position','department.parent','primaryCampus','campuses','birthCountry','birthProvince','addressCountry','addressProvince','addressDistrict','addressCommune','addressVillage','supervisor','educations','experiences'])->orderBy('id')->chunkById(200,function($staff)use($stream,$fields,&$rowNumber) {
                foreach($staff as $record) {
                    $data=$this->row($record);$cells=[];
                    foreach(array_keys($fields) as $field) $cells[]=Arr::get($data,$field,'');
                    fwrite($stream,$this->xmlRow($cells,++$rowNumber,$rowNumber%2===0?2:0));
                }
            });
            fwrite($stream,'</sheetData><autoFilter ref="A1:'.$last.$rowNumber.'"/></worksheet>');fclose($stream);$stream=null;
            if ($zip->open($file,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) throw new \RuntimeException('Unable to create the staff Excel export.');
            $zip->addFromString('[Content_Types].xml','<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
            $zip->addFromString('_rels/.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Staff Information" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml','<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="10"/><name val="Khmer OS Siemreap"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Khmer OS Siemreap"/></font></fonts><fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF27395B"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF2F5FA"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
            $zip->addFile($sheet,'xl/worksheets/sheet1.xml');
            if (!$zip->close()) throw new \RuntimeException('Unable to finish the staff Excel export.');
            return $file;
        } catch(\Throwable $exception) { if (is_file($file)) unlink($file);throw $exception; }
        finally { if (is_resource($stream)) fclose($stream);if (is_file($sheet)) unlink($sheet); }
    }

    private function xmlRow(array $cells,int $row,int $style): string
    {
        $xml='<row r="'.$row.'"'.($row===1?' ht="42" customHeight="1"':'').'>';
        foreach($cells as $index=>$cell) $xml.='<c r="'.$this->column($index+1).$row.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',(string)$cell),ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is></c>';
        return $xml.'</row>';
    }
    private function column(int $number): string { $name='';while($number>0) {$number--;$name=chr(65+$number%26).$name;$number=intdiv($number,26);}return $name; }
}
