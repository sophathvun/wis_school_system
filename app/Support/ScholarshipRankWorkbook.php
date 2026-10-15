<?php

namespace App\Support;

use DateTimeImmutable;
use RuntimeException;
use ZipArchive;

/** The A4 outstanding-student report as editable, styled Excel cells. */
class ScholarshipRankWorkbook
{
    private const NS='http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const OFFICE_NS='http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const RELS_NS='http://schemas.openxmlformats.org/package/2006/relationships';
    // Extra grid boundaries let both the table and equal-width signatures use merges.
    private const EDGES=[0,4,10,33.333333,34,50,55.5,66.666667,67.5,73,79.5,86,93,100];
    private const TABLE=[['A','A'],['B','B'],['C','D'],['E','F'],['G','H'],['I','I'],['J','J'],['K','K'],['L','L'],['M','M']];
    private array $rows=[];
    private array $merges=[];
    private array $breaks=[];

    public function generate(array $data): string
    {
        $this->rows=[];$this->merges=[];$this->breaks=[];
        $sections=$data['reportSections'];
        foreach($sections as $section){
            foreach($section['pages'] as $pageIndex=>$students){
                if($this->rows)$this->breaks[]=count($this->rows);
                if($pageIndex===0)$this->heading($data);
                $labels=['No.','','Name','គោត្តនាម-នាម','ID','Gender','Grade','Group','Rank','Campus'];
                $header=$this->row(25.26);
                $this->merged($header,'A','B','No.',4);
                foreach(array_slice(self::TABLE,2,null,true) as $i=>[$from,$to])$this->merged($header,$from,$to,$labels[$i],match($i){3=>5,5=>19,default=>4});
                foreach($students as $student){
                    if($student['show_campus_header']??false){
                        $group=$this->row(20.88);$this->merged($group,'A','M','Campus: '.$student['campus'],6);
                    }
                    $row=$this->row(25.06);
                    $values=[$student['number'],$student['campus_number']??$student['number'],$student['name_en'],$student['name_kh'],(string)$student['id'],$student['gender'],$student['grade'],$student['group']?:'—',$student['rank'],$student['campus']];
                    foreach(self::TABLE as $i=>[$from,$to])$this->merged($row,$from,$to,$values[$i],match($i){2=>8,3=>9,4=>10,8=>11,default=>7});
                }
                if(!$students){$row=$this->row(24);$this->merged($row,'A','M','No student ranks assigned for these filters.',7);}
                if($pageIndex===array_key_last($section['pages']))$this->signatures($section);
            }
        }
        $logo=$this->logo($data['logoSrc']??null);
        return $this->package($logo);
    }

    private function heading(array $data): void
    {
        foreach([
            ['ព្រះរាជាណាចក្រកម្ពុជា',20,1],['ជាតិ សាសនា ព្រះមហាក្សត្រ',20,2],
            ['KINGDOM OF CAMBODIA',16,3],['NATION RELIGION KING',16,3],
        ] as [$text,$height,$style]){
            $row=$this->row($height);$this->merged($row,'G','M',$text,$style);
        }
        $this->row(3);
        $row=$this->row(25);$this->merged($row,'A','M','List of Outstanding Students',12);
        $row=$this->row(20);$this->merged($row,'A','M','Academic Year: '.($data['year']?:'—'),13);
        $row=$this->row(19);$this->merged($row,'A','M',$data['campusLabel']??'All Campuses',14);
    }

    private function signatures(array $section): void
    {
        $row=$this->row(20);$this->merged($row,'A','M',(int)$section['total'],15);
        $roles=($section['requiresVerifier']??false)
            ?[['prepared','Prepared by','A','C'],['checked','Checked and Verified by','D','G'],['approved','Approved by','H','M']]
            :[['prepared','Prepared by','A','E'],['approved','Approved by','F','M']];
        $dates=$this->row(17);$labels=$this->row(17);$this->row(42);$names=$this->row(19);$positions=$this->row(16);
        foreach($roles as [$role,$label,$from,$to]){
            $date=new DateTimeImmutable($section[$role.'Date']);
            $serial=(int)(new DateTimeImmutable('1899-12-30'))->diff($date)->format('%r%a');
            $this->merged($dates,$from,$to,$serial,16);
            $this->merged($labels,$from,$to,$label,14);
            $this->merged($names,$from,$to,$section[$role.'By']??'________________________',17);
            $this->merged($positions,$from,$to,$section[$role.'Position']??'',18);
        }
    }

    private function row(float $height): int
    {
        $this->rows[]=['height'=>$height,'cells'=>[]];
        return count($this->rows);
    }

    private function merged(int $row,string $from,string $to,mixed $value,int $style): void
    {
        foreach(range(ord($from),ord($to)) as $column)$this->rows[$row-1]['cells'][chr($column)]=['value'=>chr($column)===$from?$value:'','style'=>$style];
        if($from!==$to)$this->merges[]=$from.$row.':'.$to.$row;
    }

