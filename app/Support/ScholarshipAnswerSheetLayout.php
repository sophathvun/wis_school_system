<?php
namespace App\Support;

use App\Models\{ScholarshipAnswerSheet,ScholarshipTeacherKeySheet};
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Illuminate\Validation\ValidationException;

/** Fixed A4 geometry shared by printing, detection and regression fixtures. */
class ScholarshipAnswerSheetLayout
{
    public const VERSION=6;
    public const WIDTH=1000;
    public const HEIGHT=1414;
    public const MARKERS=[[35,35],[965,35],[965,1379],[35,1379]];
    public const CODE_X=740, CODE_Y=205, CELL=10;

    public function questions(int $count, array $choices,int $layoutVersion=1): array
    {
        if($layoutVersion>=2)return $this->sampleQuestions($count,$choices,$layoutVersion);
        $rows=[];
        // Preserve the original coordinates for issued 2–5-choice sheets.
        // Wider ranges use two columns with enough rows for all 100 questions.
        $wide=count($choices)>5;
        $perColumn=$wide?max(25,(int)ceil($count/2)):25;
        $rowStep=$wide?min(30,720/max(1,$perColumn-1)):30;
        $choiceStep=$wide?min(29,370/max(1,count($choices)-1)):29;
        $radius=min(8,$rowStep/2-1.5,$choiceStep/2-1.5);
        for($i=0;$i<$count;$i++) {
            $x=65+intdiv($i,$perColumn)*($wide?460:230); $y=420+($i%$perColumn)*$rowStep;
            $rows[]=['number'=>$i+1,'x'=>$x,'y'=>$y,'heading'=>$i%$perColumn===0,'font'=>min(16,$rowStep*.7,$choiceStep*.75),
                'dots'=>array_map(fn($j)=>['label'=>(string)$choices[$j],'x'=>(int)round($x+48+$j*$choiceStep),'y'=>(int)round($y),'radius'=>$radius],array_keys($choices))];
        }
        return $rows;
    }
    /** Evenly spaced rows in three columns; retain the middle gap on older sheets. */
    private function sampleQuestions(int $count,array $choices,int $layoutVersion): array
    {
        $rows=[];$wide=count($choices)>6;$columns=$wide?2:3;$perColumn=max(16,(int)ceil($count/$columns));
        $gap=$layoutVersion===2?($perColumn>20?12:30):0;$rowStep=min(48,(770-$gap)/max(1,$perColumn-1));
        $choiceStep=min(34,($wide?350:136)/max(1,count($choices)-1));
        $radius=min(15,$rowStep*.40,$choiceStep*.44);
        for($i=0;$i<$count;$i++){
            $column=intdiv($i,$perColumn);$row=$i%$perColumn;
            $x=($wide?65:145)+$column*($wide?460:245);$y=450+$row*$rowStep+($row>=intdiv($perColumn,2)?$gap:0);
            $rows[]=['number'=>$i+1,'x'=>$x,'y'=>$y,'heading'=>false,'font'=>min(25,$rowStep*.68),
                'label_font'=>$radius*.9,'dots'=>array_map(fn($j)=>['label'=>(string)$choices[$j],
                    'x'=>(int)round($x+50+$j*$choiceStep),'y'=>(int)round($y),'radius'=>$radius],array_keys($choices))];
        }
        return $rows;
    }
    public function bits(string $token): array
    {
        return str_split(implode('',array_map(fn($c)=>str_pad(decbin(hexdec($c)),4,'0',STR_PAD_LEFT),str_split($token))));
    }
    public function svg(ScholarshipAnswerSheet $sheet, ?string $logo=null): string
    {return $this->render($sheet->snapshot,$sheet->token,$logo,false);}
    public function teacherSvg(ScholarshipTeacherKeySheet $sheet,?string $logo=null): string
    {return $this->render($sheet->snapshot,$sheet->token,$logo,true);}
    private function render(array $s,string $token,?string $logo,bool $teacher): string
    {
        if(!$teacher&&($s['layout_version']??1)>=4)return $this->schoolSvg($s,$token,$logo);
        if(($s['layout_version']??1)>=2)return $this->sampleSvg($s,$token,$logo,$teacher);
        $e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_XML1,'UTF-8');
        $svg='<svg xmlns="http://www.w3.org/2000/svg" width="210mm" height="297mm" viewBox="0 0 1000 1414" role="img" aria-label="'.($teacher?'Teacher answer key':'Personalized exam answer sheet').'"><rect width="1000" height="1414" fill="white"/>';
        foreach(self::MARKERS as [$x,$y])$svg.='<rect x="'.($x-10).'" y="'.($y-10).'" width="20" height="20" fill="black"/>';
        if($logo)$svg.='<image href="'.$e($logo).'" x="65" y="60" width="210" height="65" preserveAspectRatio="xMinYMid meet"/>';
        $qr='data:image/png;base64,'.base64_encode((new Writer(new GDLibRenderer(240,4)))->writeString(($teacher?'WIS-AK:':'WIS-AS:').$token));
        $svg.='<image href="'.$qr.'" x="825" y="65" width="100" height="100"/>';
        $text=function($x,$y,$value,$size=17,$anchor='start')use(&$svg,$e){$svg.='<text x="'.$x.'" y="'.$y.'" font-family="Arial, sans-serif" font-size="'.$size.'" text-anchor="'.$anchor.'" fill="black">'.$e($value).'</text>';};
        $text(500,145,$teacher?'WIS SCHOLARSHIP — ANSWER KEY':'WIS SCHOLARSHIP — ANSWER SHEET',25,'middle');
        $text(65,185,'Academic Year: '.$s['year'].'   |   Exam Date: '.$s['date']);
        $text(65,220,$teacher?'Teacher Name: __________________________________':('Name: '.mb_strimwidth($s['name'],0,66,'…')));
        $text(65,250,$teacher?'Date: ____________________':('Student ID: '.$s['student_code'].'   |   Code: '.$s['code']));
        $text(65,280,'Grade: '.$s['grade'].'   |   '.$s['subject'].'   |   Paper: '.$s['paper_version']);
        $text(65,310,$teacher?'Teacher answer key — not a student answer sheet':('Center: '.mb_strimwidth($s['center'],0,42,'…').'   |   Room: '.$s['room'].'   Seat: '.$s['seat']));
        foreach($this->bits($token) as $i=>$bit)if($bit==='1')$svg.='<rect x="'.(self::CODE_X+($i%12)*self::CELL).'" y="'.(self::CODE_Y+intdiv($i,12)*self::CELL).'" width="10" height="10" fill="black"/>';
        $text(740,302,$teacher?'Teacher key identification':'Sheet identification',12);
        $text(65,350,$teacher?'Fill ONE correct-answer circle per question. Erase changes thoroughly.':'Fill ONE circle completely for each question. Erase changes thoroughly.',17);
        $text(65,373,'Keep the four corner squares and identification code clear. Do not fold.',15);
        foreach($this->questions($s['question_count'],$s['choices']) as $row){
            if($row['heading'])foreach($row['dots'] as $dot)$text($dot['x'],400,$dot['label'],$row['font'],'middle');
            $text($row['x'],$row['y']+4,$row['number'].'.',$row['font']);
            foreach($row['dots'] as $dot)$svg.='<circle cx="'.$dot['x'].'" cy="'.$dot['y'].'" r="'.$dot['radius'].'" fill="white" stroke="black" stroke-width="1.4"/>';
        }
        $text(65,1215,'Questions: '.$s['question_count'].'   |   Maximum score: '.$s['max_score']);
        $text(65,1240,'Choices: '.implode(' / ',$s['choices']),count($s['choices'])>10?12:17);
        $text(65,1280,$teacher?'Teacher signature: ____________________   Checked by: ____________________':'Candidate signature: ____________________   Invigilator: ____________________',16);
        $text(500,1330,($teacher?'Teacher Key ID: ':'Sheet ID: ').$token.'   ·   Print A4 at 100% / Actual size',13,'middle');
        return $svg.'</svg>';
    }
    /** School header from QUESTION-SHEET.jpg; bubble and identity coordinates stay fixed. */
    private function schoolSvg(array $s,string $token,?string $logo): string
    {
        $e=fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_XML1,'UTF-8');
        $svg='<svg xmlns="http://www.w3.org/2000/svg" width="210mm" height="297mm" viewBox="0 0 1000 1414" role="img" aria-label="Student scholarship answer sheet"><rect width="1000" height="1414" fill="white"/>';
        $text=function($x,$y,$value,$size=18,$bold=false,$anchor='start',$color='black')use(&$svg,$e){
            $svg.='<text x="'.$x.'" y="'.$y.'" font-family="Arial, sans-serif" font-size="'.$size.'" font-weight="'.($bold?'700':'400').'" text-anchor="'.$anchor.'" fill="'.$color.'">'.$e($value).'</text>';
        };
        $fit=fn($value,$size,$width)=>min($size,$width/max(1,mb_strwidth((string)$value))/.60);
        foreach(self::MARKERS as [$x,$y])$svg.='<rect x="'.($x-10).'" y="'.($y-10).'" width="20" height="20" fill="black"/>';
        if($logo)$svg.='<image href="'.$e($logo).'" x="55" y="55" width="125" height="135" preserveAspectRatio="xMidYMid meet"/>';
        $text(195,87,'SCHOLARSHIP EXAMINATION',29,true);
        $text(200,113,'Academic Year: '.$s['year'],21);
        $date='Exam Date: '.$s['date'];$text(200,140,$date,$fit($date,21,490));
        $subject=$s['subject'].' - Grade '.$s['grade'];$text(200,187,$subject,$fit($subject,42,500),true);
        $text(710,85,'Invigilators',23);
        foreach([1=>130,2=>172] as $number=>$y){$text(710,$y,$number.'.',17);$svg.='<line x1="730" y1="'.$y.'" x2="945" y2="'.$y.'" stroke="#aaa" stroke-width=".7"/>';}
        $text(70,233,'Name',21);$text(174,233,':',21);$text(195,233,$s['name'],$fit($s['name'],28,510),true);
        $details='ID: '.$s['student_code'].' | Grade '.($s['class_label']??$s['grade']);
        if(trim((string)($s['campus']??''))!=='')$details.=' | '.trim($s['campus']);
        $text(195,253,$details,$fit($details,16,510));
        $text(70,284,'Room No.',21);$text(174,284,':',21);$text(202,284,$s['room'],28,true);
        $text(250,284,'Seat No.:',21);$text(345,284,$s['seat'],28,true);
        if($s['layout_version']>=5){
            [$x,$y,$width,$height]=ScholarshipAbsenceMark::box($s['layout_version']);
            $text(455,$y+17,'Absent',14,true);$text(455,$y+33,'Invigilator only',10);
            $svg.='<rect x="'.$x.'" y="'.$y.'" width="'.$width.'" height="'.$height.'" rx="2" fill="white" stroke="black" stroke-width="1.2"/>';
            $text(603,$y+24,'Write A',12);
        }
        foreach($this->bits($token) as $i=>$bit)if($bit==='1')$svg.='<rect x="'.(self::CODE_X+($i%12)*self::CELL).'" y="'.(self::CODE_Y+intdiv($i,12)*self::CELL).'" width="10" height="10" fill="black"/>';
        $text(740,298,'Sheet identification',11);
        $qr='data:image/png;base64,'.base64_encode((new Writer(new GDLibRenderer(264,4)))->writeString('WIS-AS:'.$token));
        // Keep the four-module quiet zone while aligning the visible 80px QR pattern with the sheet code.
        $svg.='<image href="'.$qr.'" x="861.2" y="192.2" width="105.6" height="105.6" preserveAspectRatio="xMidYMid meet"/>';
        $code='Code: '.$s['code'];$text(914,300,$code,$fit($code,9,88),false,'middle');
        $svg.='<rect x="52" y="310" width="896" height="68" rx="6" fill="white" stroke="#666" stroke-width=".8"/>';
        $range='('.$s['choices'][0].')–('.end($s['choices']).')';
        $instructions='Problems 1 to '.$s['question_count'].'. Choose the best answer from '.$range.' and completely fill in the';
        $instructionSize=min(17,640/max(1,mb_strwidth($instructions))/.48);
        $text(73,340,$instructions,$instructionSize);
        $text(73,361,'circle for your answer. Erase cleanly any answer you wish to change.',$instructionSize);
        $svg.='<line x1="730" y1="317" x2="730" y2="371" stroke="#777" stroke-width=".7"/><line x1="830" y1="317" x2="830" y2="371" stroke="#777" stroke-width=".7"/>';
        $text(744,328,'✓',18,true,'start','#12ad32');$text(761,328,'CORRECT',11);
        $text(840,328,'×',18,true,'start','#ed2024');$text(858,328,'WRONG',11);
        // These examples are ellipses so answer-circle tools only target real questions.
        $svg.='<ellipse cx="780" cy="352" rx="14" ry="14" fill="#888" stroke="black" stroke-width="1.5"/>';
        foreach([850,884,918] as $x)$svg.='<ellipse cx="'.$x.'" cy="352" rx="14" ry="14" fill="white" stroke="black" stroke-width="1.5"/>';
        $svg.='<ellipse cx="850" cy="356" rx="10" ry="5" fill="black"/><path d="M 878 344 L 890 360 M 890 344 L 878 360 M 910 352 L 916 359 L 928 342" fill="none" stroke="black" stroke-width="2.5"/>';
        $text(500,418,'Answer',40,true,'middle');
        foreach($this->questions($s['question_count'],$s['choices'],$s['layout_version']) as $row){
            $text($row['x']+20,$row['y']+$row['font']*.32,$row['number'],$row['font'],false,'end');
            foreach($row['dots'] as $dot){
                $svg.='<circle cx="'.$dot['x'].'" cy="'.$dot['y'].'" r="'.$dot['radius'].'" fill="white" stroke="#333" stroke-width="1.2"/>';
                $labelFont=$row['label_font']/(strlen($dot['label'])>1?1.25:1);
                $text($dot['x'],$dot['y']+$labelFont*.34,$dot['label'],$labelFont,false,'middle','#555');
            }
        }
        $text(500,1342,'Sheet ID: '.$token.' · A4 · Actual size / 100% · Keep all four corner squares clear',12,false,'middle');
        return $svg.'</svg>';
    }
    private function sampleSvg(array $s,string $token,?string $logo,bool $teacher): string
    {
        $e=fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_XML1,'UTF-8');
        $svg='<svg xmlns="http://www.w3.org/2000/svg" width="210mm" height="297mm" viewBox="0 0 1000 1414" role="img" aria-label="'.($teacher?'Teacher scholarship answer key':'Student scholarship answer sheet').'"><rect width="1000" height="1414" fill="white"/>';
        $text=function($x,$y,$value,$size=18,$bold=false,$anchor='start',$color='black')use(&$svg,$e){
            $svg.='<text x="'.$x.'" y="'.$y.'" font-family="Arial, sans-serif" font-size="'.$size.'" font-weight="'.($bold?'700':'400').'" text-anchor="'.$anchor.'" fill="'.$color.'">'.$e($value).'</text>';
        };
        foreach(self::MARKERS as [$x,$y])$svg.='<rect x="'.($x-10).'" y="'.($y-10).'" width="20" height="20" fill="black"/>';
        if($logo)$svg.='<image href="'.$e($logo).'" x="55" y="48" width="125" height="90" preserveAspectRatio="xMinYMid meet"/>';
        $text(195,68,'SCHOLARSHIP EXAMINATION',29,true);
        $text(195,101,'Academic Year: '.$s['year'],25);
        $subject=$s['subject'].', Grade '.$s['grade'];$text(195,133,$subject,min(27,660/max(1,mb_strwidth($subject))/.6),true);
        $text(755,60,'Invigilators',18);$text(755,99,'1. ___________________',17);$text(755,133,'2. ___________________',17);
        $svg.='<rect x="45" y="150" width="910" height="50" fill="#dedede" stroke="black" stroke-width="1.3"/>';
        $range='('.$s['choices'][0].')–('.end($s['choices']).')';
        $text(55,171,'• Problems 1 to '.$s['question_count'].'. Choose the best answer from '.$range.' and completely fill in',17,true);
        $text(55,192,'the circle for your answer. Erase changes thoroughly.',17,true);
        $meta='Exam Date: '.$s['date'].($teacher?'':'  |  Center: '.$s['center']).'  |  Paper: '.$s['paper_version'];
        $text(55,235,$meta,min(14,650/max(1,mb_strwidth($meta))/.6));
        $text(55,258,$teacher?'Teacher answer key — fill one correct answer per question':('Student ID: '.$s['student_code'].'  |  Candidate Code: '.$s['code']),14);
        foreach($this->bits($token) as $i=>$bit)if($bit==='1')$svg.='<rect x="'.(self::CODE_X+($i%12)*self::CELL).'" y="'.(self::CODE_Y+intdiv($i,12)*self::CELL).'" width="10" height="10" fill="black"/>';
        $text(740,299,$teacher?'Teacher key identification':'Sheet identification',11);
        $qr='data:image/png;base64,'.base64_encode((new Writer(new GDLibRenderer(240,4)))->writeString(($teacher?'WIS-AK:':'WIS-AS:').$token));
        $svg.='<image href="'.$qr.'" x="875" y="215" width="70" height="70"/>';
        $line=function($x1,$y,$x2)use(&$svg){$svg.='<line x1="'.$x1.'" y1="'.$y.'" x2="'.$x2.'" y2="'.$y.'" stroke="black" stroke-width="1.5"/>';};
        $text($teacher?65:125,327,$teacher?'Teacher Name:':'Full Name:',23);$line(250,335,720);
        if(!$teacher)$text(255,327,$s['name'],min(22,455/max(1,mb_strwidth($s['name']))/.6));
        $text(735,327,'Grade:',23);$line(810,335,935);$text(815,327,$s['grade'],22);
        $text(125,369,$teacher?'Date:':'Room No.:',23);$line(250,377,465);if(!$teacher)$text(260,369,$s['room'],22);
        $text(490,369,$teacher?'Paper:':'Seat No.:',23);$line(610,377,935);$text(620,369,$teacher?$s['paper_version']:$s['seat'],22);
        $text(500,418,$teacher?'ANSWER KEY':'Answer',32,true,'middle');
        foreach($this->questions($s['question_count'],$s['choices'],$s['layout_version']) as $row){
            // Bring labels closer to their answers while preserving all scan coordinates.
            $text($row['x']+20,$row['y']+$row['font']*.32,$row['number'],$row['font'],false,'end');
            foreach($row['dots'] as $dot){
                $svg.='<circle cx="'.$dot['x'].'" cy="'.$dot['y'].'" r="'.$dot['radius'].'" fill="white" stroke="#333" stroke-width="1.2"/>';
                $labelFont=$row['label_font']/(strlen($dot['label'])>1?1.25:1);
                $text($dot['x'],$dot['y']+$labelFont*.34,$dot['label'],$labelFont,false,'middle','#555');
            }
        }
        $text(65,1280,'Questions: '.$s['question_count'].'  |  Maximum score: '.$s['max_score'],15);
        $text(65,1310,$teacher?'Teacher signature: ____________________    Checked by: ____________________':'Candidate signature: ____________________    Invigilator: ____________________',16);
        $text(500,1342,($teacher?'Teacher Key ID: ':'Sheet ID: ').$token.'  ·  A4 · Actual size / 100% · Keep all four corner squares clear',12,false,'middle');
        return $svg.'</svg>';
    }
    private function dark(\GdImage $image,int $x,int $y,int $radius=3): float
    {
        $dark=0;$total=0;
        for($dy=-$radius;$dy<=$radius;$dy++)for($dx=-$radius;$dx<=$radius;$dx++){
            if($dx*$dx+$dy*$dy>$radius*$radius)continue;
            $rgb=imagecolorat($image,$x+$dx,$y+$dy);
            $luma=.2126*(($rgb>>16)&255)+.7152*(($rgb>>8)&255)+.0722*($rgb&255);
            $dark+=(int)($luma<155);$total++;
        }
        return $dark/max(1,$total);
    }
    /** Fit a row to its printed bubble outlines within a bounded scan tolerance. */
    private function registerBubbleRow(\GdImage $image,array $row): array
    {
        // Paper/scan distortion can shift a small bubble a few pixels even when
        // all four page markers align. Fit the printed outlines, not the answer.
        $radius=$row['dots'][0]['radius'];$limit=min(3,max(1,(int)floor($radius*.35)));
        $best=-INF;$bestX=0;$bestY=0;
        for($dy=-$limit;$dy<=$limit;$dy++)for($dx=-$limit;$dx<=$limit;$dx++){
            $scores=[];
            foreach($row['dots'] as $dot){
                $score=0;
                for($i=0;$i<24;$i++){
                    $angle=$i*M_PI/12;$cos=cos($angle);$sin=sin($angle);$samples=[];
                    foreach([.70,1,1.30] as $scale){
                        $rgb=imagecolorat($image,(int)round($dot['x']+$dx+$cos*$radius*$scale),(int)round($dot['y']+$dy+$sin*$radius*$scale));
                        $samples[]=.2126*(($rgb>>16)&255)+.7152*(($rgb>>8)&255)+.0722*($rgb&255);
                    }
                    $score+=($samples[0]+$samples[2])/2-$samples[1];
                }
                $scores[]=$score/24;
            }
            // Empty outlines have the clearest boundary. Ignore up to two filled
            // circles during fitting, without changing their mark classification.
            rsort($scores);$scores=array_slice($scores,0,max(1,count($scores)-2));
            $score=array_sum($scores)/count($scores)-($dx*$dx+$dy*$dy)*.2;
            if($score>$best){$best=$score;$bestX=$dx;$bestY=$dy;}
        }
        foreach($row['dots'] as &$dot){$dot['x']+=$bestX;$dot['y']+=$bestY;}unset($dot);
        return $row;
    }
    /** Local paper brightness keeps pencil shading readable after scanning. */
    private function bubbleThreshold(\GdImage $image,array $dot): float
    {
        $paper=[];
        // Sample outside the outline, avoiding the choice letter and its fill.
        for($i=0;$i<32;$i++){
            $angle=$i*M_PI/16;$radius=$dot['radius']*1.45;
            $rgb=imagecolorat($image,(int)round($dot['x']+cos($angle)*$radius),(int)round($dot['y']+sin($angle)*$radius));
            $paper[]=.2126*(($rgb>>16)&255)+.7152*(($rgb>>8)&255)+.0722*($rgb&255);
        }
        sort($paper);$background=$paper[24];
        // Light pencil still needs contrast against the local paper. Retain a
        // noise margin so paper texture and near-white erasures stay unmarked.
        return $background-max(26,$background*.12);
    }
    /** Sample outside the printed choice letter and inside the circle outline. */
    private function bubbleRing(\GdImage $image,array $dot): float
    {
        $dark=0;$total=0;$outer=$dot['radius']*.78;$inner=$dot['radius']*.60;$limit=(int)ceil($outer);$threshold=$this->bubbleThreshold($image,$dot);
        for($dy=-$limit;$dy<=$limit;$dy++)for($dx=-$limit;$dx<=$limit;$dx++){
            $distance=$dx*$dx+$dy*$dy;if($distance<$inner*$inner||$distance>$outer*$outer)continue;
            $rgb=imagecolorat($image,$dot['x']+$dx,$dot['y']+$dy);
            $dark+=(int)(.2126*(($rgb>>16)&255)+.7152*(($rgb>>8)&255)+.0722*($rgb&255)<$threshold);$total++;
        }
        return $dark/max(1,$total);
    }
    /** Read only aligned image pixels; client-submitted answers/scores are ignored. */
    public function read(string $bytes, ?array $configuration=null): array
    {
        $size=@getimagesizefromstring($bytes);
        if(!$size||!in_array($size['mime'],['image/png','image/jpeg','image/webp'],true)||$size[0]*$size[1]>24000000||$size[0]<700||abs($size[0]/$size[1]-self::WIDTH/self::HEIGHT)>.012)
            throw ValidationException::withMessages(['image'=>'Align all four corner squares before uploading this sheet.']);
        $source=@imagecreatefromstring($bytes);
        if(!$source)throw ValidationException::withMessages(['image'=>'This photo cannot be decoded.']);
        $image=imagecreatetruecolor(self::WIDTH,self::HEIGHT);
        imagefill($image,0,0,imagecolorallocate($image,255,255,255));
        imagealphablending($image,true);
        imagecopyresampled($image,$source,0,0,0,0,self::WIDTH,self::HEIGHT,$size[0],$size[1]);imagedestroy($source);
        try{
            foreach(self::MARKERS as [$x,$y])if($this->dark($image,$x,$y,6)<.9)throw ValidationException::withMessages(['image'=>'A corner square is missing or misaligned. Align the sheet again.']);
            if($this->dark($image,500,35,6)>.1)throw ValidationException::withMessages(['image'=>'The page is too dark or the alignment is incorrect. Retake the photo.']);
            $bits='';for($i=0;$i<96;$i++){
                $value=$this->dark($image,self::CODE_X+($i%12)*self::CELL+5,self::CODE_Y+intdiv($i,12)*self::CELL+5,2);
                if($value>.1&&$value<.9)throw ValidationException::withMessages(['image'=>'The sheet identification code is unclear. Retake or realign the photo.']);
                $bits.=$value>=.5?'1':'0';
            }
            $token='';foreach(str_split($bits,4) as $n)$token.=dechex(bindec($n));
            $responses=[];$flags=[];$confidence=[];
            if($configuration)foreach($this->questions($configuration['question_count'],$configuration['choices'],$configuration['layout_version']??1) as $row){
                if(($configuration['layout_version']??1)>=2)$row=$this->registerBubbleRow($image,$row);
                $values=[];foreach($row['dots'] as $dot)$values[$dot['label']]=($configuration['layout_version']??1)>=2?$this->bubbleRing($image,$dot):$this->dark($image,$dot['x'],$dot['y'],max(2,min(5,(int)floor($dot['radius']-2))));
                $marks=array_keys(array_filter($values,fn($value)=>$value>=.38));
                $unclear=count(array_filter($values,fn($value)=>$value>.10&&$value<.38))>0;
                $responses[]=count($marks)===1&&!$unclear?(string)$marks[0]:null;
                $flag=count($marks)>1?'multiple':($unclear?'unclear':(count($marks)===0?'blank':null));
                if($flag)$flags[(string)$row['number']]=$flag;
                $confidence[]=$values;
            }
            $absence=null;
            if($configuration&&($configuration['layout_version']??1)>=5&&($configuration['student_sheet']??false)){
                $absence=(new ScholarshipAbsenceMark())->read($image,$configuration['layout_version']);
                if($absence['state']==='absent'&&(collect($responses)->contains(fn($answer)=>$answer!==null)||collect($flags)->contains(fn($flag)=>$flag!=='blank')))$absence['state']='conflict';
            }
            return compact('token','responses','flags','confidence','absence');
        } finally {imagedestroy($image);}
    }
}
