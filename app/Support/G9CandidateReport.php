<?php

namespace App\Support;

use Mpdf\Config\{ConfigVariables,FontVariables};
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use ZipArchive;

class G9CandidateReport
{
    public const TITLES=['candidates'=>'បញ្ជីបេក្ខជនប្រឡង','cover'=>'គម្របបញ្ជីវត្តមាន','attendance-1'=>'បញ្ជីវត្តមាន ទំព័រ១','attendance-2'=>'បញ្ជីវត្តមាន ទំព័រ២'];
    public const SUBJECTS=[
        'attendance-1'=>['តែងសេចក្តី','គីមី','ផែនដី','រូប','ភូមិ','សីល-ពល'],
        'attendance-2'=>['គណិត','សរសេរតាមអាន','ជីវ','ប្រវត្តិ','ភាសា','ផ្សេងៗ'],
    ];
    public static function digits(string|int $value): string {return strtr((string)$value,array_combine(range(0,9),mb_str_split('០១២៣៤៥៦៧៨៩')));}
    public static function locationHeading(string $location): string
    {
        $location=trim(str_replace(["\u{200B}","\u{FEFF}"],'',$location));
        if(preg_match('/^(រាជធានី|ខេត្ត)\s*[:៖]?\s*(.+)$/u',$location,$match))return $match[1].'៖ '.trim($match[2]);
        return $location==='ភ្នំពេញ'?'រាជធានី៖ ភ្នំពេញ':$location;
    }
    public static function roomHeading(string $room): string
    {
        if(preg_match('/^[0-9]+$/',$room))$room=ltrim($room,'0')?:'0';
        return self::digits($room);
    }
    public static function khmerDate(?string $date): string
    {
        if(!$date||!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$date,$m))return $date?:'……………………';
        $months=['មករា','កុម្ភៈ','មីនា','មេសា','ឧសភា','មិថុនា','កក្កដា','សីហា','កញ្ញា','តុលា','វិច្ឆិកា','ធ្នូ'];
        return self::digits($m[3]).' '.$months[(int)$m[2]-1].' '.self::digits($m[1]);
    }
    public function pages($records,array $settings,string $type): array
    {
        $pages=[];$centers=collect($settings['report_centers']??[])->keyBy('exam_center');
        foreach($records->groupBy(fn($r)=>json_encode([$r->data['exam_center']??'',$r->data['room_number']??'',$r->data['exam_date']??''])) as $group){
            $group=$group->sort(function($a,$b){return strnatcasecmp((string)($a->data['seat_number']??''),(string)($b->data['seat_number']??''))?:strcmp($a->name_kh??$a->name_en,$b->name_kh??$b->name_en)?:($a->id<=>$b->id);})->values();
            $first=$group->first();$center=trim((string)($first->data['exam_center']??''));$config=$centers->get($center,[]);
            $location=$config['location']??$settings['date_location']??'';
            $solar=$settings['listDate']??'';
            if(!empty($settings['listDate_iso'])){
                $parts=explode(' ',self::khmerDate($settings['listDate_iso']));
                $solar=$location.' ថ្ងៃទី'.$parts[0].' ខែ'.$parts[1].' ឆ្នាំ'.$parts[2];
            }
            elseif($location&&($settings['date_location']??'')&&str_starts_with($solar,$settings['date_location']))$solar=$location.mb_substr($solar,mb_strlen($settings['date_location']));
            $base=['center'=>$center?:'មិនបានបញ្ជាក់','room'=>$first->data['room_number']??'','examDate'=>self::khmerDate($first->data['exam_date']??null),
                'location'=>$location,'department'=>$config['department']??'','signer'=>$config['signer']??'ប្រធានមណ្ឌល',
                'solar'=>$solar,'lunar'=>$settings['k_listdate']??'','type'=>$type];
            foreach(($type==='cover'?collect([$group]):$group->chunk(25)) as $chunk){
                $rows=$chunk->map(function($r){$d=$r->data;return ['seat'=>(string)($d['seat_number']??''),'name'=>$r->name_kh?:$r->name_en,
                    'gender'=>['male'=>'ប្រុស','female'=>'ស្រី','m'=>'ប្រុស','f'=>'ស្រី'][mb_strtolower($d['gender']??'')]??($d['gender']??''),
                    'dob'=>!empty($d['date_of_birth'])?\Carbon\CarbonImmutable::parse($d['date_of_birth'])->format('d-m-Y'):'',
                    'school'=>$d['school_name']??'','national_id'=>$r->legacy_data['national_id']??'',
                    'language'=>['english'=>'អង់គ្លេស','french'=>'បារាំង'][mb_strtolower($d['foreign_language']??'')]??($d['foreign_language']??'')];})->values()->all();
                $pages[]=$base+['rows'=>$rows,'total'=>count($rows),'female'=>collect($rows)->where('gender','ស្រី')->count(),
                    'english'=>collect($rows)->where('language','អង់គ្លេស')->count(),'french'=>collect($rows)->where('language','បារាំង')->count()];
            }
        }
        return $pages;
    }
    public function pdf(array $data): string
    {
        $temp=storage_path('app/private/g9-report-pdf');if(!is_dir($temp))mkdir($temp,0755,true);
        $pdf=new Mpdf(['mode'=>'utf-8','format'=>'A4','tempDir'=>$temp,'margin_left'=>8,'margin_right'=>8,'margin_top'=>8,'margin_bottom'=>8,
            'fontDir'=>[...(new ConfigVariables)->getDefaults()['fontDir'],public_path('fonts/khmer')],
            'fontdata'=>(new FontVariables)->getDefaults()['fontdata']+['g9body'=>['R'=>'KhmerOSsiemreap.ttf','useOTL'=>0xFF],'g9heading'=>['R'=>'KhmerOSmuollight.ttf','useOTL'=>0xFF]],
            'default_font'=>'g9body','default_font_size'=>10,'shrink_tables_to_fit'=>1]);
        $pdf->SetTitle(self::TITLES[$data['type']]);
        $attendance=isset(self::SUBJECTS[$data['type']]);$baseSize=$attendance?8:10;
        $pdf->SetFont('g9body','',$baseSize);
        $available=194*($attendance?.17:.28)-($attendance?1.2:2);
        foreach($data['pages'] as &$page)foreach($page['rows'] as &$row){
            $width=$pdf->GetStringWidth((string)$row['school']);
            $row['school_font_size']=round(max(5.5,min($baseSize,$baseSize*$available/max(.1,$width))),2);
        }unset($page,$row);
        // mPDF reads A4 from its constructor; its CSS parser does not support "A4 portrait".
        $css=preg_replace('/@page\{[^}]*\}/','',file_get_contents(resource_path('css/pages/g9-candidate-print.css')));
        $pdf->WriteHTML($css,\Mpdf\HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML(view('g9-national-exams.candidate-report',$data+['pdfExport'=>true])->render());
        return $pdf->Output('',Destination::STRING_RETURN);
    }
    /** One worksheet per printed page keeps room groupings and A4 print areas identical. */
    public function xlsx(array $data): string
    {
        $ns='http://schemas.openxmlformats.org/';$entries=[];$sheets='';$relationships='';$overrides='';$areas='';
        foreach($data['pages'] as $i=>$page){
            $number=$i+1;$rows=[];$merges=[];$cover=$data['type']==='cover';$subjects=self::SUBJECTS[$data['type']]??[];$count=$cover?7:($subjects?6+count($subjects):7);$last=$this->column($count);
            $add=function(array $values,int $style=0,int $height=24)use(&$rows){$rows[]=['values'=>$values,'style'=>$style,'height'=>$height];};
            $merged=function(string $text,int $style=0,int $height=25)use(&$rows,&$merges,$add,$last){$add([$text],$style,$height);$n=count($rows);$merges[]='A'.$n.':'.$last.$n;};
            $merged('ព្រះរាជាណាចក្រកម្ពុជា',1,26);$merged('ជាតិ សាសនា ព្រះមហាក្សត្រ',1,26);$merged('──── ◇ ────',0,12);
            $merged($page['location'],5,20);if($cover)$merged($page['department'],5,25);$merged('មណ្ឌលប្រឡង៖ '.$page['center'],5,25);
            if($cover){
                $merged('',0,65);$merged('បញ្ជីស្រង់វត្តមានសិស្ស',2,90);$merged('ប្រឡងសញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិ',1,65);
                $merged('បន្ទប់លេខ៖ '.$page['room'],6,70);$merged('សម័យប្រឡង៖ '.$page['examDate'],7,55);
                $merged('ចំនួនបេក្ខជន៖ '.$page['total'].' នាក់',1,45);$merged('ស្រី៖ '.$page['female'].' នាក់',0,35);
            }else{
                $merged('បន្ទប់លេខ៖ '.$page['room'],5,20);$merged('បញ្ជីរាយនាមបេក្ខជនប្រឡងសញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិ',1,26);
                $merged(self::TITLES[$data['type']],0,18);$merged('សម័យប្រឡង៖ '.$page['examDate'],1,26);
                $headers=['លេខតុ','នាមត្រកូល និងនាមខ្លួន','ភេទ','ថ្ងៃខែឆ្នាំកំណើត','អាគតដ្ឋាន','ភាសាបរទេស'];
                if($subjects){
                    $add(array_merge($headers,['ហត្ថលេខាបេក្ខជនតាមមុខវិជ្ជា'],array_fill(0,count($subjects)-1,'')),3,21);$start=count($rows);
                    $merges[]='G'.$start.':'.$last.$start;
                    foreach(range(1,6) as $ci)$merges[]=$this->column($ci).$start.':'.$this->column($ci).($start+1);
                    $add(array_merge(array_fill(0,6,''),$subjects),3,27);
                }else $add(array_merge($headers,['សេចក្តីផ្សេងៗ']),3,32);
                foreach(array_pad($page['rows'],25,[]) as $row)$add(array_merge([$row['seat']??'',$row['name']??'',$row['gender']??'',$row['dob']??'',$row['school']??'',$row['language']??''],array_fill(0,$subjects?count($subjects):1,'')),4,19);
                $merged('បេក្ខជនសរុប៖ '.$page['total'].' នាក់   ស្រី៖ '.$page['female'].' នាក់',5,22);
                $merged('អង់គ្លេស៖ '.$page['english'].' នាក់   បារាំង៖ '.$page['french'].' នាក់',5,22);
                $merged($page['lunar'],0,22);$merged($page['solar'],0,22);$merged($page['signer'],1,26);
            }
            $xmlRows='';foreach($rows as $ri=>$row){
                $rn=$ri+1;$xmlRows.='<row r="'.$rn.'" ht="'.$row['height'].'" customHeight="1">';
                foreach($cover?array_pad($row['values'],7,''):$row['values'] as $ci=>$value){
                    $style=$row['style'];
                    if($cover){
                        $edge=($ci===0?1:0)|($ci===6?2:0)|($ri===0?4:0)|($ri===count($rows)-1?8:0);
                        if($edge)$style+=8*(array_search($edge,[1,2,4,8,5,6,9,10],true)+1);
                    }
                    $xmlRows.='<c r="'.$this->column($ci+1).$rn.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->xml((string)$value).'</t></is></c>';
                }$xmlRows.='</row>';
            }
            $cols='';$widths=$cover?array_fill(0,7,12):($subjects?array_merge([6,25,6,14,26,10],array_fill(0,count($subjects),7)):[7,27,7,16,43,15,15]);
            foreach($widths as $ci=>$width)$cols.='<col min="'.($ci+1).'" max="'.($ci+1).'" width="'.$width.'" customWidth="1"/>';
            $mergeXml='';foreach($merges as $merge)$mergeXml.='<mergeCell ref="'.$merge.'"/>';
            $entries['xl/worksheets/sheet'.$number.'.xml']='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="'.$ns.'spreadsheetml/2006/main"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><sheetViews><sheetView workbookViewId="0" showGridLines="0"/></sheetViews><cols>'.$cols.'</cols><sheetData>'.$xmlRows.'</sheetData><mergeCells count="'.count($merges).'">'.$mergeXml.'</mergeCells><pageMargins left="0.31" right="0.31" top="0.31" bottom="0.31" header="0" footer="0"/><pageSetup paperSize="9" orientation="portrait" fitToWidth="1" fitToHeight="1"/></worksheet>';
            $sheets.='<sheet name="Page '.$number.'" sheetId="'.$number.'" r:id="rId'.$number.'"/>';
            $relationships.='<Relationship Id="rId'.$number.'" Type="'.$ns.'officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$number.'.xml"/>';
            $overrides.='<Override PartName="/xl/worksheets/sheet'.$number.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $areas.='<definedName name="_xlnm.Print_Area" localSheetId="'.$i.'">\'Page '.$number.'\'!$A$1:$'.$last.'$'.count($rows).'</definedName>';
        }
        $entries['xl/workbook.xml']='<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="'.$ns.'spreadsheetml/2006/main" xmlns:r="'.$ns.'officeDocument/2006/relationships"><sheets>'.$sheets.'</sheets><definedNames>'.$areas.'</definedNames></workbook>';
        $entries['_rels/.rels']='<?xml version="1.0"?><Relationships xmlns="'.$ns.'package/2006/relationships"><Relationship Id="rId1" Type="'.$ns.'officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
        $entries['xl/_rels/workbook.xml.rels']='<?xml version="1.0"?><Relationships xmlns="'.$ns.'package/2006/relationships">'.$relationships.'<Relationship Id="rIdStyles" Type="'.$ns.'officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $fonts='<font><sz val="10"/><name val="Khmer OS Siemreap"/></font><font><sz val="12"/><name val="Khmer OS Muol Light"/></font><font><sz val="24"/><name val="Khmer OS Muol Light"/><color rgb="FF20385D"/></font><font><sz val="24"/><name val="Khmer OS Muol Light"/><color rgb="FFDC2626"/></font><font><sz val="14"/><name val="Khmer OS Siemreap"/><color rgb="FFDC2626"/></font>';
        $baseStyles=[[0,0,'center'],[1,0,'center'],[2,0,'center'],[0,1,'center'],[0,1,'center'],[0,0,'left'],[3,0,'center'],[4,0,'center']];
        $styles='';$borders='<border/><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border>';
        foreach([0,1,2,4,8,5,6,9,10] as $edgeIndex=>$edge){
            if($edge){$borders.='<border>';foreach(['left'=>1,'right'=>2,'top'=>4,'bottom'=>8] as $side=>$flag)$borders.='<'.$side.(($edge&$flag)?' style="medium"><color rgb="FF20385D"/></'.$side.'>':'/>');$borders.='</border>';}
            foreach($baseStyles as [$font,$border,$align])$styles.='<xf numFmtId="0" fontId="'.$font.'" fillId="0" borderId="'.($edge?$edgeIndex+1:$border).'" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="'.$align.'" vertical="center" wrapText="1"/></xf>';
        }
        $entries['xl/styles.xml']='<?xml version="1.0"?><styleSheet xmlns="'.$ns.'spreadsheetml/2006/main"><fonts count="5">'.$fonts.'</fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="10">'.$borders.'</borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="72">'.$styles.'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
        $entries['[Content_Types].xml']='<?xml version="1.0"?><Types xmlns="'.$ns.'package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$overrides.'</Types>';
        $path=tempnam(sys_get_temp_dir(),'g9-report-');try{$zip=new ZipArchive();if($zip->open($path,ZipArchive::OVERWRITE)!==true)throw new \RuntimeException('Unable to create Excel report.');foreach($entries as $name=>$content)$zip->addFromString($name,$content);$zip->close();return file_get_contents($path);}finally{if(is_file($path))unlink($path);}
    }
    private function xml(string $value): string {return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u','',$value),ENT_XML1|ENT_QUOTES,'UTF-8');}
    private function column(int $number): string {$value='';while($number>0){$number--;$value=chr(65+$number%26).$value;$number=intdiv($number,26);}return $value;}
}
