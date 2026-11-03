<?php

namespace App\Http\Controllers;

use App\Support\G12NationalExamPermissions as Access;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class G12GraphExportController
{
    public function pdf(Request $request)
    {
        abort_unless(Access::allows($request->user(),'export'),403);
        $request->validate(['image'=>['required','file','mimes:png','max:20480']]);
        $file=$request->file('image');$size=@getimagesize($file->getRealPath());
        if(!$size||$size[0]*$size[1]>30000000)throw ValidationException::withMessages(['image'=>'Use a dashboard image below 30 megapixels.']);
        $source=@imagecreatefrompng($file->getRealPath());
        if(!$source)throw ValidationException::withMessages(['image'=>'The dashboard image could not be read.']);
        $temp=storage_path('app/private/mpdf');if(!is_dir($temp))mkdir($temp,0755,true);
        try{
            $pdf=new Mpdf(['format'=>'A4','tempDir'=>$temp,'margin_left'=>0,'margin_right'=>0,'margin_top'=>0,'margin_bottom'=>0]);
            $pdf->SetTitle('G12 Results Dashboard');$pdf->SetAutoPageBreak(false);
            $width=$size[0];$pageHeight=(int)floor($width*277/190);
            for($top=0;$top<$size[1];$top+=$pageHeight){
                $height=min($pageHeight,$size[1]-$top);$page=imagecreatetruecolor($width,$height);
                imagefill($page,0,0,imagecolorallocate($page,255,255,255));imagecopy($page,$source,0,0,0,$top,$width,$height);
                ob_start();imagepng($page);$bytes=ob_get_clean();imagedestroy($page);
                $pdf->AddPage();$pdf->Image('data:image/png;base64,'.base64_encode($bytes),10,10,190,$height*190/$width,'png');
            }
            return response($pdf->Output('',Destination::STRING_RETURN),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="g12-results-dashboard.pdf"']);
        }finally{imagedestroy($source);}
    }
}
