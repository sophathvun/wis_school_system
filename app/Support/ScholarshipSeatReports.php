<?php

namespace App\Support;

use App\Models\{ScholarshipExam, ScholarshipExamCenter, ScholarshipSeatPrintSetting};
use Illuminate\Support\Collection;

/** A shared page grid keeps Print, PDF and editable Excel reports consistent. */
class ScholarshipSeatReports
{
    public const DOCUMENTS = [
        'candidates'=>'List of Candidates', 'attendance'=>'Attendance List',
        'attendance-cover'=>'Cover Attendance List', 'envelope-cover'=>'Cover Envelope',
        'seat-labels'=>'Seat Label Sticker', 'room-plan'=>'Seat Plan',
    ];

    public function data(ScholarshipExam $exam, Collection $candidates, string $document): array
    {
        abort_unless(isset(self::DOCUMENTS[$document]),404);
        $exam->loadMissing('academicYear','subjects');
        $candidates->each->loadMissing('student');
        $signatures=ScholarshipSeatPrintSetting::whereIn('exam_center_id',$exam->centers()->select('id'))->get()->keyBy('exam_center_id');
        $centers=$exam->centers()->get()->keyBy('id');
        $invigilatorDuties=$document==='attendance-cover'?$exam->invigilatorDuties()->with(['invigilator','session'])->get():collect();
        $pages=[];
        // Centers can both have Room 1. Never combine them by room number alone.
        foreach($candidates->groupBy(fn($candidate)=>($candidate->exam_center_id??0).':'.$candidate->room_number) as $room){
            $first=$room->first();
            $center=$centers->get($first->exam_center_id)??new ScholarshipExamCenter();
            $students=$room->sortBy('room_seat')->map(function($candidate){
                $snapshot=$candidate->snapshot;
                $class=trim(explode(' / ',(string)($snapshot['class']??''))[0]);
                $grade=(string)$candidate->grade_number;
                $gradeClass=$snapshot['grade_class']??(preg_match('/^\d/',$class)?$class:$grade.$class);
                $gender=strtolower((string)($snapshot['gender']??$candidate->student?->gender??''));
                return ['seat'=>$candidate->room_seat,'name'=>$snapshot['name_en']??'','grade'=>$gradeClass,'grade_number'=>$candidate->grade_number,
                    'gender'=>match($gender){'male','m'=>'M','female','f'=>'F',default=>'—'},'campus'=>$snapshot['campus']??''];
            })->values()->all();
            $subjects=app(ScholarshipSubjectOrder::class)->forGrade($exam,(int)$first->grade_number);
            $meta=['center_id'=>$first->exam_center_id,'center'=>$first->exam_center_name?:'Main Exam Center','location'=>$center->location?:'Phnom Penh','exam_date'=>$center->reportDate($exam->exam_date),'room'=>$first->room_number,'grade'=>$first->grade_number,'total'=>count($students)];
            if(in_array($document,['candidates','attendance'],true)){
                $perPage=collect($students)->contains(fn($student)=>mb_strlen($student['name'])>($document==='attendance'?32:42))?($document==='candidates'?18:20):25;
                $sets=$document==='attendance'?$subjects->pluck('name')->chunk(4)->map->all()->all():[[]];
                if(!$sets)$sets=[[]];
                foreach($sets as $subjectSet)foreach(array_chunk($students,$perPage) as $index=>$chunk){
                    $rows=$this->heading($exam,$document,$meta,true);
                    $rows[]=$this->row(8,[$this->cell(60,'Room: '.$meta['room'].'   ·   Grade: '.$meta['grade'], 'room')]);
                    if($document==='candidates'){
                        $widths=[5,23,5,6,7,5,9];$labels=['SEAT NO.','NAME','GENDER','GRADE','CAMPUS','ROOM','REMARKS'];
                    }else{
                        $widths=[4,17,5,5,5,...array_fill(0,count($subjectSet),(int)(24/max(1,count($subjectSet))))];
                        $labels=['SEAT NO.','NAME','GENDER','GRADE','CAMPUS',...array_map(fn($subject)=>mb_strtoupper($subject),$subjectSet)];
                        if(!$subjectSet){$widths[] =24;$labels[]='ATTENDANCE';}
                    }
                    $rows[]=$this->tableRow(9,$widths,$labels,'candidate-table-head');
                    foreach($chunk as $student){
                        $values=$document==='candidates'?[$student['seat'],$student['name'],$student['gender'],$student['grade'],$student['campus'],$meta['room'],'']
                            :[$student['seat'],$student['name'],$student['gender'],$student['grade'],$student['campus'],...array_fill(0,max(1,count($subjectSet)),'')];
                        $rows[]=$this->tableRow($document==='attendance'?6.9:6.4,$widths,$values,'table-body',1);
                    }
                    if($document==='attendance')foreach(['Number of absent students','Invigilator’s signature (1)','Invigilator’s signature (2)','Controller’s signature'] as $label){
                        $rows[]=$this->row(7,[$this->cell(36,mb_strtoupper($label),'attendance-footer-label'),...array_map(fn($width)=>$this->cell($width,'','table-body'),array_slice($widths,5))]);
                    }
                    $totals=($document==='candidates'?'Total Students: ':'Total Student: ').$meta['total'].'   |   Female: '.collect($students)->where('gender','F')->count();
                    $rows[]=$this->row(6,[$this->cell(60,$totals.(count($students)>$perPage?'   ·   List part '.($index+1):''),'small')]);
                    if($document==='candidates'&&($index+1)*$perPage>=count($students))$rows=[...$rows,...$this->signatures($signatures->get($first->exam_center_id),$center)];
                    $pages[]=$meta+['rows'=>$rows];
                }
            }elseif($document==='attendance-cover'){
                $coverRows=[];
                foreach($subjects->values() as $subjectIndex=>$subject){
                    $assigned=$invigilatorDuties->filter(fn($d)=>$d->exam_center_id===$first->exam_center_id && $d->room_number===$first->room_number && $d->session->number===$subjectIndex+1)->sortBy(fn($d)=>$d->invigilator->name_en);
                    $people=$assigned->isEmpty()?[null]:$assigned->all();
                    foreach($people as $duty){
                        $name=$duty?->invigilator->name_en??'____________________';$phone=$duty?->invigilator->phone??'________________';
                        $coverRows[]=$this->row(max(9,ceil(mb_strlen($name)/32)*4.5,ceil(mb_strlen($subject->name)/22)*4.5,ceil(mb_strlen($phone)/22)*4.5),[
                            $this->cell(6,[$this->line((string)($subjectIndex+1),10)],'cover-table-body'),
                            $this->cell(14,[$this->line($subject->name,10)],'cover-table-body'),
                            $this->cell(24,[$this->line($name,10)],'cover-table-body'),
                            $this->cell(16,[$this->line($phone,10)],'cover-table-body'),
                        ]);
                    }
                }
                // A room's four sessions and two invigilators per session fit on one cover.
                // Additional/long records continue on another complete A4 cover.
                $chunks=[[]];$height=0;
                foreach($coverRows as $row){if($height+$row['height']>88 && $chunks[array_key_last($chunks)]){$chunks[]=[];$height=0;}$chunks[array_key_last($chunks)][]=$row;$height+=$row['height'];}
                foreach($chunks as $part=>$chunk){
                    $rows=$this->heading($exam,$document,$meta,false);
                    $rows[]=$this->row(6,[$this->cell(60,'')]);
                    $rows[]=$this->row(16,[$this->cell(60,[$this->line('Attendance List'.($part?' (continued)':''),28,true)],'cover-title')]);
                    $rows[]=$this->row(18,[$this->cell(60,'Room: '.$meta['room'],'cover-room')]);
                    $rows[]=$this->row(12,[$this->cell(60,'Grade: '.$meta['grade'],'cover-title')]);
                    $rows[]=$this->row(13,[$this->cell(60,'Number of Candidates: '.$meta['total'],'cover-text')]);
                    $rows[]=$this->row(13,[$this->cell(60,'Absent Students: __________','cover-text')]);
                    $rows[]=$this->row(5,[$this->cell(60,'')]);
                    $rows[]=$this->row(10,array_map(fn($span,$text)=>$this->cell($span,[$this->line($text,10,true)],'cover-table-head'),[6,14,24,16],['Session','Subject','Invigilator Name','Phone Number']));
                    $rows=[...$rows,...$chunk];
                    $rows[]=$this->row(max(1,262-array_sum(array_column($rows,'height'))),[$this->cell(60,'')]);
                    $pages[]=$meta+['rows'=>$rows];
                }
            }elseif($document==='envelope-cover'){
                $coverSubjects=$document==='envelope-cover'?$subjects->pluck('name')->all():[null];
                foreach($coverSubjects as $subject){
                    $rows=$this->heading($exam,$document,$meta,false);
                    $rows[]=$this->row(12,[$this->cell(60,'','body')]);
                    $rows[]=$this->row(23,[$this->cell(60,$subject?'Subject: '.$subject:[$this->line('Attendance List',28,true)],'cover-title')]);
                    $rows[]=$this->row(25,[$this->cell(60,'Room: '.$meta['room'],'cover-room')]);
                    $rows[]=$this->row(17,[$this->cell(60,'Grade: '.$meta['grade'],'cover-title')]);
                    $rows[]=$this->row(13,[$this->cell(60,'Number of Candidates: '.$meta['total'],'cover-text')]);
                    $rows[]=$this->row(13,[$this->cell(60,'Absent Students: __________','cover-text')]);
                    $rows[]=$this->row(10,[$this->cell(60,'','body')]);
                    $rows[]=$this->row(10,[$this->cell(6,''),$this->cell(24,'Invigilator(s)','cover-table-head'),$this->cell(24,'Contact Number','cover-table-head'),$this->cell(6,'')]);
                    for($i=0;$i<2;$i++)$rows[]=$this->row(11,[$this->cell(6,''),$this->cell(24,'','cover-table-body'),$this->cell(24,'','cover-table-body'),$this->cell(6,'')]);
                    $rows[]=$this->row(9,[$this->cell(60,'')]);
                    if($subject){
                        $rows[]=$this->row(9,[$this->cell(60,'For Central Exam Committee','committee')]);
                        $rows[]=$this->row(15,[$this->cell(60,'Number of exam papers received: __________','cover-detail')]);
                        $rows[]=$this->row(15,[$this->cell(60,'Received by: ____________________   Signature: ____________________','cover-detail')]);
                    }
                    $rows[]=$this->row(max(1,262-array_sum(array_column($rows,'height'))),[$this->cell(60,'')]);
                    $pages[]=$meta+['rows'=>$rows];
                }
            }elseif($document==='seat-labels'){
                foreach(array_chunk($students,18) as $chunk){
                    $rows=$this->heading($exam,$document,$meta,false,true);
                    $rows[]=$this->row(7,[$this->cell(60,'Room: '.$meta['room'].'   ·   Grade: '.$meta['grade'].'   ·   Candidates: '.$meta['total'],'small')]);
                    foreach(array_chunk($chunk,3) as $line){
                        $cells=[];
                        foreach($line as $student){
                            $cell=$this->cell(20,[
                                $this->line($exam->examinationTitle(),mb_strlen($exam->examinationTitle())>30?6.5:9,true),$this->line($student['seat'],26,true,'#ed1c24'),
                                $this->line('Seat No.',6),$this->line($student['name'],9,true),
                                $this->line('Grade: '.$student['grade_number'].'   ·   Room: '.$meta['room'],8),
                            ],'label');
                            $cell['logo']=true;$cell['logo_width']=8;$cells[]=$cell;
                        }
                        while(count($cells)<3)$cells[]=$this->cell(20,'');
                        $rows[]=$this->row(37,$cells);
                        $rows[]=$this->row(2,[$this->cell(60,'')]);
                    }
                    $pages[]=$meta+['rows'=>$rows];
                }
            }else{
                foreach(array_chunk($students,25) as $index=>$chunk){
                    $rows=$this->heading($exam,$document,$meta,false);
                    $rows[]=$this->row(9,[$this->cell(60,'Seat Plan   ·   Room: '.$meta['room'].'   ·   Grade: '.$meta['grade'].(count($students)>25?'   ·   Part '.($index+1):''),'room')]);
                    $rows[]=$this->row(9,[$this->cell(12,''),$this->cell(36,'Whiteboard','table-body'),$this->cell(12,'')]);
                    $rows[]=$this->row(26,[$this->cell(15,'Teacher','table-body'),$this->cell(30,''),$this->cell(15,'Door','room')]);
                    $rows[]=$this->row(7,[$this->cell(60,'')]);
                    // Read down each column, matching the reference classroom plan.
                    for($r=0;$r<5;$r++){
                        $cells=[];
                        for($c=0;$c<5;$c++){
                            $student=$chunk[$c*5+$r]??null;
                            $cells[]=$this->cell(12,$student?[$this->line($student['seat'],22,true,'#ed1c24'),$this->line($student['name'],8.5,true),$this->line('Grade: '.$student['grade_number'],8)]:[],$student?'desk':'body');
                        }
                        $rows[]=$this->row(29,$cells);$rows[]=$this->row(2,[$this->cell(60,'')]);
                    }
                    $pages[]=$meta+['rows'=>$rows];
                }
            }
        }
        $logo=app(ScholarshipExport::class)->rankLogo();
        $logoPath=$logo?\App\Models\BrandingSetting::current()->report_logo_1_path:null;
        return ['title'=>self::DOCUMENTS[$document],'document'=>$document,'pages'=>$pages,'logoSrc'=>$logo,
            'logoUrl'=>$logoPath?asset('storage/'.ltrim($logoPath,'/\\')):null,'pdfExport'=>false];
    }

