<?php

namespace App\Support;

use App\Models\ScholarshipExam;
use App\Services\ScholarshipWorkflow;
use Illuminate\Support\Collection;

/** One shared layout model for room score verification in Print, PDF and Excel. */
class ScholarshipScoreReports
{
    public function data(ScholarshipExam $exam,Collection $candidates): array
    {
        $exam->loadMissing('academicYear','subjects');$candidates->each->loadMissing('scores','student');
        $logo=app(ScholarshipExport::class)->reportLogo(1);
        $title='Score Verification List';$pages=[];$workflow=app(ScholarshipWorkflow::class);
        foreach($candidates->groupBy(fn($candidate)=>($candidate->exam_center_id??0).':'.$candidate->room_number.':'.$candidate->grade_number) as $room){
            $first=$room->first();$subjects=$workflow->applicableSubjects($exam,$first->grade_number)->values();
            $students=$room->sortBy('room_seat',SORT_NUMERIC)->values()->map(function($candidate,$index)use($workflow,$exam,$subjects){
                $snapshot=$candidate->snapshot;$scores=$candidate->scores->keyBy('subject_id');
                $class=trim(explode(' / ',(string)($snapshot['class']??''))[0]);
                $grade=$snapshot['grade_class']??(preg_match('/^\d/',$class)?$class:$candidate->grade_number.$class);
                $gender=strtolower((string)($snapshot['gender']??$candidate->student?->gender??''));
                $calculation=$workflow->calculate($exam,$candidate);$marks=[];
                foreach($subjects as $subject){
                    $score=$scores->get($subject->id);$absent=$candidate->absent||$score?->is_absent;
                    $marks[$subject->id]=['value'=>$absent?0:$score?->score,'absent'=>(bool)$absent];
                }
                return ['number'=>$index+1,'room'=>$candidate->room_number,'seat'=>$candidate->room_seat,'name'=>$snapshot['name_en']??'',
                    'gender'=>match($gender){'male','m'=>'M','female','f'=>'F',default=>'—'},'grade'=>$grade,'campus'=>$snapshot['campus']??'',
                    'scores'=>$marks,'total'=>$calculation['total'],'all_absent'=>$candidate->absent,'reviewed'=>(bool)$candidate->reviewed_at];
            });
            $chunks=[];$chunk=collect();$height=0;
            foreach($students as $student){
                $rowHeight=$this->studentHeight($student);
                if($chunk->isNotEmpty()&&($chunk->count()===25||$height+$rowHeight>116)){$chunks[]=$chunk;$chunk=collect();$height=0;}
                $chunk->push($student);$height+=$rowHeight;
            }
            if($chunk->isNotEmpty())$chunks[]=$chunk;
            // Split unusually large subject lists into legible continuation pages.
            foreach($subjects->chunk(6) as $subjectSet)foreach($chunks as $part=>$studentSet){
                $page=['center_id'=>$first->exam_center_id,'center'=>$first->exam_center_name?:'Main Exam Center','room'=>$first->room_number,
                    'grade'=>$first->grade_number,'subjects'=>$subjectSet->values(),'students'=>$studentSet->values(),'total'=>$students->count(),
                    'female'=>$students->where('gender','F')->count(),'part'=>$part+1,'exam_date'=>$exam->exam_date?->format('F j, Y'),
                    'year'=>$exam->academicYear?->academic_year,'reviewed'=>$students->every(fn($student)=>$student['reviewed'])];
                $page['rows']=$this->grid($page,$title);$pages[]=$page;
            }
        }
        $verificationStampSrc='data:image/svg+xml;base64,'.base64_encode(file_get_contents(public_path('images/scholarship/score-verification-stamp.svg')));
        return ['title'=>$title,'titleSize'=>mb_strlen($title)>70?14:20,'document'=>'score-list','pages'=>$pages,'logoSrc'=>$logo,'logoLabel'=>'School Logo 1','verificationStampSrc'=>$verificationStampSrc,
            'pdfExport'=>false,'orientation'=>'landscape','exam'=>$exam];
    }

    private function cell(int $span,mixed $text='',string $style='body',array $extra=[]): array
    {return array_replace(['span'=>$span,'lines'=>[['text'=>$text]],'style'=>$style],$extra);}

    /** A ZIP avoids browser blocking when downloading several independent room PDFs. */
    public function pdfArchive(array $data): string
    {
        $path=tempnam(sys_get_temp_dir(),'scholarship-score-rooms-');
        if($path===false)throw new \RuntimeException('Unable to prepare the room PDF download.');
        $zip=new \ZipArchive();$opened=false;
        try{
            if($zip->open($path,\ZipArchive::CREATE|\ZipArchive::OVERWRITE)!==true)throw new \RuntimeException('Unable to create the room PDF archive.');
            $opened=true;
            $groups=collect($data['pages'])->groupBy(fn($page)=>($page['center_id']??0).':'.$page['room']);
            $roomCounts=$groups->map(fn($pages)=>$pages->first()['room'])->countBy();
            foreach($groups as $pages){
                $first=$pages->first();
                $name=($roomCounts[$first['room']]>1?'CENTER '.(int)$first['center_id'].' - ':'').'ROOM '.(int)$first['room'].'.pdf';
                $zip->addFromString($name,app(ScholarshipSeatReports::class)->pdf(array_replace($data,['pages'=>$pages->values()->all()])));
            }
            $zip->close();$opened=false;return file_get_contents($path);
        }finally{
            if($opened)$zip->close();
            if(is_file($path))unlink($path);
        }
    }

