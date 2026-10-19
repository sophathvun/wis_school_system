<?php

use App\Support\{ScholarshipSeatReports, ScholarshipSeatWorkbook};

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';
beforeEach(fn()=>createScholarshipSchema());

it('separates room reports by exam center and paginates the full confirmed list',function($kind,$count){
    $data=scholarshipSeatReportData(scholarshipSeatReportExam(),$kind);
    expect($data['pages'])->toHaveCount($count);
    foreach($data['pages'] as $page){
        expect($page['room'])->toBe(1)->and($page['total'])->toBe($page['center']==='North Exam Center'?26:2);
        foreach($page['rows'] as $row)expect(array_sum(array_column($row['cells'],'span')))->toBe(60);
    }
    $north=collect($data['pages'])->where('center','North Exam Center');
    $south=collect($data['pages'])->where('center','South Exam Center');
    expect($north)->not->toBeEmpty()->and($south)->not->toBeEmpty();
    $html=view('western-scholarship.seat-report',$data)->render();
    expect($html)->toContain('North Exam Center','South Exam Center','November 20, 2026')->not->toContain('Secondary Only');
    if($kind==='candidates')expect($html)->toContain('Total Students: 26   |   Female: 13','Total Students: 2   |   Female: 1');
    if($kind==='attendance')expect($html)->toContain('>SEAT NO.</div>','>GENDER</div>','>MATHEMATICS</div>','Total Student: 26   |   Female: 13','Total Student: 2   |   Female: 1','Exam Date: November 20, 2026')->not->toContain('Exam Date: Phnom Penh,');
    if($kind==='envelope-cover'){
        expect($html)->toContain('border-radius:15px','font-size:24pt;font-weight:bold','Academic Year: 2025-2026','Exam Date: November 20, 2026')->not->toContain('Exam Date: Phnom Penh,','2025-2026   ·   Exam Date:');
        expect($data['pages'][0]['rows'][0]['cells'][0]['logo_width'])->toBe(85);
    }
})->with([['candidates',3],['attendance',6],['attendance-cover',2],['envelope-cover',10],['seat-labels',3],['room-plan',3]]);

it('prints envelope exam times below the subject using grade overrides and leaves unscheduled subjects explicit',function(){
    $exam=scholarshipSeatReportExam(1);
    $subject=$exam->subjects()->where('name','Mathematics')->first();
    $subject->update(['exam_date'=>'2026-11-20','starts_at'=>'08:00','ends_at'=>'08:50']);
    $exam->update(['subject_schedule_overrides'=>[2=>[$subject->id=>['exam_date'=>'2026-11-20','starts_at'=>'13:00','ends_at'=>'14:50']]]]);
    $data=scholarshipSeatReportData($exam->fresh(),'envelope-cover');
    foreach($data['pages'] as $page){
        $subjectRow=collect($page['rows'])->search(fn($row)=>str_starts_with((string)($row['cells'][0]['lines'][0]['text']??''),'Subject: '));
        $cell=$page['rows'][$subjectRow+1]['cells'][0];
        $expected=$page['subject']==='Mathematics'?($page['grade']===1?"8:00 AM\u{2013}8:50 AM":"1:00 PM\u{2013}2:50 PM"):'Not scheduled';
        expect($cell['role'])->toBe('exam-time')->and($cell['lines'][0]['text'])->toBe('Exam Time: '.$expected);
    }
    $html=view('western-scholarship.seat-report',$data)->render();
    expect($html)->toContain("Exam Time: 8:00 AM\u{2013}8:50 AM","Exam Time: 1:00 PM\u{2013}2:50 PM",'Exam Time: Not scheduled');
});

it('keeps sticker and classroom seat numbers and names complete and in the reference order',function(){
    $exam=scholarshipSeatReportExam();
    foreach(['seat-labels','room-plan'] as $kind){
        $data=scholarshipSeatReportData($exam,$kind);
        $names=[];
        foreach($data['pages'] as $page)foreach($page['rows'] as $row)foreach($row['cells'] as $cell)if(in_array($cell['style'],['label','desk'])){
            foreach($cell['lines'] as $line)if(str_contains((string)$line['text'],'STUDENT'))$names[]=$line['text'];
        }
        expect($names)->toHaveCount(28)->and(array_unique($names))->toHaveCount(28)->and($names)->toContain('NORTH STUDENT 26','SOUTH STUDENT 02');
        if($kind==='room-plan')expect(array_slice($names,0,5))->toBe(['NORTH STUDENT 01','NORTH STUDENT 06','NORTH STUDENT 11','NORTH STUDENT 16','NORTH STUDENT 21']);
    }
});