    private function xml(string $text): string
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',$text),ENT_XML1|ENT_QUOTES,'UTF-8');
    }

    private function logo(?string $source): ?array
    {
        if(!$source)return null;
        if(!preg_match('~^data:(image/[\w.+-]+);base64,(.+)$~s',$source,$match))throw new RuntimeException('Invalid report logo.');
        $bytes=base64_decode($match[2],true);
        if($bytes!==false&&$match[1]==='image/svg+xml'){
            $svg=new \DOMDocument();
            if(!@$svg->loadXML($bytes,LIBXML_NONET)||$svg->documentElement?->localName!=='svg')throw new RuntimeException('Invalid School Logo 1 SVG.');
            $box=preg_split('/[\s,]+/',trim($svg->documentElement->getAttribute('viewBox')));
            $width=count($box)===4?(float)$box[2]:(float)$svg->documentElement->getAttribute('width');
            $height=count($box)===4?(float)$box[3]:(float)$svg->documentElement->getAttribute('height');
            if($width<=0||$height<=0){$width=650;$height=240;}
            return $this->logoSize($bytes,$width,$height,'svg','image/svg+xml');
        }
        $size=$bytes===false?false:@getimagesizefromstring($bytes);
        $image=$size?@imagecreatefromstring($bytes):false;
        if(!$image)throw new RuntimeException('Unable to read School Logo 1 for Excel.');
        ob_start();imagepng($image);$png=ob_get_clean();imagedestroy($image);
        return $this->logoSize($png,$size[0],$size[1],'png','image/png');
    }

    private function logoSize(string $bytes,float $width,float $height,string $extension,string $mime): array
    {
        $scale=min((245.67/.95)/$width,96/$height);
        return ['bytes'=>$bytes,'extension'=>$extension,'mime'=>$mime,'width'=>(int)round($width*$scale*9525),'height'=>(int)round($height*$scale*9525),'top'=>(int)round(max(0,96-$height*$scale)*9525)];
    }

    private function package(?array $logo): string
    {
        $sheet='';
        foreach($this->rows as $i=>$row){
            $number=$i+1;$sheet.='<row r="'.$number.'" ht="'.$row['height'].'" customHeight="1">';
            ksort($row['cells']);
            foreach($row['cells'] as $column=>$cell){
                $value=$cell['value'];$sheet.='<c r="'.$column.$number.'" s="'.$cell['style'].'"';
                $sheet.=is_int($value)||is_float($value)?'><v>'.$value.'</v></c>':' t="inlineStr"><is><t xml:space="preserve">'.$this->xml((string)$value).'</t></is></c>';
            }
            $sheet.='</row>';
        }
        $cols='';
        foreach(array_slice(self::EDGES,1,null,true) as $index=>$edge){
            $pixels=729.45*($edge-self::EDGES[$index-1])/100;
            // OOXML widths include the cell padding. Arial 9 measures 4.8 pt
            // per stored width unit in Excel. A 193 mm grid leaves room for
            // Excel's column rounding within the 194 mm printable A4 width.
            $width=$pixels/6.4;
            $cols.='<col min="'.$index.'" max="'.$index.'" width="'.round($width,4).'" customWidth="1"/>';
        }
        $merges=implode('',array_map(fn($ref)=>'<mergeCell ref="'.$ref.'"/>',$this->merges));
        $breaks=$this->breaks?'<rowBreaks count="'.count($this->breaks).'" manualBreakCount="'.count($this->breaks).'">'.implode('',array_map(fn($row)=>'<brk id="'.$row.'" min="0" max="16383" man="1"/>',$this->breaks)).'</rowBreaks>':'';
        $last=count($this->rows);
        $worksheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="'.self::NS.'" xmlns:r="'.self::OFFICE_NS.'"><sheetPr><pageSetUpPr fitToPage="0"/></sheetPr><dimension ref="A1:M'.$last.'"/><sheetViews><sheetView showGridLines="0" workbookViewId="0"/></sheetViews><sheetFormatPr defaultRowHeight="18"/><cols>'.$cols.'</cols><sheetData>'.$sheet.'</sheetData><mergeCells count="'.count($this->merges).'">'.$merges.'</mergeCells><printOptions horizontalCentered="1"/><pageMargins left="0.31496" right="0.31496" top="0.31496" bottom="0.31496" header="0.1" footer="0.15748"/><pageSetup paperSize="9" orientation="portrait" scale="95" horizontalDpi="300" verticalDpi="300"/><headerFooter><oddFooter>&amp;R&amp;&quot;Arial,Regular&quot;&amp;8Page &amp;P of &amp;N</oddFooter></headerFooter>'.$breaks.($logo?'<drawing r:id="rId1"/>':'').'</worksheet>';
        $entries=[
            '_rels/.rels'=>'<?xml version="1.0"?><Relationships xmlns="'.self::RELS_NS.'"><Relationship Id="rId1" Type="'.self::OFFICE_NS.'/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml'=>'<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="'.self::NS.'" xmlns:r="'.self::OFFICE_NS.'"><sheets><sheet name="Outstanding Students" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm.Print_Area" localSheetId="0">\'Outstanding Students\'!$A$1:$M$'.$last.'</definedName></definedNames></workbook>',
            'xl/_rels/workbook.xml.rels'=>'<?xml version="1.0"?><Relationships xmlns="'.self::RELS_NS.'"><Relationship Id="rId1" Type="'.self::OFFICE_NS.'/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="'.self::OFFICE_NS.'/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml'=>$this->styles(),'xl/worksheets/sheet1.xml'=>$worksheet,
        ];
        if($logo){
            $entries['xl/media/logo.'.$logo['extension']]=$logo['bytes'];
            $entries['xl/worksheets/_rels/sheet1.xml.rels']='<?xml version="1.0"?><Relationships xmlns="'.self::RELS_NS.'"><Relationship Id="rId1" Type="'.self::OFFICE_NS.'/drawing" Target="../drawings/drawing1.xml"/></Relationships>';
            $entries['xl/drawings/_rels/drawing1.xml.rels']='<?xml version="1.0"?><Relationships xmlns="'.self::RELS_NS.'"><Relationship Id="rId1" Type="'.self::OFFICE_NS.'/image" Target="../media/logo.'.$logo['extension'].'"/></Relationships>';
            $entries['xl/drawings/drawing1.xml']='<?xml version="1.0"?><xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="'.self::OFFICE_NS.'"><xdr:oneCellAnchor><xdr:from><xdr:col>0</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>'.$logo['top'].'</xdr:rowOff></xdr:from><xdr:ext cx="'.$logo['width'].'" cy="'.$logo['height'].'"/><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="1" name="School Logo 1"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr><xdr:blipFill><a:blip r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill><xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$logo['width'].'" cy="'.$logo['height'].'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>';
        }
        $types='<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>';
        foreach(['workbook'=>'sheet.main','styles'=>'styles','worksheets/sheet1'=>'worksheet'] as $part=>$type)$types.='<Override PartName="/xl/'.$part.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.'.$type.'+xml"/>';
        if($logo)$types.='<Default Extension="'.$logo['extension'].'" ContentType="'.$logo['mime'].'"/><Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';
        $entries['[Content_Types].xml']=$types.'</Types>';
        $path=tempnam(sys_get_temp_dir(),'scholarship-ranks-');
        if($path===false)throw new RuntimeException('Unable to prepare the Excel report.');
        try{
            $zip=new ZipArchive();if($zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Unable to create the Excel report.');
            foreach($entries as $name=>$contents)$zip->addFromString($name,$contents);
            $zip->close();return file_get_contents($path);
        }finally{if(is_file($path))unlink($path);}
    }

    private function styles(): string
    {
        $fonts='';
        foreach([['Arial',9,false],['Khmer OS Muol Light',11,false],['Khmer OS Muol Light',10,false],['Arial',11,true],['Khmer OS Siemreap',11,false],['Arial',8.5,false],['Arial',9,true],['Arial',16,true],['Arial',12,false],['Arial',11,false],['Khmer OS Siemreap',11,true],['Arial',7.5,false],['Arial',10,true]] as [$name,$size,$bold])$fonts.='<font>'.($bold?'<b/>':'').'<sz val="'.$size.'"/><name val="'.$name.'"/></font>';
        $xfs='';
        // font, fill, border, horizontal alignment, number format
        foreach([[0,0,0,'left',0],[1,0,0,'center',0],[2,0,0,'center',0],[3,0,0,'center',0],[0,2,1,'center',0],[4,2,1,'center',0],[12,3,1,'left',0],[0,0,1,'center',0],[5,0,1,'left',0],[4,0,1,'left',0],[0,0,1,'center',49],[6,0,1,'center',0],[7,0,0,'center',0],[8,0,0,'center',0],[9,0,0,'center',0],[0,0,0,'left',165],[0,0,0,'center',164],[10,0,0,'center',0],[0,0,0,'center',0],[11,2,1,'center',0]] as [$font,$fill,$border,$align,$format])$xfs.='<xf numFmtId="'.$format.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="'.$border.'" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1" applyNumberFormat="1"><alignment horizontal="'.$align.'" vertical="center" wrapText="1"/></xf>';
        return '<?xml version="1.0"?><styleSheet xmlns="'.self::NS.'"><numFmts count="2"><numFmt numFmtId="164" formatCode="&quot;Phnom Penh, &quot;mmmm d, yyyy"/><numFmt numFmtId="165" formatCode="&quot;Total students: &quot;#,##0"/></numFmts><fonts count="13">'.$fonts.'</fonts><fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFFF99"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8EEF8"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"><color rgb="FF000000"/></left><right style="thin"><color rgb="FF000000"/></right><top style="thin"><color rgb="FF000000"/></top><bottom style="thin"><color rgb="FF000000"/></bottom></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="20">'.$xfs.'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
