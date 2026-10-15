<?php

namespace App\Support;

use ZipArchive;

class ScholarshipExport
{
    public function rankLogo(): ?string
    {
        $relative=\App\Models\BrandingSetting::current()->report_logo_1_path;
        if(!$relative)return null;
        $root=realpath(storage_path('app/public'));
        $path=realpath(storage_path('app/public/'.ltrim($relative,'/\\')));
        if(!$root || !$path || !str_starts_with(strtolower($path),strtolower($root.DIRECTORY_SEPARATOR)) || !is_file($path))return null;
        $mime=mime_content_type($path);
        if(!in_array($mime,['image/png','image/jpeg','image/gif','image/webp','image/svg+xml'],true))return null;
        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }

    /** Count campus headers as rows so groups and final signatures stay inside the page. */
    public function rankPages(array $rows): array
    {
        $grouped=collect($rows)->contains(fn($row)=>array_key_exists('campus',$row));
        if($grouped){
            $campusNumbers=[];
            foreach($rows as &$row){
                $campus=(string)($row['campus_id']??$row['campus']??'');
                $campusNumbers[$campus]=($campusNumbers[$campus]??0)+1;
                $row['campus_number']=$campusNumbers[$campus];
            }
            unset($row);
        }
        $annotate=function(array $items)use($grouped){
            $previous=null;
            foreach($items as &$row){
                $campus=(string)($row['campus_id']??$row['campus']??'');
                if($grouped)$row['show_campus_header']=$previous===null||$previous!==$campus;
                $previous=$campus;
            }
            return $items;
        };
        $pages=[];$page=[];$used=0;$previous=null;
        foreach($rows as $row){
            $campus=(string)($row['campus_id']??$row['campus']??'');
            $header=$grouped&&(!$page||$previous!==$campus);
            $capacity=(count($pages)===0?25:30)+($grouped?1:0);
            if($page && $used+1+(int)$header>$capacity){$pages[]=$annotate($page);$page=[];$used=0;$header=$grouped;}
            $page[]=$row;$used+=1+(int)$header;$previous=$campus;
        }
        $pages[]=$annotate($page);
        $last=count($pages)-1;
        $lastUsed=count($pages[$last])+collect($pages[$last])->where('show_campus_header',true)->count();
        if($lastUsed>($last===0?20:26)+($grouped?1:0)){
            // Keep the preceding page filled instead of splitting its list in half
            // just to make room for signatures. Carry a small group to the last page.
            $tail=array_splice($pages[$last],-min(3,count($pages[$last])-1));
            $pages[$last]=$annotate($pages[$last]);$pages[]=$annotate($tail);
        }
        return $pages;
    }

    public function rankSections(array $rows, int $year, ?string $preparedBy, ?int $signatureCampus=null): array
    {
        $setting=$signatureCampus?\App\Models\ScholarshipPrintSetting::where('academic_year_id',$year)->where('campus_id',$signatureCampus)->first():null;
        return [['campus'=>'','pages'=>$this->rankPages($rows),'total'=>count($rows),'requiresVerifier'=>\App\Models\ScholarshipPrintSetting::requiresVerifier($signatureCampus),
            'preparedPosition'=>$setting?->prepared_position??'','checkedPosition'=>$setting?->checked_position??'','approvedPosition'=>$setting?->approved_position??'',
            'preparedBy'=>$setting?->prepared_by_name??$preparedBy,'preparedDate'=>$setting?->prepared_date?->format('F j, Y')??now()->format('F j, Y'),
            'checkedBy'=>$setting?->checked_by_name??'________________________','checkedDate'=>$setting?->checked_date?->format('F j, Y')??now()->format('F j, Y'),
            'approvedBy'=>$setting?->approved_by_name??'________________________','approvedDate'=>$setting?->approved_date?->format('F j, Y')??now()->format('F j, Y')]];
    }

    public function ranksPdf(array $data): string
    {
        $temp=storage_path('framework/cache/scholarship-pdf');
        if(!is_dir($temp))mkdir($temp,0775,true);
        $pdf=new \Mpdf\Mpdf([
            'mode'=>'utf-8','format'=>'A4','tempDir'=>$temp,
            'margin_left'=>8,'margin_right'=>8,'margin_top'=>8,'margin_bottom'=>8,'margin_footer'=>4,
            'fontDir'=>[...(new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'],public_path('fonts/khmer')],
            'fontdata'=>(new \Mpdf\Config\FontVariables())->getDefaults()['fontdata']+['scholarshipsiemreap'=>['R'=>'KhmerOSsiemreap.ttf','useOTL'=>0xFF],'scholarshipmuol'=>['R'=>'KhmerOSmuollight.ttf','useOTL'=>0xFF]],
            'default_font'=>'dejavusans','default_font_size'=>9,
        ]);
        $pdf->SetTitle('List of Outstanding Students');
        $pdf->SetHTMLFooter('<div style="text-align:right;font-size:8pt">Page {PAGENO} of {nbpg}</div>');
        $pdf->WriteHTML(view('western-scholarship.ranks-print',array_replace($data,['pdfExport'=>true]))->render());
        return $pdf->Output('',\Mpdf\Output\Destination::STRING_RETURN);
    }

    public function ranksXlsx(array $data): string
    {
        return app(ScholarshipRankWorkbook::class)->generate($data);
    }

    /** Inline strings preserve student IDs and prevent spreadsheet formula execution. */
    public function xlsx(array $rows): string
    {
        $escape=fn($value)=>htmlspecialchars((string)$value,ENT_XML1|ENT_QUOTES,'UTF-8');
        $column=function(int $number) { $name=''; for($number++;$number>0;$number=intdiv($number-1,26)) $name=chr(65+(($number-1)%26)).$name; return $name; };
        $sheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" state="frozen"/></sheetView></sheetViews><sheetData>';
        foreach($rows as $r=>$row) {
            $sheet.='<row r="'.($r+1).'">';
            foreach(array_values($row) as $c=>$value) {
                $cell=$column($c).($r+1);
                $sheet.=is_int($value)||is_float($value)?'<c r="'.$cell.'"><v>'.$value.'</v></c>':'<c r="'.$cell.'" t="inlineStr"><is><t xml:space="preserve">'.$escape($value??'').'</t></is></c>';
            }
            $sheet.='</row>';
        }
        $sheet.='</sheetData></worksheet>';
        $path=tempnam(sys_get_temp_dir(),'wis-scholarship-');
        if($path===false) throw new \RuntimeException('Unable to prepare the Excel export.');
        try {
            $zip=new ZipArchive();
            if($zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) throw new \RuntimeException('Unable to create the Excel export.');
            $zip->addFromString('[Content_Types].xml','<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml','<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Scholarship" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $zip->addFromString('xl/worksheets/sheet1.xml',$sheet); $zip->close();
            return file_get_contents($path);
        } finally { if(is_file($path)) unlink($path); }
    }
}