it('uses smaller roster pages for long names without truncating the names',function(){
    $exam=scholarshipSeatReportExam();
    $longName='SAMPLE STUDENT WITH AN ESPECIALLY LONG ENGLISH NAME';
    foreach($exam->candidates()->get() as $candidate)$candidate->update(['snapshot'=>array_replace($candidate->snapshot,['name_en'=>$longName])]);
    $data=scholarshipSeatReportData($exam,'candidates');
    $names=collect($data['pages'][0]['rows'])->flatMap(fn($row)=>$row['cells'])->where('style','table-name');
    expect($names)->toHaveCount(18)->and($names->first()['lines'][0]['text'])->toBe($longName);
});

it('keeps eighteen branded stickers on each full A4 PDF page',function(){
    $data=scholarshipSeatReportData(scholarshipSeatReportExam(),'seat-labels');
    $data['logoSrc']='data:image/png;base64,'.base64_encode(file_get_contents(storage_path('app/public/school_logo/student_profile_report_logo.png')));
    $pdf=app(ScholarshipSeatReports::class)->pdf($data);
    expect(preg_match_all('~/Type\s*/Page(?!s)\b~',$pdf))->toBe(count($data['pages']));
});

it('scales longer sticker titles down and puts the exam title beside the logo',function(){
    $exam=scholarshipSeatReportExam();$short=scholarshipSeatReportData($exam,'seat-labels');
    $firstLabel=fn($data)=>collect($data['pages'][0]['rows'])->flatMap(fn($row)=>$row['cells'])->firstWhere('style','label');
    $shortLabel=$firstLabel($short);
    expect($shortLabel['lines'][0]['size'])->toBeGreaterThan(9)->and($shortLabel['sticker_title'])->toBeTrue();
    $exam->update(['examination_type'=>'Annual Scholarship Entrance Examination for Outstanding Students']);
    $long=scholarshipSeatReportData($exam->fresh(),'seat-labels');$longLabel=$firstLabel($long);
    expect($longLabel['lines'][0]['size'])->toBeLessThan($shortLabel['lines'][0]['size'])
        ->and($longLabel['lines'][0]['text'])->toBe($exam->examination_type);
    $html=view('western-scholarship.seat-report',$long)->render();
    expect($html)->toContain('class="sticker-logo"','class="sticker-title"',$exam->examination_type);
    expect(preg_match_all('~/Type\s*/Page(?!s)\b~',app(ScholarshipSeatReports::class)->pdf($long)))->toBe(count($long['pages']));
});

it('keeps twenty five candidates signatures and the revised heading together on one A4 page per room',function(){
    $data=scholarshipSeatReportData(scholarshipSeatReportExam(25),'candidates');
    $data['logoSrc']='data:image/png;base64,'.base64_encode(file_get_contents(storage_path('app/public/school_logo/student_profile_report_logo.png')));
    $html=view('western-scholarship.seat-report',$data)->render();
    expect($html)->toContain('>SEAT NO.</div>','>NAME</div>','>GENDER</div>','>GRADE</div>','>CAMPUS</div>','>ROOM</div>','>REMARKS</div>','Academic Year: 2025-2026','Exam Date: Phnom Penh, November 20, 2026','background:#dbeafe','print-color-adjust:exact')->not->toContain('>Sex</div>');
    $pdf=app(ScholarshipSeatReports::class)->pdf($data);
    expect(preg_match_all('~/Type\s*/Page(?!s)\b~',$pdf))->toBe(2)->and(count($data['pages']))->toBe(2);
});