    private function heading(ScholarshipExam $exam,string $document,array $meta,bool $national,bool $compact=false): array
    {
        $logo=$this->cell($national?24:60,'');$logo['logo']=true;$logo['logo_width']=$compact?35:60;
        $rows=[];
        if($national){
            $title=($document==='attendance'?'Attendance List':'List of Candidates').' for '.$exam->examinationTitle();
            $examDate=$document==='attendance'?$exam->exam_date->format('F j, Y'):$meta['exam_date'];
            $logo['logo_offset_y']=5;
            $motto=$this->cell(18,[
                $this->line('ព្រះរាជាណាចក្រកម្ពុជា',11,false,'#000000','muol')+['space_after'=>1.5],
                $this->line('ជាតិ សាសនា ព្រះមហាក្សត្រ',10,false,'#000000','muol')+['space_after'=>1.5],
                $this->line('KINGDOM OF CAMBODIA',10,true),$this->line('NATION RELIGION KING',10,true),
            ]);
            $motto['role']='national-heading';$motto['offset_y']=-3;$logo['role']='school-logo';
            $rows[]=$this->row(29,[$logo,$this->cell(18,''),$motto]);
            foreach([
                ['title',$title,8,mb_strlen($title)>60?12:16,true],
                ['academic-year','Academic Year: '.$exam->academicYear?->academic_year,4.8,11,false],
                ['exam-date','Exam Date: '.$examDate,4.8,11,false],
                ['exam-center','Exam Center: '.$meta['center'],4.8,11,false],
            ] as [$role,$text,$height,$size,$bold]){
                $cell=$this->cell(60,[$this->line($text,$size,$bold)],$role==='title'?'title':'subtitle');
                $cell['padding_y']=0;$cell['role']=$role;
                $rows[]=$this->row($height,[$cell]);
            }
            return $rows;
        }
        if($document==='attendance-cover'){
            $logo['logo_width']=85;$logo['role']='school-logo';
            $rows[]=$this->row(36,[$logo]);
            $title=$exam->examinationTitle();
            foreach([
                ['title',$title,mb_strlen($title)>60?20:14,mb_strlen($title)>60?16:24,true],
                ['academic-year','Academic Year: '.$exam->academicYear?->academic_year,7,12,false],
                ['exam-date','Exam Date: '.$exam->exam_date->format('F j, Y'),7,12,false],
                ['exam-center','Exam Center: '.$meta['center'],7,12,false],
            ] as [$role,$text,$height,$size,$bold]){
                $cell=$this->cell(60,[$this->line($text,$size,$bold)],$role==='title'?'title':'subtitle');
                $cell['padding_y']=0;$cell['role']=$role;
                $rows[]=$this->row($height,[$cell]);
            }
            return $rows;
        }
        $rows[]=$this->row($compact?12:28,[$logo]);
        $title=$exam->examinationTitle();
        $rows[]=$this->row($compact?6:10,[$this->cell(60,[$this->line($title,$compact?11:(mb_strlen($title)>60?12:16),true)],$compact?'compact-title':'title')]);
        $details='Academic Year: '.$exam->academicYear?->academic_year.'   ·   Exam Date: '.$meta['exam_date'];
        $rows[]=$this->row($compact?5:7,[$this->cell(60,$details,$compact?'small':'subtitle')]);
        $rows[]=$this->row($compact?5:7,[$this->cell(60,'Exam Center: '.$meta['center'],$compact?'small':'subtitle')]);
        return $rows;
    }

