<?php

namespace App\Support;

use App\Models\{ScholarshipExam,ScholarshipResultPrintSetting};
use App\Services\ScholarshipWorkflow;
use Illuminate\Support\Collection;

class ScholarshipResultReports
{
    public const WIDTHS=[3,17,4,4,5,4,4,11,8];
    public const HEADERS=['NO.','NAME','GENDER','GRADE','CAMPUS','ROOM','SEAT NO.','RESULT','SCHOLARSHIP AWARD'];

    public function data(ScholarshipExam $exam,Collection $results,string $students='winners'): array
    {
        $exam->loadMissing('academicYear','subjects');$setting=ScholarshipResultPrintSetting::where('exam_id',$exam->id)->first();
        $title='Result of '.$exam->examinationTitle();$pages=[];$signers=[];
        foreach(['prepared'=>'Prepared by','checked'=>'Checked and Verified by','approved'=>'Approved by'] as $role=>$label){
            $date=$setting?->getAttribute($role.'_date');
            $signers[]=['label'=>$label,'name'=>$setting?->getAttribute($role.'_by_name')??'________________________',
                'position'=>$setting?->getAttribute($role.'_position')??'',
                'date'=>$date?($setting->signing_location.', '.$date->format('F j, Y').'.'):'________________________'];
        }
        foreach($results->groupBy(fn($row)=>$row['candidate']->grade_number)->sortKeys(SORT_NUMERIC) as $grade=>$group){
            $slots=app(ScholarshipWorkflow::class)->awardSlots($exam,(int)$grade);
            $order=$slots->mapWithKeys(fn($slot,$index)=>[mb_strtolower(str_replace(' — Total Score','',$slot['label']))=>$index]);
            $ordered=$group->sort(function($left,$right)use($order){
                $rank=fn($row)=>filled($row['award_label'])?($order[mb_strtolower(str_replace(' — Total Score','',$row['award_label']))]??100):PHP_INT_MAX;
                return ($rank($left)<=>$rank($right))?:(($left['rank']??PHP_INT_MAX)<=>($right['rank']??PHP_INT_MAX))?:($left['candidate']->grade_exam_number<=>$right['candidate']->grade_exam_number);
            })->values();
            $rows=$ordered->map(function($row,$index){
                $candidate=$row['candidate'];$snapshot=$candidate->snapshot;$class=trim(explode(' / ',(string)($snapshot['class']??''))[0]);
                $gender=strtolower((string)($snapshot['gender']??$candidate->student?->gender??''));
                return ['number'=>$index+1,'name'=>$snapshot['name_en']??'','gender'=>match($gender){'male','m'=>'M','female','f'=>'F',default=>'—'},
                    'grade'=>$snapshot['grade_class']??(preg_match('/^\d/',$class)?$class:$candidate->grade_number.$class),
                    'campus'=>$snapshot['campus']??'','room'=>$candidate->room_number??'—','seat'=>$candidate->room_seat??'—',
                    'result'=>filled($row['award_label'])?str_replace(' — Total Score','',$row['award_label']):($candidate->absent?'Absent — All Subjects':'No Award'),
                    'award'=>$row['award']===null?'—':rtrim(rtrim(number_format((float)$row['award'],2,'.',''),'0'),'.').'%'];
            });
            // Leave space for the three signatures; long roster names get smaller page groups.
            $chunks=[];$chunk=collect();$height=0;
            foreach($rows as $row){
                $needed=max(5.5,ceil(mb_strlen($row['name'])/38)*4.5,ceil(mb_strlen($row['result'])/24)*4.5);
                if($chunk->isNotEmpty()&&($chunk->count()>=18||$height+$needed>99)){$chunks[]=$chunk;$chunk=collect();$height=0;}
                $chunk->push($row);$height+=$needed;
            }
            if($chunk->isNotEmpty())$chunks[]=$chunk;
            foreach($chunks as $part=>$chunk){
                $page=['grade'=>(int)$grade,'students'=>$chunk,'total'=>$rows->count(),'female'=>$rows->where('gender','F')->count(),
                    'year'=>$exam->academicYear?->academic_year,'exam_date'=>$exam->exam_date?->format('F j, Y'),
                    'part'=>$part+1,'signers'=>$part===array_key_last($chunks)?$signers:[]];
                $page['rows']=$this->grid($page,$title);$pages[]=$page;
            }
        }
        return ['title'=>$title,'document'=>'result-list','pages'=>$pages,'logoSrc'=>app(ScholarshipExport::class)->reportLogo(1),'logoLabel'=>'School Logo 1',
            'orientation'=>'landscape','pdfExport'=>false,'exam'=>$exam,'studentScope'=>$students,'widths'=>self::WIDTHS,'headers'=>self::HEADERS];
    }