it('produces editable styled A4 workbooks for every report and preserves literal user text',function($kind){
    $data=scholarshipSeatReportData(scholarshipSeatReportExam(),$kind);
    $data['pages'][0]['rows'][]=['height'=>6,'cells'=>[['span'=>60,'style'=>'body','lines'=>[['text'=>'=HYPERLINK("https://example.invalid")']]]]];
    $data['logoSrc']='data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 650 180"><rect width="650" height="180" fill="#293959"/></svg>');
    $path=tempnam(sys_get_temp_dir(),'seat-report-test-');file_put_contents($path,app(ScholarshipSeatWorkbook::class)->generate($data));
    try{
        $zip=new ZipArchive();expect($zip->open($path))->toBeTrue();
        for($i=0;$i<$zip->numFiles;$i++)if(preg_match('/\.(xml|rels)$/',$zip->getNameIndex($i))){$xml=new DOMDocument();expect($xml->loadXML($zip->getFromIndex($i),LIBXML_NONET))->toBeTrue();}
        $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');
        if($kind==='attendance')$sheet.=$zip->getFromName('xl/worksheets/sheet2.xml');
        expect($sheet)->toContain('North Exam Center','South Exam Center','paperSize="9"','orientation="portrait"','<rowBreaks','mergeCell','HYPERLINK')->not->toContain('<f>','<f ');
        expect($zip->getFromName('xl/styles.xml'))->toContain('FFFFF2CC','Arial');
        expect($zip->getFromName('xl/drawings/drawing1.xml'))->toContain($kind==='seat-labels'?'School Logo 2':'School Logo 1','noChangeAspect="1"');
        $zip->close();
    }finally{unlink($path);}
})->with(array_keys(ScholarshipSeatReports::DOCUMENTS));

function scholarshipRoomWorkbookParts(array $data): array
{
    $path=tempnam(sys_get_temp_dir(),'room-workbook-');
    try{
        file_put_contents($path,app(ScholarshipSeatWorkbook::class)->generate($data));
        $zip=new ZipArchive();expect($zip->open($path))->toBeTrue();$parts=[];
        for($i=0;$i<$zip->numFiles;$i++)$parts[$zip->getNameIndex($i)]=$zip->getFromIndex($i);
        $zip->close();return $parts;
    }finally{unlink($path);}
}

it('uses Branding School Logo 2 only for stickers and fits portrait artwork on A4',function(){
    \Illuminate\Support\Facades\Schema::create('tb_branding_setting',function($table){$table->id();$table->string('report_logo_1_path');$table->string('report_logo_2_path');$table->timestamps();});
    $paths=[tempnam(storage_path('app/public'),'scholarship-logo-'),tempnam(storage_path('app/public'),'scholarship-logo-')];
    try{
        foreach($paths as $index=>$path){
            $image=imagecreatetruecolor($index===0?60:20,30);
            imagefill($image,0,0,imagecolorallocate($image,$index===0?255:0,0,$index===0?0:255));
            imagepng($image,$path);imagedestroy($image);
        }
        \Illuminate\Support\Facades\DB::table('tb_branding_setting')->insert(['report_logo_1_path'=>basename($paths[0]),'report_logo_2_path'=>basename($paths[1])]);
        $exam=scholarshipSeatReportExam();$data=scholarshipSeatReportData($exam,'seat-labels');
        expect($data['logoSrc'])->toBe('data:image/png;base64,'.base64_encode(file_get_contents($paths[1])))
            ->and($data['logoLabel'])->toBe('School Logo 2')->and($data['logoUrl'])->toContain(basename($paths[1]));
        $labels=collect($data['pages'][0]['rows'])->flatMap(fn($row)=>$row['cells'])->where('style','label');
        expect($labels)->toHaveCount(18)->and($labels->first()['logo_width'])->toBeLessThan(10);
        $parts=scholarshipRoomWorkbookParts($data);
        expect($parts['xl/drawings/drawing1.xml'])->toContain('School Logo 2');
        $pdf=app(ScholarshipSeatReports::class)->pdf($data);
        expect(preg_match_all('~/Type\s*/Page(?!s)\b~',$pdf))->toBe(count($data['pages']));
        expect(scholarshipSeatReportData($exam,'candidates')['logoSrc'])->toBe('data:image/png;base64,'.base64_encode(file_get_contents($paths[0])));
        \Illuminate\Support\Facades\DB::table('tb_branding_setting')->update(['report_logo_2_path'=>'missing-logo.png']);
        expect(scholarshipSeatReportData($exam,'seat-labels')['logoSrc'])->toBeNull();
    }finally{foreach($paths as $path)if(is_file($path))unlink($path);}
});

