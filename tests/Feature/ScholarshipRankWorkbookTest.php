<?php

use App\Support\ScholarshipExport;

function scholarshipWorkbookParts(string $bytes): array
{
    $path=tempnam(sys_get_temp_dir(),'rank-workbook-test-');file_put_contents($path,$bytes);
    try{
        $zip=new ZipArchive();expect($zip->open($path))->toBeTrue();$parts=[];
        foreach(range(0,$zip->numFiles-1) as $i){
            $name=$zip->getNameIndex($i);$parts[$name]=$zip->getFromIndex($i);
            if(str_ends_with($name,'.xml')||str_ends_with($name,'.rels')){
                $document=new DOMDocument();expect($document->loadXML($parts[$name],LIBXML_NONET))->toBeTrue();
            }
        }
        $zip->close();return $parts;
    }finally{unlink($path);}
}

function scholarshipWorkbookData(bool $central=true,int $total=75): array
{
    $export=new ScholarshipExport();
    $rows=$total?array_map(fn($i)=>['number'=>$i,'id'=>$i===60?'001234':'ID-'.$i,'name_en'=>'STUDENT '.$i,'name_kh'=>'ចាន់ សុភា','gender'=>'F','grade'=>'1A','group'=>'III','rank'=>1,'campus'=>$central&&$i>=60?'BKK':'BCH','campus_id'=>$central&&$i>=60?2:1],range(1,$total)):[];
    $pages=$export->rankPages($rows);
    return ['year'=>'2025-2026','campusLabel'=>$central?'All Campuses':'BCH Campus','logoSrc'=>null,'reportSections'=>[['pages'=>$pages,'total'=>$total,'requiresVerifier'=>$central,
        'preparedBy'=>'PREPARER','preparedPosition'=>'Registrar','preparedDate'=>'October 2, 2026',
        'checkedBy'=>'VERIFIER','checkedPosition'=>'Academic Director','checkedDate'=>'October 6, 2026',
        'approvedBy'=>'APPROVER','approvedPosition'=>'Vice President','approvedDate'=>'October 9, 2026']]];
}

it('exports the A4 report layout with campus numbering page breaks print area and Central signatures',function(){
    $parts=scholarshipWorkbookParts((new ScholarshipExport())->ranksXlsx(scholarshipWorkbookData()));
    $sheet=$parts['xl/worksheets/sheet1.xml'];$styles=$parts['xl/styles.xml'];
    expect($sheet)->toContain('List of Outstanding Students','All Campuses','Campus: BCH','Campus: BKK','គោត្តនាម-នាម','KINGDOM OF CAMBODIA','NATION RELIGION KING','Checked and Verified by','Registrar','Academic Director','Vice President');
    expect($sheet)->toContain('mergeCell ref="A9:B9"','mergeCell ref="A10:M10"','paperSize="9" orientation="portrait" scale="95"','<brk id="35"','<brk id="67"','&amp;8Page &amp;P of &amp;N');
    expect($sheet)->toContain('<c r="A75" s="7"><v>60</v>','<c r="B75" s="7"><v>1</v>','<c r="G75" s="10" t="inlineStr"><is><t xml:space="preserve">001234</t>');
    expect($sheet)->toContain('mergeCell ref="A92:C92"','mergeCell ref="D92:G92"','mergeCell ref="H92:M92"');
    expect($styles)->toContain('FFFFFF99','FFE8EEF8','Khmer OS Muol Light','Khmer OS Siemreap','numFmtId="164"');
    expect($parts['xl/workbook.xml'])->toContain('_xlnm.Print_Area',"'Outstanding Students'!\$A\$1:\$M\$96");
    expect($parts)->not->toHaveKey('xl/drawings/drawing1.xml');
});

it('uses only campus signatures and keeps an empty list printable',function(){
    $parts=scholarshipWorkbookParts((new ScholarshipExport())->ranksXlsx(scholarshipWorkbookData(false,0)));
    expect($parts['xl/worksheets/sheet1.xml'])->toContain('BCH Campus','No student ranks assigned for these filters.','Prepared by','Approved by','PREPARER','APPROVER','mergeCell ref="A12:E12"','mergeCell ref="F12:M12"')->not->toContain('Checked and Verified by','VERIFIER','Academic Director','<rowBreaks');
});

it('embeds School Logo 1 and exports user text as literal values instead of formulas',function(){
    $data=scholarshipWorkbookData(false,1);
    $data['reportSections'][0]['pages'][0][0]['id']='=1+1';
    $data['reportSections'][0]['pages'][0][0]['name_en']='=HYPERLINK("https://example.invalid")';
    $data['logoSrc']='data:image/png;base64,'.base64_encode(file_get_contents(storage_path('app/public/school_logo/student_profile_report_logo.png')));
    $parts=scholarshipWorkbookParts((new ScholarshipExport())->ranksXlsx($data));
    expect($parts['xl/worksheets/sheet1.xml'])->toContain('=1+1','=HYPERLINK(&quot;https://example.invalid&quot;)','<drawing r:id="rId1"/>')->not->toContain('<f>','<f ');
    expect($parts['xl/media/logo.png'])->toStartWith("\x89PNG");
    expect($parts['xl/drawings/drawing1.xml'])->toContain('School Logo 1','noChangeAspect="1"');
    expect($parts['xl/drawings/_rels/drawing1.xml.rels'])->toContain('../media/logo.png');
});

it('preserves SVG branding logos without requiring a server image conversion service',function(){
    $data=scholarshipWorkbookData(false,0);
    $svg='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 650 180"><rect width="650" height="180" fill="#293959"/></svg>';
    $data['logoSrc']='data:image/svg+xml;base64,'.base64_encode($svg);
    $parts=scholarshipWorkbookParts((new ScholarshipExport())->ranksXlsx($data));
    expect($parts['xl/media/logo.svg'])->toBe($svg);
    expect($parts['[Content_Types].xml'])->toContain('Extension="svg" ContentType="image/svg+xml"');
    expect($parts['xl/drawings/_rels/drawing1.xml.rels'])->toContain('../media/logo.svg');
});