    private function cell(int $span,mixed $text='',string $style='body',array $extra=[]): array
    {return array_replace(['span'=>$span,'lines'=>[['text'=>$text]],'style'=>$style],$extra);}

    private function grid(array $page,string $title): array
    {
        $rows=[['height'=>22,'cells'=>[$this->cell(12,'','body',['logo'=>true,'logo_width'=>55]),$this->cell(48,$title,'result-title')]],
            ['height'=>7,'cells'=>[$this->cell(60,'Academic Year: '.$page['year'],'result-subtitle')]],
            ['height'=>7,'cells'=>[$this->cell(60,'Exam Date: '.$page['exam_date'],'result-subtitle')]],
            ['height'=>8,'cells'=>[$this->cell(60,'Grade: '.$page['grade'],'result-grade')]],
            ['height'=>10,'cells'=>array_map(fn($width,$label)=>$this->cell($width,$label,'result-head'),self::WIDTHS,self::HEADERS)]];
        foreach($page['students'] as $student){
            $cells=[];$values=array_values($student);
            foreach(self::WIDTHS as $index=>$width)$cells[]=$this->cell($width,$values[$index],$index===1?'result-name':'result-body');
            $rows[]=['height'=>max(5.5,ceil(mb_strlen($student['name'])/38)*4.5,ceil(mb_strlen($student['result'])/24)*4.5),'cells'=>$cells];
        }
        $rows[]=['height'=>6,'cells'=>[$this->cell(60,'Grade '.$page['grade'].' | Total Students: '.$page['total'].' | Female: '.$page['female'],'small')]];
        if($page['signers']){
            $rows[]=['height'=>5,'cells'=>[$this->cell(60)]];
            foreach([['date',7,'result-signature'],['label',7,'result-signature'],['',17,'body'],['name',7,'result-signer'],['position',6,'result-signature']] as [$field,$height,$style])
                $rows[]=['height'=>$height,'cells'=>array_map(fn($signer)=>$this->cell(20,$field?$signer[$field]:'',$style),$page['signers'])];
        }
        return $rows;
    }

    public static function styles(): array
    {
        return ['result-title'=>['size'=>22,'bold'=>true,'align'=>'center','color'=>'#203c65'],
            'result-subtitle'=>['size'=>14,'align'=>'center','color'=>'#203c65'],'result-grade'=>['size'=>16,'bold'=>true,'align'=>'center','color'=>'#203c65'],
            'result-head'=>['size'=>10,'align'=>'center','fill'=>'#d9e2f3','border'=>'#000000'],
            'result-name'=>['size'=>10,'align'=>'left','border'=>'#000000'],'result-body'=>['size'=>10,'align'=>'center','border'=>'#000000'],
            'result-signature'=>['size'=>11,'align'=>'center'],'result-signer'=>['size'=>11,'bold'=>true,'align'=>'center']];
    }

    public function pdfArchive(array $data): string
    {
        $path=tempnam(sys_get_temp_dir(),'scholarship-result-grades-');if($path===false)throw new \RuntimeException('Unable to prepare result files.');
        $zip=new \ZipArchive();$opened=false;
        try{
            if($zip->open($path,\ZipArchive::CREATE|\ZipArchive::OVERWRITE)!==true)throw new \RuntimeException('Unable to create result files.');$opened=true;
            foreach(collect($data['pages'])->groupBy('grade') as $grade=>$pages)
                $zip->addFromString('Grade '.(int)$grade.'.pdf',app(ScholarshipSeatReports::class)->pdf(array_replace($data,['pages'=>$pages->values()->all()])));
            $zip->close();$opened=false;return file_get_contents($path);
        }finally{if($opened)$zip->close();if(is_file($path))unlink($path);}
    }
}
