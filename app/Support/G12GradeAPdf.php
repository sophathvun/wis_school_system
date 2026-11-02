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
            'mode' => 'utf-8', 'format' => 'A4', 'tempDir' => $temp,
            'margin_left' => 7, 'margin_right' => 7,
            'margin_top' => 10, 'margin_bottom' => 10,
            'fontDir' => [...(new ConfigVariables)->getDefaults()['fontDir'], public_path('fonts/khmer')],
            'fontdata' => $fonts, 'default_font' => 'dejavusans',
            'default_font_size' => 9,
        ]);
        $pdf->SetTitle($data['title']);
        $pdf->shrink_tables_to_fit = 1;
        $pdf->WriteHTML(view($data['type']==='result-list' ? 'g12-national-exams.result-list-pdf' : 'g12-national-exams.grade-a-pdf', $data)->render());

        return $pdf->Output('', Destination::STRING_RETURN);
    }
}