    private function signatures(?ScholarshipSeatPrintSetting $setting,ScholarshipExamCenter $center): array
    {
        $rows=[];$location=$center->location?:'Phnom Penh';
        foreach(['date','label','space','name','position'] as $part){
            $cells=[];
            foreach(['prepared'=>'Prepared by','checked'=>'Checked and Verified by','approved'=>'Approved by'] as $key=>$label){
                $date=$setting?->getAttribute($key.'_date');
                $text=match($part){'date'=>$date?$location.', '.$date->format('F j, Y').'.':$location.', ____________________','label'=>$label,'space'=>'','name'=>$setting?->getAttribute($key.'_by_name')?:'____________________','position'=>$setting?->getAttribute($key.'_position')??''};
                $cell=$this->cell(20,[$this->line($text,9,$part==='name')],'signature');
                if($part!=='space')$cell['padding_y']=0;
                $cell['role']='signature-'.$part;
                $cells[]=$cell;
            }
            $rows[]=$this->row($part==='space'?16:4.5,$cells);
        }
        return $rows;
    }

    private function line(mixed $text,float $size=10,bool $bold=false,string $color='#000000',string $font='arial'): array
    {return compact('text','size','bold','color','font');}
    private function cell(int $span,mixed $text='',string $style='body'): array
    {return ['span'=>$span,'lines'=>is_array($text)?$text:[['text'=>$text]],'style'=>$style];}
    private function row(float $height,array $cells): array {return compact('height','cells');}
    private function tableRow(float $height,array $widths,array $values,string $style,?int $left=null): array
    {
        return $this->row($height,array_map(fn($i)=>$this->cell($widths[$i],$values[$i],$i===$left?'table-name':$style),array_keys($widths)));
    }

