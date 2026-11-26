<?php
namespace App\Support;

/** Small dependency-free XLSX writer; all values remain literal text, including IDs and phones. */
class GepTransferWorkbook
{
    public static function write(string $path,array $headers,iterable $rows): void
    {
        $xml=fn($value)=>htmlspecialchars(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u','',(string)($value??'')),ENT_XML1|ENT_QUOTES,'UTF-8');
        $column=function(int $index){$name='';do{$name=chr(65+$index%26).$name;$index=intdiv($index,26)-1;}while($index>=0);return $name;};
        $sheet='<row r="1">';foreach($headers as $i=>$value)$sheet.='<c r="'.$column($i).'1" t="inlineStr" s="1"><is><t>'.$xml($value).'</t></is></c>';$sheet.='</row>';$n=1;
        foreach($rows as $row){$n++;$sheet.='<row r="'.$n.'">';foreach($headers as $i=>$key)$sheet.='<c r="'.$column($i).$n.'" t="inlineStr"><is><t xml:space="preserve">'.$xml($row[$key]??'').'</t></is></c>';$sheet.='</row>';}
        $ns='http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $entries=[
            '[Content_Types].xml'=>'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels'=>'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="'.$ns.'/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml'=>'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="'.$ns.'"><sheets><sheet name="GEP Records" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels'=>'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="'.$ns.'/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="'.$ns.'/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml'=>'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF203858"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="49" fontId="1" fillId="2" borderId="0" xfId="0"/></cellXfs></styleSheet>',
            'xl/worksheets/sheet1.xml'=>'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="'.count($headers).'" width="24" customWidth="1"/></cols><sheetData>'.$sheet.'</sheetData><autoFilter ref="A1:'.$column(count($headers)-1).$n.'"/></worksheet>',
        ];
        $zip=new \ZipArchive;if($zip->open($path,\ZipArchive::CREATE|\ZipArchive::OVERWRITE)!==true)throw new \RuntimeException('Cannot create Excel export.');
        foreach($entries as $name=>$content)$zip->addFromString($name,'<?xml version="1.0" encoding="UTF-8"?>'.$content);
        if(!$zip->close())throw new \RuntimeException('Cannot finish Excel export.');
    }
}