    private function studentHeight(array $student): float
    {return max(4.3,ceil(mb_strlen($student['name'])/40)*3.8,ceil(mb_strlen($student['campus'])/13)*3.8);}

    private function grid(array $page,string $title): array
    {
        $row=fn($height,$cells)=>['height'=>$height,'cells'=>$cells];
        $rows=[
            $row(23,[$this->cell(10,'','body',['logo'=>true,'logo_width'=>43]),$this->cell(50,$title,'score-title',['lines'=>[['text'=>$title,'size'=>mb_strlen($title)>70?14:20]]])]),
            $row(5,[$this->cell(60,'Academic Year: '.$page['year'],'subtitle')]),
            $row(5,[$this->cell(60,'Exam Date: '.$page['exam_date'],'subtitle')]),
            $row(5,[$this->cell(60,'Exam Center: '.$page['center'],'subtitle')]),
            $row(8,[$this->cell(60,'Room: '.$page['room'].'   |   Grade: '.$page['grade'].($page['part']>1?'   |   Continued':''),'room')]),
        ];
        $widths=[3,4,4,13,4,4,4];$labels=['NO.','ROOM NO.','SEAT NO.','NAME','GENDER','GRADE','CAMPUS'];
        $head=[];$subhead=[];
        foreach($widths as $index=>$width){
            $head[]=$this->cell($width,$labels[$index],'score-table-head',['rowspan'=>2]);
            $subhead[]=$this->cell($width,'','score-table-head',['covered'=>true]);
        }
        $head[]=$this->cell(24,'SCORES','score-group-head');$rows[]=$row(6,$head);
        $count=$page['subjects']->count()+1;$base=intdiv(24,$count);$remaining=24%$count;$scoreWidths=[];
        foreach(range(0,$count-1) as $index)$scoreWidths[]=$base+($index<$remaining?1:0);
        foreach($page['subjects'] as $index=>$subject)$subhead[]=$this->cell($scoreWidths[$index],$subject->name,'score-subject-head');
        $subhead[]=$this->cell($scoreWidths[$count-1],'TOTAL','score-total-head');$rows[]=$row(6,$subhead);
        foreach($page['students'] as $student){
            $style=$student['number']%2?'score-body':'score-body-alt';$cells=[];
            $values=[$student['number'],$student['room'],$student['seat'],$student['name'],$student['gender'],$student['grade'],$student['campus']];
            foreach($values as $index=>$value)$cells[]=$this->cell($widths[$index],$value,$index===3?($style==='score-body'?'score-name':'score-name-alt'):$style);
            foreach($page['subjects'] as $index=>$subject){
                $score=$student['scores'][$subject->id];
                $cells[]=$this->cell($scoreWidths[$index],$score['value']??'—',$score['absent']?'score-absent':$style);
            }
            $cells[]=$this->cell($scoreWidths[$count-1],$student['total']??'—','score-total');
            $rows[]=$row($this->studentHeight($student),$cells);
        }
        $rows[]=$row(6,[$this->cell(60,'Total Students: '.$page['total'].'   |   Female: '.$page['female'],'small')]);
        $rows[]=$row(5,[$this->cell(60,'A = Absent (counts as 0)   |   — = Not entered   |   '.($page['reviewed']?'Reviewed scores':'Draft / pending review'),'small')]);
        return $rows;
    }

    public static function styles(): array
    {
        return [
            'score-title'=>['size'=>20,'bold'=>true,'align'=>'center','color'=>'#203c65'],
            'score-table-head'=>['size'=>8,'align'=>'center','fill'=>'#d9e2f3','border'=>'#000000'],
            'score-group-head'=>['size'=>10,'bold'=>true,'align'=>'center','fill'=>'#ffdf6b','border'=>'#000000'],
            'score-subject-head'=>['size'=>8,'align'=>'center','fill'=>'#c6e0b4','border'=>'#000000'],
            'score-total-head'=>['size'=>8,'bold'=>true,'align'=>'center','fill'=>'#548235','color'=>'#ffffff','border'=>'#000000'],
            'score-body'=>['size'=>8,'align'=>'center','border'=>'#000000'],
            'score-body-alt'=>['size'=>8,'align'=>'center','fill'=>'#f2f2f2','border'=>'#000000'],
            'score-name'=>['size'=>8,'align'=>'left','border'=>'#000000'],
            'score-name-alt'=>['size'=>8,'align'=>'left','fill'=>'#f2f2f2','border'=>'#000000'],
            'score-total'=>['size'=>8,'bold'=>true,'align'=>'center','border'=>'#000000'],
            'score-absent'=>['size'=>8,'align'=>'center','color'=>'#c00000','border'=>'#000000','number_format'=>'0.00" (A)"'],
        ];
    }
}