    /** Styles are shared by the HTML and Excel renderers. Sizes are points. */
    public static function styles(): array
    {
        return [
            'body'=>['size'=>10,'align'=>'center'], 'title'=>['size'=>16,'bold'=>true,'align'=>'center'],
            'compact-title'=>['size'=>11,'bold'=>true,'align'=>'left'], 'subtitle'=>['size'=>11,'align'=>'center'],
            'room'=>['size'=>12,'bold'=>true,'align'=>'left','color'=>'#203c65'], 'small'=>['size'=>8,'align'=>'left'],
            'table-head'=>['size'=>8,'align'=>'center','fill'=>'#fff2cc','border'=>'#000000'],
            'candidate-table-head'=>['size'=>8,'align'=>'center','fill'=>'#dbeafe','border'=>'#000000'],
            'attendance-footer-label'=>['size'=>9,'align'=>'right','fill'=>'#fff2cc','border'=>'#000000'],
            'table-body'=>['size'=>9,'align'=>'center','border'=>'#000000'], 'table-name'=>['size'=>9,'align'=>'left','border'=>'#000000'],
            'signature'=>['size'=>9,'align'=>'center'], 'cover-title'=>['size'=>26,'bold'=>true,'align'=>'center','color'=>'#203c65'],
            'cover-room'=>['size'=>36,'bold'=>true,'align'=>'center','color'=>'#203c65'], 'cover-text'=>['size'=>18,'align'=>'center','color'=>'#203c65'],
            'cover-detail'=>['size'=>12,'align'=>'left','color'=>'#203c65'], 'committee'=>['size'=>14,'bold'=>true,'align'=>'left','fill'=>'#d9e2f3','color'=>'#203c65'],
            'cover-table-head'=>['size'=>14,'bold'=>true,'align'=>'center','color'=>'#203c65','border'=>'#203c65'],
            'cover-table-body'=>['size'=>12,'align'=>'center','border'=>'#203c65'],
            'label'=>['size'=>9,'align'=>'center','border'=>'#203c65','border_weight'=>'medium'], 'desk'=>['size'=>8,'align'=>'center','border'=>'#203c65'],
        ];
    }

