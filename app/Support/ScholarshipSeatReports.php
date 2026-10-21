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
        $logoNumber=1;
        $logo=app(ScholarshipExport::class)->reportLogo($logoNumber);
        $stickerLogoWidth=$logo && $document==='seat-labels'?$this->stickerLogoWidth($logo):40;
        $stickerTitleSize=$document==='seat-labels'?$this->stickerTitleSize($exam->examinationTitle()):12;
        $exam->loadMissing('academicYear','subjects');
        $candidates->each->loadMissing('student');
        $signatures=ScholarshipSeatPrintSetting::whereIn('exam_center_id',$exam->centers()->select('id'))->get()->keyBy('exam_center_id');
        $centers=$exam->centers()->get()->keyBy('id');
        $duties=$document==='envelope-cover'?$exam->invigilatorDuties()->with(['invigilator','session'])->get():collect();
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
                $rows=$this->heading($exam,$document,$meta,false);
                $rows[]=$this->row(20,[$this->cell(60,'')]);
                $rows[]=$this->row(16,[$this->cell(60,[$this->line('Attendance List',28,true)],'cover-title')]);
                $rows[]=$this->row(18,[$this->cell(60,'Room: '.$meta['room'],'cover-room')]);
                $rows[]=$this->row(18,[$this->cell(60,'')]);
                $rows[]=$this->row(12,[$this->cell(60,'Grade: '.$meta['grade'],'cover-title')]);
                $rows[]=$this->row(13,[$this->cell(60,'Number of Candidates: '.$meta['total'],'cover-text')]);
                $rows[]=$this->row(max(1,262-array_sum(array_column($rows,'height'))),[$this->cell(60,'')]);
                $pages[]=$meta+['rows'=>$rows];
            }elseif($document==='envelope-cover'){
                foreach($subjects->values() as $subjectIndex=>$subject){
                    $schedule=app(ScholarshipSubjectSchedule::class)->forSubject($exam,(int)$first->grade_number,$subject->id);
                    $examTime=$schedule?\Carbon\Carbon::createFromFormat('H:i',$schedule['starts_at'])->format('g:i A')."\u{2013}".\Carbon\Carbon::createFromFormat('H:i',$schedule['ends_at'])->format('g:i A'):'Not scheduled';
                    $assigned=$duties->filter(fn($duty)=>$duty->exam_center_id===$first->exam_center_id
                        && $duty->room_number===$first->room_number && $duty->session?->number===$subjectIndex+1)
                        ->sortBy(fn($duty)=>$duty->invigilator?->name_en)->values();
                    $contacts=$assigned->map(function($duty){
                        $name=$duty->invigilator?->name_en??'';$phone=PhoneNumber::formatCambodian($duty->invigilator?->phone,international:true);
                        return $this->row(max(11,ceil(mb_strlen($name)/30)*5,ceil(mb_strlen($phone)/30)*5),[
                            $this->cell(6,''),$this->cell(24,$name,'cover-table-body'),
                            $this->cell(24,$phone,'cover-table-body'),$this->cell(6,''),
                        ]);
                    })->all();
                    while(count($contacts)<2)$contacts[]=$this->row(11,[$this->cell(6,''),$this->cell(24,'','cover-table-body'),$this->cell(24,'','cover-table-body'),$this->cell(6,'')]);
                    // Keep additional invigilators on complete covers instead of overflowing A4.
                    $chunks=[[]];$height=0;
                    foreach($contacts as $row){
                        if($height+$row['height']>28 && $chunks[array_key_last($chunks)]){$chunks[]=[];$height=0;}
                        $chunks[array_key_last($chunks)][]=$row;$height+=$row['height'];
                    }
                    foreach($chunks as $part=>$chunk){
                        $rows=$this->heading($exam,$document,$meta,false);
                        $rows[]=$this->row(8,[$this->cell(60,'')]);
                        $rows[]=$this->row(16,[$this->cell(60,'Subject: '.$subject->name.($part?' (continued)':''),'cover-title')]);
                        $timeCell=$this->cell(60,[$this->line('Exam Time: '.$examTime,14,false)],'cover-text');$timeCell['role']='exam-time';$timeCell['padding_y']=0;
                        $rows[]=$this->row(7,[$timeCell]);
                        $rows[]=$this->row(22,[$this->cell(60,'Room: '.$meta['room'],'cover-room')]);
                        $rows[]=$this->row(14,[$this->cell(60,'Grade: '.$meta['grade'],'cover-title')]);
                        $rows[]=$this->row(13,[$this->cell(60,'Number of Candidates: '.$meta['total'],'cover-text')]);
                        $rows[]=$this->row(13,[$this->cell(60,'Absent Students: __________','cover-text')]);
                        $rows[]=$this->row(6,[$this->cell(60,'')]);
                        $rows[]=$this->row(10,[$this->cell(6,''),$this->cell(24,'Invigilator(s)','cover-table-head'),$this->cell(24,'Contact Number','cover-table-head'),$this->cell(6,'')]);
                        $rows=[...$rows,...$chunk];
                        $rows[]=$this->row(12,[$this->cell(60,'')]);
                        $rows[]=$this->row(9,[$this->cell(60,'For Central Exam Committee','committee')]);
                        $rows[]=$this->row(6,[$this->cell(60,'')]);
                        $rows[]=$this->row(12,[$this->cell(60,'Number of exam papers received: __________','cover-detail')]);
                        $rows[]=$this->row(12,[$this->cell(60,'Received by: ____________________   Signature: ____________________','cover-detail')]);
                        $rows[]=$this->row(max(1,262-array_sum(array_column($rows,'height'))),[$this->cell(60,'')]);
                        $pages[]=$meta+['subject'=>$subject->name,'session'=>$subjectIndex+1,'rows'=>$rows];
                    }
                }
            }elseif($document==='seat-labels'){
                foreach(array_chunk($students,18) as $chunk){
                    $rows=$this->heading($exam,$document,$meta,false,true);
                    $rows[]=$this->row(3,[$this->cell(60,'')]);
                    foreach(array_chunk($chunk,3) as $line){
                        $cells=[];
                        foreach($line as $student){
                            $seat=$this->line('Seat No. '.$student['seat'],26);
                            $seat['role']='sticker-seat';
                            $seat['runs']=[$this->line('Seat No. ',6),$this->line($student['seat'],26,true,'#ed1c24')];
                            $cell=$this->cell(20,[
                                $this->line($exam->examinationTitle(),$stickerTitleSize,true),$seat,
                                $this->line($student['name'],$this->stickerTextSize($student['name'],10.5),true)+['space_after'=>1],
                                $this->line('Grade: '.$student['grade_number'].'   ·   Room: '.$meta['room'],8),
                            ],'label');
                            $cell['logo']=true;$cell['logo_width']=$stickerLogoWidth;$cell['logo_height']=14;$cell['sticker_title']=true;$cells[]=$cell;
                        }
                        while(count($cells)<3)$cells[]=$this->cell(20,'');
                        $rows[]=$this->row(40,$cells);
                        $rows[]=$this->row(1.5,[$this->cell(60,'')]);
                    }
                    $pages[]=$meta+['rows'=>$rows];
                }
            }else{
                foreach(array_chunk($students,25) as $index=>$chunk){
                    $rows=$this->heading($exam,$document,$meta,false);
                    $planStart=count($rows);
                    $planSuffix='   |   Grade: '.$meta['grade'].(count($students)>25?'   |   Part '.($index+1):'');
                    $planTitle=$this->line('Room: '.$meta['room'].$planSuffix,12,true,'#203c65');
                    $planTitle['runs']=[
                        $this->line('Room: ',12,true,'#203c65'),
                        $this->line($meta['room'],20,true,'#ed1c24'),
                        $this->line($planSuffix,12,true,'#203c65'),
                    ];
                    $rows[]=$this->row(10,[$this->cell(60,[$planTitle],'room')]);
                    $board=$this->cell(60,'Whiteboard','classroom-symbol')+['role'=>'classroom-whiteboard','illustration'=>'whiteboard','illustration_width'=>130,'illustration_height'=>10];
                    $rows[]=$this->row(16,[$board]);
                    $rows[]=$this->row(20,[$this->cell(60,'')]);
                    // Alternate down and up without changing saved seat numbers.
                    for($r=0;$r<5;$r++){
                        $cells=[$this->cell(1,'')];
                        for($c=0;$c<5;$c++){
                            $student=$chunk[$c*5+($c%2===0?$r:4-$r)]??null;
                            $cells[]=$this->cell(10,$student?[
                                $this->line($student['seat'],22,true,'#ed1c24')+['space_after'=>1.5],
                                $this->line($student['name'],$this->stickerTextSize($student['name'],8.5,30),true)+['space_after'=>1.5],
                                $this->line('Grade: '.$student['grade_number'],8),
                            ]:[],$student?'desk':'empty-desk');
                            if($c<4)$cells[]=$this->cell(2,'');
                        }
                        $cells[]=$this->cell(1,'');
                        $rows[]=$this->row(27,$cells);
                        if($r<4)$rows[]=$this->row(3,[$this->cell(60,'')]);
                    }
                    $rows[]=$this->row(4,[$this->cell(60,'')]);
                    foreach($rows as $rowIndex=>&$row)if($rowIndex>=$planStart)$row['area']='classroom';unset($row);
                    $pages[]=$meta+['rows'=>$rows];
                }
            }
        }
        $logoPath=$logo?\App\Models\BrandingSetting::current()->getAttribute('report_logo_'.$logoNumber.'_path'):null;
        return ['title'=>self::DOCUMENTS[$document],'document'=>$document,'pages'=>$pages,'logoSrc'=>$logo,
            'logoLabel'=>'School Logo '.$logoNumber,'logoUrl'=>$logoPath?asset('storage/'.ltrim($logoPath,'/\\')):null,'pdfExport'=>false];
    }

    /** Preserve the artwork's proportions within the sticker's 40 x 14 mm logo area. */
    private function stickerLogoWidth(string $source): float
    {
        $bytes=base64_decode(explode(',',$source,2)[1]??'',true);
        $size=$bytes===false?false:@getimagesizefromstring($bytes);
        if(!$size && str_starts_with($source,'data:image/svg+xml;') && $bytes!==false){
            $svg=new \DOMDocument();
            if(@$svg->loadXML($bytes,LIBXML_NONET) && $svg->documentElement?->localName==='svg'){
                $box=preg_split('/[\s,]+/',trim($svg->documentElement->getAttribute('viewBox')));
                if(count($box)===4 && (float)$box[2]>0 && (float)$box[3]>0)$size=[(float)$box[2],(float)$box[3]];
            }
        }
        return $size && $size[1]>0?min(40,14*$size[0]/$size[1]):14;
    }

    private function stickerTitleSize(string $title): float
    {return $this->stickerTextSize($title,13);}

    private function stickerTextSize(string $text,float $maximum,float $availableWidth=55,bool $bold=true): float
    {
        return round(max($bold?5:3,min($maximum,12*$availableWidth/max(1,$this->reportTextWidth($text,$bold)))),2);
    }

    /** Width in millimeters at 12 pt. */
    private function reportTextWidth(string $text,bool $bold): float
    {
        $font=base_path('vendor/mpdf/mpdf/ttfonts/'.($bold?'DejaVuSans-Bold.ttf':'DejaVuSans.ttf'));
        $box=function_exists('imagettfbbox') && is_file($font)?imagettfbbox(12,0,$font,$text):false;
        // GD measures points at 96 DPI. Leave a little room inside the sticker border.
        return $box?(max($box[0],$box[2],$box[4],$box[6])-min($box[0],$box[2],$box[4],$box[6]))*25.4/96:mb_strlen($text)*2.3;
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
        if(in_array($document,['attendance-cover','envelope-cover'],true)){
            $logo['logo_width']=85;$logo['role']='school-logo';
            $rows[]=$this->row(36,[$logo]);
            if($document==='attendance-cover')$rows[]=$this->row(8,[$this->cell(60,'')]);
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
        if($document==='seat-labels'){
            $title=$exam->examinationTitle();
            $titleCell=$this->cell(48,[$this->line($title,mb_strlen($title)>60?12:16,true)],'small');
            $titleCell['role']='title';$titleCell['padding_y']=0;
            $room=$this->line('Room: '.$meta['room'],18);
            $room['runs']=[$this->line('Room: ',18),$this->line($meta['room'],18,true,'#ed1c24')];
            $roomCell=$this->cell(12,[$room,$this->line('Grade: '.$meta['grade'],10)],'sticker-header-room');$roomCell['role']='sticker-header-room';$roomCell['padding_y']=0;
            $rows[]=$this->row(7,[$titleCell,$roomCell]);
            $prefix='Academic Year: '.$exam->academicYear?->academic_year.' | Exam Center: '.$meta['center'].' | Exam Date: '.$exam->exam_date->format('F j, Y').' | Number of Candidates: ';
            $text=$prefix.$meta['total'];
            $numberWidth=$this->reportTextWidth((string)$meta['total'],true);
            $size=$this->stickerTextSize($prefix,10,137-$numberWidth,false);
            $subtitle=$this->line($text,$size);
            $subtitle['runs']=[
                $this->line($prefix,$size),$this->line($meta['total'],12,true,'#ed1c24'),
            ];
            $cell=$this->cell(60,[$subtitle],'small');$cell['role']='sticker-subtitle';$cell['padding_y']=0;
            $rows[]=$this->row(5,[$cell]);
            return $rows;
        }
        $rows[]=$this->row($compact?12:($document==='room-plan'?24:28),[$logo]);
        $title=$exam->examinationTitle();
        $titleSize=$compact?11:(mb_strlen($title)>60?12:16);
        if($document==='room-plan')$titleSize=mb_strlen($title)>60?16:20;
        $rows[]=$this->row($compact?6:10,[$this->cell(60,[$this->line($title,$titleSize,true)],$compact?'compact-title':'title')]);
        if($document==='room-plan'){
            foreach([
                ['academic-year','Academic Year: '.$exam->academicYear?->academic_year],
                ['exam-date','Exam Date: '.$exam->exam_date->format('F j, Y')],
                ['exam-center','Exam Center: '.$meta['center']],
            ] as [$role,$text]){
                $cell=$this->cell(60,$text,'subtitle');$cell['role']=$role;$cell['padding_y']=0;
                $rows[]=$this->row(5,[$cell]);
            }
            $rows[]=$this->row(3,[$this->cell(60,'')]);
            $label=$this->cell(60,[$this->line('Seat Plan',16,true,'#203c65')],'title');$label['role']='seat-plan-label';$label['padding_y']=0;
            $rows[]=$this->row(10,[$label]);
            return $rows;
        }
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
            'sticker-header-room'=>['size'=>12,'align'=>'right'],
            'table-head'=>['size'=>8,'align'=>'center','fill'=>'#fff2cc','border'=>'#000000'],
            'candidate-table-head'=>['size'=>8,'align'=>'center','fill'=>'#dbeafe','border'=>'#000000'],
            'attendance-footer-label'=>['size'=>9,'align'=>'right','fill'=>'#fff2cc','border'=>'#000000'],
            'table-body'=>['size'=>9,'align'=>'center','border'=>'#000000'], 'table-name'=>['size'=>9,'align'=>'left','border'=>'#000000'],
            'signature'=>['size'=>9,'align'=>'center'], 'cover-title'=>['size'=>26,'bold'=>true,'align'=>'center','color'=>'#203c65'],
            'cover-room'=>['size'=>36,'bold'=>true,'align'=>'center','color'=>'#203c65'], 'cover-text'=>['size'=>18,'align'=>'center','color'=>'#203c65'],
            'cover-detail'=>['size'=>12,'align'=>'left','color'=>'#203c65'], 'committee'=>['size'=>14,'bold'=>true,'align'=>'left','fill'=>'#d9e2f3','color'=>'#203c65'],
            'cover-table-head'=>['size'=>14,'bold'=>true,'align'=>'center','color'=>'#203c65','border'=>'#203c65'],
            'cover-table-body'=>['size'=>12,'align'=>'center','border'=>'#203c65'],
            'empty-desk'=>['size'=>8,'align'=>'center','border'=>'#cbd5e1'], 'classroom-symbol'=>['size'=>9,'bold'=>true,'align'=>'center','color'=>'#334155'],
            'label'=>['size'=>9,'align'=>'center','border'=>'#203c65','border_weight'=>'medium'], 'desk'=>['size'=>8,'align'=>'center','border'=>'#203c65'],
        ];
    }

    public function pdf(array $data): string
    {
        $temp=storage_path('framework/cache/scholarship-pdf');if(!is_dir($temp))mkdir($temp,0775,true);
        $pdf=new \Mpdf\Mpdf(['mode'=>'utf-8','format'=>'A4','orientation'=>($data['orientation']??'portrait')==='landscape'?'L':'P','tempDir'=>$temp,'margin_left'=>8,'margin_right'=>8,'margin_top'=>$data['document']==='score-list'?4:8,'margin_bottom'=>8,'margin_footer'=>3,
            'fontDir'=>[...(new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'],public_path('fonts/khmer')],
            'fontdata'=>(new \Mpdf\Config\FontVariables())->getDefaults()['fontdata']+['scholarshipmuol'=>['R'=>'KhmerOSmuollight.ttf','useOTL'=>0xFF],'scholarshipsiemreap'=>['R'=>'KhmerOSsiemreap.ttf','useOTL'=>0xFF]],'default_font'=>'dejavusans','default_font_size'=>9]);
        $pdf->SetTitle($data['title']);
        if($data['document']==='score-list'){
            // Use the preview's rotated graphic at the same physical position.
            // mPDF does not support CSS transforms or relative positioning on spans.
            $pdf->imageVars['verificationStamp']=base64_decode(explode(',',$data['verificationStampSrc'],2)[1]);
            $pdf->SetWatermarkImage('var:verificationStamp',1,[40,16],[246,27]);
            $pdf->showWatermarkImage=true;
        }
        if(!in_array($data['document'],['attendance','attendance-cover','envelope-cover','room-plan','score-list','result-list'],true))$pdf->SetHTMLFooter('<div style="text-align:right;font-size:8pt">Page {PAGENO} of {nbpg}</div>');
        $pdf->shrink_tables_to_fit=1;
        if($data['logoSrc']){
            $pdf->imageVars['schoolLogo']=base64_decode(explode(',',$data['logoSrc'],2)[1]);
            $data['logoSrc']='var:schoolLogo';
        }
        // Feed one page at a time; repeated sticker logos and large exams must
        // not exceed mPDF's HTML parser limit.
        foreach($data['pages']?:[null] as $index=>$page){
            if($index)$pdf->AddPage();
            $view=match($data['document']){'score-list'=>'western-scholarship.score-report','result-list'=>'western-scholarship.result-report',default=>'western-scholarship.seat-report'};
            $pdf->WriteHTML(view($view,array_replace($data,['pdfExport'=>true,'pages'=>$page?[$page]:[]]))->render());
        }
        return $pdf->Output('',\Mpdf\Output\Destination::STRING_RETURN);
    }
}
