<?php

namespace App\Support;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class G12GradeAPdf
{
    public function render(array $data): string
    {
        $temp = storage_path('app/private/mpdf');
        if (!is_dir($temp)) mkdir($temp, 0755, true);

        $fonts = (new FontVariables)->getDefaults()['fontdata'];
        $fonts['g12siemreap'] = ['R' => 'KhmerOSsiemreap.ttf', 'useOTL' => 0xFF];
        $fonts['g12muol'] = ['R' => 'KhmerOSmuollight.ttf', 'useOTL' => 0xFF];

        $pdf = new Mpdf([
            'mode' => 'utf-8', 'format' => str_starts_with($data['type'],'statistics-') ? 'A4-L' : 'A4', 'tempDir' => $temp,
            'margin_left' => 7, 'margin_right' => 7,
            'margin_top' => 10, 'margin_bottom' => 10,
            'fontDir' => [...(new ConfigVariables)->getDefaults()['fontDir'], public_path('fonts/khmer')],
            'fontdata' => $fonts, 'default_font' => 'dejavusans',
            'default_font_size' => 9,
        ]);
        $pdf->SetTitle($data['title']);
        $pdf->shrink_tables_to_fit = 1;
        $view = str_starts_with($data['type'],'statistics-') ? 'g12-national-exams.statistics-pdf' : ($data['type']==='result-list' ? 'g12-national-exams.result-list-pdf' : 'g12-national-exams.grade-a-pdf');
        $html=view($view, $data)->render();
        if(str_starts_with($data['type'],'statistics-')){
            $html=str_replace(['<small>','</small>'],['<br><span style="font-family:dejavusans;font-size:6pt;color:#217695;white-space:nowrap">','</span>'],$html);
            $dom=new \DOMDocument();
            $previous=libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath=new \DOMXPath($dom);
            foreach($xpath->query('//table[contains(@class,"all-statistics-table") or contains(@class,"doeys-table")]/tbody/tr') as $row){
                $cells=$xpath->query('./td',$row);
                if($cells->length<19)continue;
                foreach($cells as $index=>$cell)$cell->setAttribute('width',($index===0?'4.5':($index===1?'18.5':(string)(77/($cells->length-2)))).'%');
            }
            $html=$dom->saveHTML();
            libxml_clear_errors();libxml_use_internal_errors($previous);
        }
        $pdf->WriteHTML($html);

        return $pdf->Output('', Destination::STRING_RETURN);
    }
}