    public function pdf(array $data): string
    {
        $temp=storage_path('framework/cache/scholarship-pdf');if(!is_dir($temp))mkdir($temp,0775,true);
        $pdf=new \Mpdf\Mpdf(['mode'=>'utf-8','format'=>'A4','tempDir'=>$temp,'margin_left'=>8,'margin_right'=>8,'margin_top'=>8,'margin_bottom'=>8,'margin_footer'=>3,
            'fontDir'=>[...(new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'],public_path('fonts/khmer')],
            'fontdata'=>(new \Mpdf\Config\FontVariables())->getDefaults()['fontdata']+['scholarshipmuol'=>['R'=>'KhmerOSmuollight.ttf','useOTL'=>0xFF],'scholarshipsiemreap'=>['R'=>'KhmerOSsiemreap.ttf','useOTL'=>0xFF]],'default_font'=>'dejavusans','default_font_size'=>9]);
        $pdf->SetTitle($data['title']);
        if($data['document']!=='attendance')$pdf->SetHTMLFooter('<div style="text-align:right;font-size:8pt">Page {PAGENO} of {nbpg}</div>');
        $pdf->shrink_tables_to_fit=1;
        if($data['logoSrc']){
            $pdf->imageVars['schoolLogo']=base64_decode(explode(',',$data['logoSrc'],2)[1]);
            $data['logoSrc']='var:schoolLogo';
        }
        // Feed one page at a time; repeated sticker logos and large exams must
        // not exceed mPDF's HTML parser limit.
        foreach($data['pages']?:[null] as $index=>$page){
            if($index)$pdf->AddPage();
            $pdf->WriteHTML(view('western-scholarship.seat-report',array_replace($data,['pdfExport'=>true,'pages'=>$page?[$page]:[]]))->render());
        }
        return $pdf->Output('',\Mpdf\Output\Destination::STRING_RETURN);
    }
}
