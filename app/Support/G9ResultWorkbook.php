<?php

namespace App\Support;

use ZipArchive;

/** Native OOXML: Khmer text, typed numbers, merged headings and A4 print areas. */
class G9ResultWorkbook
{
    private array $styles=[];
    private const COLORS=['blue'=>'DFEAF4','pale-green'=>'E2EFD9','pale-yellow'=>'FFF2CC','pale-orange'=>'FBE5D6',
        'yellow'=>'FFF8BA','purple'=>'E2DCED','orange'=>'F8D8BD','red'=>'F5D7D9','total'=>'EAF2F8'];

    public function generate(array $pages,string $type): string
    {
        $this->styles=[];$this->style();
        $ns='http://schemas.openxmlformats.org/';$entries=[];$sheets='';$rels='';$overrides='';$areas='';
        foreach($pages as $i=>$page){
            $number=$i+1;$sheetName=ucfirst($type).' '.$number;
            $last=$this->column(count($page['tables'][0]['widths']));$sheetRows=[];$merges=[];
            $add=function(array $cells,float $height=22)use(&$sheetRows){$sheetRows[]=['cells'=>$cells,'height'=>$height];};
            $merged=function(string $text,string $style='plain',float $height=23)use(&$sheetRows,&$merges,$add,$last){
                $add([['value'=>$text,'style'=>$style]],$height);$row=count($sheetRows);$merges[]='A'.$row.':'.$last.$row;
            };
            if($page['header']){
                $merged('ព្រះរាជាណាចក្រកម្ពុជា','title',27);$merged('ជាតិ សាសនា ព្រះមហាក្សត្រ','title',27);
                $merged($type==='list'?$page['location']:$page['department'],'plain left');
                if($type==='list'){$merged('មណ្ឌលប្រឡង៖ '.$page['center'],'plain left');$merged('បន្ទប់លេខ៖ '.G9CandidateReport::roomHeading((string)$page['room']),'plain left');}
                $merged(G9ResultReport::TITLES[$type],'title',28);$merged('ប្រឡងសញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិ','title',25);
                $merged('សម័យប្រឡង៖ '.$page['exam_date'].'  ឆ្នាំសិក្សា៖ '.$page['year'],'plain',23);
            }
            foreach($page['tables'] as $table){
                if(!empty($table['caption']))$merged($table['caption'],'section',27);
                $occupied=[];$start=count($sheetRows)+1;
                foreach($table['headers'] as $hi=>$header){
                    $cells=[];$column=1;$row=$start+$hi;
                    foreach($header as $cell){
                        while(isset($occupied[$row][$column]))$column++;
                        $cells[$column-1]=['value'=>$cell['value'],'style'=>'header '.$cell['style']];
                        if($cell['colspan']>1||$cell['rowspan']>1){
                            $merges[]=$this->column($column).$row.':'.$this->column($column+$cell['colspan']-1).($row+$cell['rowspan']-1);
                            for($r=$row;$r<$row+$cell['rowspan'];$r++)for($c=$column;$c<$column+$cell['colspan'];$c++)$occupied[$r][$c]=true;
                        }
                        $column+=$cell['colspan'];
                    }
                    $add($cells,$type==='list'?30:24);
                }
                foreach($table['rows'] as $row){
                    if(isset($row['section'])){$merged($row['section'],'section',25);continue;}
                    $cells=[];
                    foreach($row['cells'] as $cell){
                        $style=$cell['style'];
                        if($type==='list')$style.=' fit';
                        if(!empty($row['total']))$style.=' total';
                        if(in_array($row['outcome']??'',['failed','absent'],true))$style.=' strike';
                        $cells[]=['value'=>$cell['value'],'style'=>$style];
                    }
                    $add($cells,$type==='list'?20:22);
                }
                if($type==='list')for($blank=count($table['rows']);$blank<25;$blank++)$add(array_fill(0,8,['value'=>'','style'=>'']),20);
                $merged('','plain',8);
            }
            foreach($page['notes'] as $note)$merged($note,'plain left',24);
            foreach($page['footer'] as $line)$merged($line,'plain',22);
            if($page['signature']){
                if($page['lunar'])$merged($page['lunar']);
                if($page['solar'])$merged($page['solar']);
                $merged($page['signer'],'title',26);$merged('','plain',30);$merged($page['signer_name'],'title',26);
            }
            $merged('ទំព័រ '.G9CandidateReport::digits($page['page']).' / '.G9CandidateReport::digits($page['pages']),'plain',18);
            $xmlRows='';
            foreach($sheetRows as $ri=>$row){
                $rn=$ri+1;$xmlRows.='<row r="'.$rn.'" ht="'.$row['height'].'" customHeight="1">';
                foreach($row['cells'] as $ci=>$cell){
                    $ref=$this->column($ci+1).$rn;$value=$cell['value'];$style=$this->style($cell['style']);
                    if(is_int($value)||is_float($value))$xmlRows.='<c r="'.$ref.'" s="'.$style.'"><v>'.$value.'</v></c>';
                    else $xmlRows.='<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->xml($value===null&&str_contains($cell['style'],'percent')?'—':(string)($value??'')).'</t></is></c>';
                }
                $xmlRows.='</row>';
            }
            $widths=$page['tables'][0]['widths'];$sum=array_sum($widths);$cols='';
            foreach($widths as $ci=>$width)$cols.='<col min="'.($ci+1).'" max="'.($ci+1).'" width="'.round($width/$sum*145,2).'" customWidth="1"/>';
            $mergeXml='';foreach($merges as $merge)$mergeXml.='<mergeCell ref="'.$merge.'"/>';
            $entries['xl/worksheets/sheet'.$number.'.xml']='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="'.$ns.'spreadsheetml/2006/main"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><dimension ref="A1:'.$last.count($sheetRows).'"/><sheetViews><sheetView workbookViewId="0" showGridLines="0"/></sheetViews><cols>'.$cols.'</cols><sheetData>'.$xmlRows.'</sheetData><mergeCells count="'.count($merges).'">'.$mergeXml.'</mergeCells><printOptions horizontalCentered="1"/><pageMargins left="0.315" right="0.315" top="0.276" bottom="0.315" header="0" footer="0"/><pageSetup paperSize="9" orientation="portrait" fitToWidth="1" fitToHeight="1"/></worksheet>';
            $sheets.='<sheet name="'.$sheetName.'" sheetId="'.$number.'" r:id="rId'.$number.'"/>';
            $rels.='<Relationship Id="rId'.$number.'" Type="'.$ns.'officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$number.'.xml"/>';
            $overrides.='<Override PartName="/xl/worksheets/sheet'.$number.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $areas.='<definedName name="_xlnm.Print_Area" localSheetId="'.$i.'">\''.$sheetName.'\'!$A$1:$'.$last.'$'.count($sheetRows).'</definedName>';
        }
        $rels.='<Relationship Id="styles" Type="'.$ns.'officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $entries['xl/workbook.xml']='<?xml version="1.0"?><workbook xmlns="'.$ns.'spreadsheetml/2006/main" xmlns:r="'.$ns.'officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets>'.$sheets.'</sheets><definedNames>'.$areas.'</definedNames></workbook>';
        $entries['xl/_rels/workbook.xml.rels']='<?xml version="1.0"?><Relationships xmlns="'.$ns.'package/2006/relationships">'.$rels.'</Relationships>';
        $entries['_rels/.rels']='<?xml version="1.0"?><Relationships xmlns="'.$ns.'package/2006/relationships"><Relationship Id="rId1" Type="'.$ns.'officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
        $entries['xl/styles.xml']=$this->stylesXml();
        $entries['[Content_Types].xml']='<?xml version="1.0"?><Types xmlns="'.$ns.'package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$overrides.'</Types>';
        $path=tempnam(sys_get_temp_dir(),'g9-result-');
        if($path===false)throw new \RuntimeException('Unable to prepare Excel report.');
        try{
            $zip=new ZipArchive();if($zip->open($path,ZipArchive::OVERWRITE)!==true)throw new \RuntimeException('Unable to create Excel report.');
            foreach($entries as $name=>$content)$zip->addFromString($name,$content);
            $zip->close();return file_get_contents($path);
        }finally{if(is_file($path))unlink($path);}
    }

