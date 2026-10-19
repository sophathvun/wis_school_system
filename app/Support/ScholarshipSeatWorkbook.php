<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/** Render the same 60-column page grid as the printable reports, using editable cells. */
class ScholarshipSeatWorkbook
{
    private const NS='http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const REL='http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private array $images=[];

    private function xml(mixed $text): string {return htmlspecialchars((string)$text,ENT_XML1|ENT_QUOTES,'UTF-8');}
    private function col(int $index): string
    { $name='';for($index++;$index>0;$index=intdiv($index-1,26))$name=chr(65+($index-1)%26).$name;return $name; }

    public function generate(array $data): string
    {
        $entries=[];$sheets='';$areas='';$relationships=[];$hasDrawing=false;
        $styles=ScholarshipSeatReports::styles();$styleIds=array_flip(array_keys($styles));
        $logo=$data['logoSrc']?$this->logo($data['logoSrc']):null;
        $types='<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>';
        foreach($this->worksheets($data) as $index=>$worksheet){
            $number=$index+1;
            [$parts,$rowCount,$drawing]=$this->worksheet(array_replace($data,['pages'=>$worksheet['pages']]),$number,$logo,$styles,$styleIds);
            $entries=array_replace($entries,$parts);$hasDrawing=$hasDrawing||$drawing;
            $sheets.='<sheet name="'.$this->xml($worksheet['name']).'" sheetId="'.$number.'" r:id="rId'.$number.'"/>';
            $areas.='<definedName name="_xlnm.Print_Area" localSheetId="'.$index.'">'.$this->xml("'".str_replace("'","''",$worksheet['name'])."'!\$A\$1:\$BH\$".$rowCount).'</definedName>';
            $relationships[]=['rId'.$number,'worksheet','worksheets/sheet'.$number.'.xml'];
            $types.='<Override PartName="/xl/worksheets/sheet'.$number.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            if($drawing)$types.='<Override PartName="/xl/drawings/drawing'.$number.'.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';
        }
        $relationships[]=['rId'.(count($relationships)+1),'styles','styles.xml'];
        $entries['_rels/.rels']=$this->rels([['rId1','officeDocument','xl/workbook.xml']]);
        $entries['xl/workbook.xml']='<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="'.self::NS.'" xmlns:r="'.self::REL.'"><sheets>'.$sheets.'</sheets><definedNames>'.$areas.'</definedNames></workbook>';
        $entries['xl/_rels/workbook.xml.rels']=$this->rels($relationships);
        $entries['xl/styles.xml']=$this->styles($styles);
        foreach(['workbook'=>'sheet.main','styles'=>'styles'] as $part=>$type)$types.='<Override PartName="/xl/'.$part.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.'.$type.'+xml"/>';
        if($hasDrawing){
            $entries['xl/media/logo.'.$logo['ext']]=$logo['bytes'];
            $types.='<Default Extension="'.$logo['ext'].'" ContentType="'.$logo['mime'].'"/>';
        }
        $entries['[Content_Types].xml']=$types.'</Types>';
        $path=tempnam(sys_get_temp_dir(),'scholarship-seat-');if($path===false)throw new RuntimeException('Unable to prepare the Excel report.');
        try{
            $zip=new ZipArchive();if($zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Unable to create the Excel report.');
            foreach($entries as $name=>$bytes)$zip->addFromString($name,$bytes);
            $zip->close();return file_get_contents($path);
        }finally{if(is_file($path))unlink($path);}
    }

    /** Keep all subject/student continuation pages for a room on the same sheet. */
    private function worksheets(array $data): array
    {
        if($data['document']!=='attendance'||!$data['pages'])return [['name'=>'Scholarship Report','pages'=>$data['pages']]];
        $groups=[];$roomCounts=[];
        foreach($data['pages'] as $page){
            $key=json_encode([$page['center_id']??$page['center'],$page['room']],JSON_THROW_ON_ERROR);
            if(!isset($groups[$key])){
                $groups[$key]=['room'=>$page['room'],'center'=>$page['center'],'pages'=>[]];
                $roomCounts[$page['room']]=($roomCounts[$page['room']]??0)+1;
            }
            $groups[$key]['pages'][]=$page;
        }
        $sheets=[];$used=[];
        foreach($groups as $group){
            $base='ROOM '.$group['room'];
            // Excel requires unique sheet names when different centers both have Room 1.
            if($roomCounts[$group['room']]>1)$base.=' - '.$group['center'];
            $base=preg_replace('~[\\\\/?*\[\]:\x00-\x1F]~u',' ',$base);
            $base=trim($base," '\t\n\r");$name=mb_substr($base,0,31);$suffix=1;
            while(isset($used[mb_strtolower($name)])){
                $tail=' ('.++$suffix.')';$name=mb_substr($base,0,31-mb_strlen($tail)).$tail;
            }
            $used[mb_strtolower($name)]=true;$sheets[]=['name'=>$name,'pages'=>$group['pages']];
        }
        return $sheets;
    }

    private function worksheet(array $data,int $number,?array $logo,array &$styles,array &$styleIds): array
    {
        $this->images=[];$sheet='';$merges=[];$breaks=[];$rowNumber=0;
        foreach($data['pages'] as $page){
            if($rowNumber)$breaks[]=$rowNumber;
            foreach($page['rows'] as $pageRow=>$row){
                $rowNumber++;$column=0;
                $sheet.='<row r="'.$rowNumber.'" ht="'.round($row['height']*72/25.4,2).'" customHeight="1">';
                foreach($row['cells'] as $cell){
                    $style=$styles[$cell['style']];$id=$styleIds[$cell['style']];$start=$column;
                    if($cell['span']>1)$merges[]=$this->col($start).$rowNumber.':'.$this->col($start+$cell['span']-1).$rowNumber;
                    for($i=0;$i<$cell['span'];$i++,$column++){
                        $cellStyle=$id;
                        if(in_array($data['document'],['attendance-cover','envelope-cover','room-plan'],true)){
                            $edges=[];
                            if($pageRow===0)$edges[]='top';
                            if($pageRow===array_key_last($page['rows']))$edges[]='bottom';
                            if($column===0)$edges[]='left';
                            if($column===59)$edges[]='right';
                            if($edges){
                                $key=$cell['style'].'-'.implode('-',$edges);
                                if(!isset($styleIds[$key])){$styleIds[$key]=count($styleIds);$styles[$key]=$style+['border_sides'=>array_fill_keys($edges,'#203c65')];}
                                $cellStyle=$styleIds[$key];
                            }
                        }
                        $sheet.='<c r="'.$this->col($column).$rowNumber.'" s="'.$cellStyle.'"';
                        if($i>0){$sheet.='/>';continue;}
                        $lines=$cell['lines'];$single=count($lines)===1?$lines[0]['text']:null;
                        if(is_int($single)||is_float($single)){$sheet.='><v>'.$single.'</v></c>';continue;}
                        $sheet.=' t="inlineStr"><is>';
                        if(!$lines)$sheet.='<t></t>';
                        foreach($lines as $lineIndex=>$line){
                            foreach($line['runs']??[$line] as $runIndex=>$run){
                            $run=array_replace($line,$run);
                            $font=($run['font']??'')==='muol'?'Khmer OS Muol Light':(preg_match('/\p{Khmer}/u',(string)$run['text'])?'Khmer OS Siemreap':'Arial');
                            $sheet.='<r><rPr><rFont val="'.$font.'"/><sz val="'.($run['size']??$style['size']).'"/>'.(($run['bold']??$style['bold']??false)?'<b/>':'').'<color rgb="FF'.str_replace('#','',$run['color']??$style['color']??'#000000').'"/></rPr><t xml:space="preserve">'.$this->xml(($lineIndex&&$runIndex===0?"\n":'').$run['text']).'</t></r>';
                            }
                        }
                        $sheet.='</is></c>';
                    }
                    if(($cell['logo']??false)&&$data['logoSrc'])$this->images[]=['row'=>$rowNumber-1,'column'=>$start,'max_width'=>$cell['logo_width'],'max_height'=>$row['height']-2,'offset_y'=>$cell['logo_offset_y']??0];
                }
                $sheet.='</row>';
            }
        }
        if(!$rowNumber){$rowNumber=1;$sheet='<row r="1"><c r="A1" t="inlineStr"><is><t>No confirmed candidates available.</t></is></c></row>';}
        $entries=[];$drawing=(bool)($logo&&$this->images);
        $mergeXml=implode('',array_map(fn($ref)=>'<mergeCell ref="'.$ref.'"/>',$merges));
        $breakXml=$breaks?'<rowBreaks count="'.count($breaks).'" manualBreakCount="'.count($breaks).'">'.implode('',array_map(fn($row)=>'<brk id="'.$row.'" min="0" max="16383" man="1"/>',$breaks)).'</rowBreaks>':'';
        $entries['xl/worksheets/sheet'.$number.'.xml']='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="'.self::NS.'" xmlns:r="'.self::REL.'"><sheetPr><pageSetUpPr fitToPage="0"/></sheetPr><dimension ref="A1:BH'.$rowNumber.'"/><sheetViews><sheetView showGridLines="0" workbookViewId="0"/></sheetViews><sheetFormatPr defaultRowHeight="18"/><cols><col min="1" max="60" width="1.86" customWidth="1"/></cols><sheetData>'.$sheet.'</sheetData>'.($merges?'<mergeCells count="'.count($merges).'">'.$mergeXml.'</mergeCells>':'').'<printOptions horizontalCentered="1"/><pageMargins left="0.315" right="0.315" top="0.315" bottom="0.315" header="0.1" footer="0.1"/><pageSetup paperSize="9" orientation="portrait" scale="95"/><headerFooter><oddFooter>&amp;RPage &amp;P of &amp;N</oddFooter></headerFooter>'.$breakXml.($drawing?'<drawing r:id="rId1"/>':'').'</worksheet>';
        if($drawing){
            $entries['xl/worksheets/_rels/sheet'.$number.'.xml.rels']=$this->rels([['rId1','drawing','../drawings/drawing'.$number.'.xml']]);
            $entries['xl/drawings/_rels/drawing'.$number.'.xml.rels']=$this->rels([['rId1','image','../media/logo.'.$logo['ext']]]);
            $xml='';
            foreach($this->images as $i=>$image){
                $scale=min($image['max_width']/$logo['width'],$image['max_height']/$logo['height']);
                $width=(int)round($logo['width']*$scale*36000);$height=(int)round($logo['height']*$scale*36000);
                $xml.='<xdr:oneCellAnchor><xdr:from><xdr:col>'.$image['column'].'</xdr:col><xdr:colOff>36000</xdr:colOff><xdr:row>'.$image['row'].'</xdr:row><xdr:rowOff>'.((1+$image['offset_y'])*36000).'</xdr:rowOff></xdr:from><xdr:ext cx="'.$width.'" cy="'.$height.'"/><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="'.($i+1).'" name="'.$this->xml($data['logoLabel']??'School Logo 1').'"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr><xdr:blipFill><a:blip r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill><xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$width.'" cy="'.$height.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';
            }
            $entries['xl/drawings/drawing'.$number.'.xml']='<?xml version="1.0"?><xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="'.self::REL.'">'.$xml.'</xdr:wsDr>';
        }
        return [$entries,$rowNumber,$drawing];
    }

    private function rels(array $items): string
    {return '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.implode('',array_map(fn($item)=>'<Relationship Id="'.$item[0].'" Type="'.self::REL.'/'.$item[1].'" Target="'.$item[2].'"/>',$items)).'</Relationships>';}

    private function logo(string $source): array
    {
        if(!preg_match('~^data:(image/[\w.+-]+);base64,(.+)$~s',$source,$match))throw new RuntimeException('Invalid report logo.');
        $bytes=base64_decode($match[2],true);
        if($bytes!==false&&$match[1]==='image/svg+xml'){
            $svg=new \DOMDocument();if(!@$svg->loadXML($bytes,LIBXML_NONET)||$svg->documentElement?->localName!=='svg')throw new RuntimeException('Invalid report logo.');
            $box=preg_split('/[\s,]+/',trim($svg->documentElement->getAttribute('viewBox')));
            return ['bytes'=>$bytes,'width'=>count($box)===4?max(1,(float)$box[2]):650,'height'=>count($box)===4?max(1,(float)$box[3]):240,'ext'=>'svg','mime'=>'image/svg+xml'];
        }
        $size=$bytes===false?false:@getimagesizefromstring($bytes);$image=$size?@imagecreatefromstring($bytes):false;
        if(!$image)throw new RuntimeException('Unable to read the report logo.');
        ob_start();imagepng($image);$png=ob_get_clean();imagedestroy($image);
        return ['bytes'=>$png,'width'=>$size[0],'height'=>$size[1],'ext'=>'png','mime'=>'image/png'];
    }

    private function styles(array $styles): string
    {
        $fonts='';$fills='<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>';$borders='<border/>';$xfs='';$fillId=2;$borderId=1;
        foreach($styles as $id=>$style){
            $index=array_search($id,array_keys($styles),true);
            $fonts.='<font><sz val="'.($index===0?9:$style['size']).'"/><name val="Arial"/>'.(($style['bold']??false)?'<b/>':'').'<color rgb="FF'.str_replace('#','',$style['color']??'#000000').'"/></font>';
            $fill=0;$border=0;
            if(isset($style['fill'])){$fill=$fillId++;$fills.='<fill><patternFill patternType="solid"><fgColor rgb="FF'.strtoupper(substr($style['fill'],1)).'"/><bgColor indexed="64"/></patternFill></fill>';}
            if(isset($style['border'])||isset($style['border_sides'])){
                $border=$borderId++;$borders.='<border>';
                foreach(['left','right','top','bottom'] as $side){
                    $color=$style['border_sides'][$side]??$style['border']??null;
                    $borders.=$color?'<'.$side.' style="'.($style['border_weight']??'thin').'"><color rgb="FF'.strtoupper(substr($color,1)).'"/></'.$side.'>':'<'.$side.'/>';
                }
                $borders.='</border>';
            }
            $xfs.='<xf numFmtId="0" fontId="'.$index.'" fillId="'.$fill.'" borderId="'.$border.'" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="'.$style['align'].'" vertical="center" wrapText="1"/></xf>';
        }
        return '<?xml version="1.0"?><styleSheet xmlns="'.self::NS.'"><fonts count="'.count($styles).'">'.$fonts.'</fonts><fills count="'.$fillId.'">'.$fills.'</fills><borders count="'.$borderId.'">'.$borders.'</borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="'.count($styles).'">'.$xfs.'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