it('exports each attendance room to its own named worksheet with independent print areas and branding',function(){
    $exam=scholarshipSeatReportExam(3);$exam->subjects()->where('name','General Knowledge')->delete();
    $north=$exam->candidates()->where('campus_id',1)->orderBy('room_seat')->get();
    $north[1]->update(['room_number'=>2]);$north[2]->update(['room_number'=>12]);
    $data=app(ScholarshipSeatReports::class)->data($exam->fresh(),$exam->candidates()->where('campus_id',1)->orderBy('room_number')->get(),'attendance');
    $data['logoSrc']='data:image/png;base64,'.base64_encode(file_get_contents(storage_path('app/public/school_logo/student_profile_report_logo.png')));
    $parts=scholarshipRoomWorkbookParts($data);
    $workbook=new DOMDocument();$workbook->loadXML($parts['xl/workbook.xml']);
    expect(array_map(fn($sheet)=>$sheet->getAttribute('name'),iterator_to_array($workbook->getElementsByTagName('sheet'))))->toBe(['ROOM 1','ROOM 2','ROOM 12']);
    foreach([1=>1,2=>2,3=>12] as $number=>$room){
        $sheet=$parts['xl/worksheets/sheet'.$number.'.xml'];
        expect($sheet)->toContain('NORTH STUDENT '.sprintf('%02d',$number),'paperSize="9"','mergeCell','<drawing r:id="rId1"/>')->not->toContain('<rowBreaks');
        foreach(array_diff([1,2,3],[$number]) as $other)expect($sheet)->not->toContain('NORTH STUDENT '.sprintf('%02d',$other));
        expect($parts['xl/workbook.xml'])->toContain('localSheetId="'.($number-1).'">&apos;ROOM '.$room.'&apos;!$A$1:$BH$');
        expect($parts['xl/worksheets/_rels/sheet'.$number.'.xml.rels'])->toContain('../drawings/drawing'.$number.'.xml');
        expect($parts['xl/drawings/drawing'.$number.'.xml'])->toContain('<xdr:row>0</xdr:row>','School Logo 1');
        expect($parts['xl/drawings/_rels/drawing'.$number.'.xml.rels'])->toContain('../media/logo.png');
        expect($parts['[Content_Types].xml'])->toContain('/xl/worksheets/sheet'.$number.'.xml','/xl/drawings/drawing'.$number.'.xml');
    }
    foreach($parts as $name=>$xml)if(preg_match('/\.(xml|rels)$/',$name))expect((new DOMDocument())->loadXML($xml,LIBXML_NONET))->toBeTrue();
});

it('keeps attendance continuation pages together and distinguishes identical room numbers across centers',function(){
    $data=scholarshipSeatReportData(scholarshipSeatReportExam(),'attendance');$parts=scholarshipRoomWorkbookParts($data);
    expect($parts['xl/workbook.xml'])->toContain('name="ROOM 1 - North Exam Center"','name="ROOM 1 - South Exam Center"')->not->toContain('sheetId="3"');
    $north=$parts['xl/worksheets/sheet1.xml'];$south=$parts['xl/worksheets/sheet2.xml'];
    expect($north)->toContain('NORTH STUDENT 26','GENERAL KNOWLEDGE','<rowBreaks count="3"')->not->toContain('SOUTH STUDENT');
    expect($south)->toContain('SOUTH STUDENT 02','GENERAL KNOWLEDGE','<rowBreaks count="1"')->not->toContain('NORTH STUDENT');
});

it('creates safe unique room sheet names for long or special exam center names',function(){
    $data=scholarshipSeatReportData(scholarshipSeatReportExam(1),'attendance');
    foreach($data['pages'] as &$page)$page['center']="Center / \\ [North]:?* With A Very Long Name '";
    unset($page);$parts=scholarshipRoomWorkbookParts($data);
    $workbook=new DOMDocument();$workbook->loadXML($parts['xl/workbook.xml']);$names=[];
    foreach($workbook->getElementsByTagName('sheet') as $sheet){
        $name=$sheet->getAttribute('name');$names[]=$name;
        expect(mb_strlen($name))->toBeLessThanOrEqual(31)->and(preg_match('~[\\\\/?*\[\]:]~u',$name))->toBe(0);
    }
    expect($names)->toHaveCount(2)->and(array_unique($names))->toHaveCount(2);
});

it('exports an empty attendance workbook without drawings or invalid print areas',function(){
    $data=scholarshipSeatReportData(scholarshipSeatReportExam(),'attendance');$data['pages']=[];$data['logoSrc']=null;
    $parts=scholarshipRoomWorkbookParts($data);
    expect($parts['xl/workbook.xml'])->toContain('name="Scholarship Report"','!$A$1:$BH$1');
    expect($parts['xl/worksheets/sheet1.xml'])->toContain('No confirmed candidates available.')->not->toContain('<drawing');
});