    private function style(string $name=''): int
    {
        $parts=array_filter(explode(' ',trim($name)));sort($parts);$key=implode(' ',$parts);
        if(!array_key_exists($key,$this->styles))$this->styles[$key]=count($this->styles);return $this->styles[$key];
    }

    private function stylesXml(): string
    {
        $fonts='';
        foreach([['Khmer OS Siemreap',9,''],['Khmer OS Muol Light',11,''],['Khmer OS Siemreap',9,'<b/>'],['Khmer OS Siemreap',9,'<color rgb="FFDB293B"/>'],['Khmer OS Siemreap',9,'<strike/>']] as [$name,$size,$extra])
            $fonts.='<font><sz val="'.$size.'"/><name val="'.$name.'"/>'.$extra.'</font>';
        $fills='<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>';
        foreach(self::COLORS as $color)$fills.='<fill><patternFill patternType="solid"><fgColor rgb="FF'.$color.'"/><bgColor indexed="64"/></patternFill></fill>';
        $xfs='';
        foreach($this->styles as $key=>$index){
            $parts=explode(' ',$key);$has=fn($part)=>in_array($part,$parts,true);
            $font=$has('title')?1:($has('strike')?4:($has('result')?3:($has('total')||$has('section')?2:0)));
            $fill=0;foreach(array_keys(self::COLORS) as $i=>$color)if($has($color)){$fill=$i+2;break;}
            $plain=$has('plain')||$has('title');
            $xfs.='<xf numFmtId="'.($has('percent')?10:0).'" fontId="'.$font.'" fillId="'.$fill.'" borderId="'.($plain?0:1).'" xfId="0" applyAlignment="1" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="'.($has('left')||$has('section')?'left':'center').'" vertical="center" wrapText="'.($has('fit')?'0':'1').'"'.($has('fit')?' shrinkToFit="1"':'').'/></xf>';
        }
        return '<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="5">'.$fonts.'</fonts><fills count="'.(count(self::COLORS)+2).'">'.$fills.'</fills><borders count="2"><border/><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="'.count($this->styles).'">'.$xfs.'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function xml(string $value): string {return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u','',$value),ENT_XML1|ENT_QUOTES,'UTF-8');}
    private function column(int $number): string {$value='';while($number>0){$number--;$value=chr(65+$number%26).$value;$number=intdiv($number,26);}return $value;}
}
