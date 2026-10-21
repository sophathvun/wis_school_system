<?php
namespace App\Support;

use App\Models\{AcademicYear,ScholarshipCertificateSetting};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScholarshipCertificateReport
{
    private function dataImage(?string $relative): ?string
    {
        if(!$relative)return null;$root=realpath(storage_path('app/private/scholarship-certificates'));
        $file=realpath(storage_path('app/private/'.$relative));
        if(!$root||!$file||!str_starts_with(strtolower($file),strtolower($root.DIRECTORY_SEPARATOR))||!is_file($file))return null;
        $mime=mime_content_type($file);if(!in_array($mime,['image/png','image/jpeg','image/webp'],true))return null;
        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($file));
    }
    public function assets(AcademicYear $year): array
    {
        $row=ScholarshipCertificateSetting::where('academic_year_id',$year->id)->first();
        return ['frame'=>$this->dataImage($row?->frame_path)??'data:image/jpeg;base64,'.base64_encode(file_get_contents(public_path('images/scholarship/certificates/frame.jpg'))),
            'branding1'=>app(ScholarshipExport::class)->reportLogo(1),'branding2'=>app(ScholarshipExport::class)->reportLogo(2),'upload'=>$this->dataImage($row?->logo_path)];
    }
    public function upload(UploadedFile $file): string
    {
        $info=@getimagesize($file->getRealPath());
        if(!$info||$info[0]*$info[1]>20000000)throw ValidationException::withMessages(['image'=>'Use an image with at most 20 megapixels.']);
        $image=@imagecreatefromstring(file_get_contents($file->getRealPath()));
        if(!$image)throw ValidationException::withMessages(['image'=>'This image could not be read.']);
        imagealphablending($image,false);imagesavealpha($image,true);
        $path='scholarship-certificates/'.Str::uuid().'.png';$folder=storage_path('app/private/scholarship-certificates');
        if(!is_dir($folder))mkdir($folder,0755,true);
        try{if(!imagepng($image,storage_path('app/private/'.$path),6))throw new \RuntimeException('Could not save certificate image.');}finally{imagedestroy($image);}
        return $path;
    }
    /** Branding supports SVG logos as well as raster images. */
    private function imageSize(string $source): array
    {
        $info=@getimagesize($source);if($info)return [(float)$info[0],(float)$info[1]];
        if(str_starts_with($source,'data:image/svg+xml;base64,')){
            $svg=base64_decode(substr($source,strpos($source,',')+1),true);$document=new \DOMDocument();
            $previous=libxml_use_internal_errors(true);
            try{
                if($svg&&$document->loadXML($svg,LIBXML_NONET)){
                    $root=$document->documentElement;$box=preg_split('/[\s,]+/',trim($root->getAttribute('viewBox')));
                    if(count($box)===4 && is_numeric($box[2]) && is_numeric($box[3]) && $box[2]>0 && $box[3]>0)return [(float)$box[2],(float)$box[3]];
                    $width=(float)$root->getAttribute('width');$height=(float)$root->getAttribute('height');if($width>0&&$height>0)return [$width,$height];
                }
            }finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
        }
        throw ValidationException::withMessages(['logo'=>'The selected logo dimensions could not be read. Upload a PNG or JPG logo.']);
    }
    public function pdf(array $pages,array $assets,bool $frame): string
    {
        $fontdata=(new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];
        foreach(ScholarshipCertificateLayout::fonts() as $key=>$font)$fontdata[$key]=['R'=>$font['file'],'B'=>$font['bold']??$font['file'],'useOTL'=>$font['directory']==='khmer'?0xFF:0];
        $temp=storage_path('app/private/mpdf');if(!is_dir($temp))mkdir($temp,0755,true);
        $pdf=new \Mpdf\Mpdf(['mode'=>'utf-8','format'=>'A4','orientation'=>'P','margin_left'=>0,'margin_right'=>0,'margin_top'=>0,'margin_bottom'=>0,
            'tempDir'=>$temp,'fontDir'=>[...(new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'],public_path('fonts/certificates'),public_path('fonts/khmer')],
            'fontdata'=>$fontdata,'default_font'=>'schcertsans']);
        $pdf->SetTitle('WIS Scholarship Certificates');$pdf->SetAuthor('Western International School');$pdf->SetAutoPageBreak(false);
        foreach($pages as $i=>$page){
            $pdf->AddPage();
            if($frame)$pdf->Image($assets['frame'],0,0,210,297,'', '',true,false);
            $layout=$page['layout'];
            if($frame&&$layout['fields']['logo']['visible']&&!empty($assets[$layout['logo_source']])){
                $pdf->SetFillColor(255,255,255);$pdf->Rect(23,23,82,59,'F');
            }
            foreach($layout['fields'] as $key=>$field){
                if(!$field['visible'])continue;
                $x=$field['x']+$layout['offset_x'];$y=$field['y']+$layout['offset_y'];$w=$field['width'];$h=$field['height'];
                if($key==='logo'){
                    $src=$assets[$layout['logo_source']]??null;
                    if($src){
                        [$imageWidth,$imageHeight]=$this->imageSize($src);$scale=min($w/$imageWidth,$h/$imageHeight);$iw=$imageWidth*$scale;$ih=$imageHeight*$scale;
                        $pdf->Image($src,$x+($w-$iw)/2,$y,$iw,$ih,'','',true,false);
                    }
                    continue;
                }
                $text=ScholarshipCertificateLayout::text($field['text']??'',$page['values']);$size=(float)$field['size'];
                $pdf->SetFont($field['font'],$field['bold']?'B':'',$size);
                $lines=explode("\n",str_replace("\r",'',$text));$width=max(1,...array_map(fn($line)=>$pdf->GetStringWidth($line),$lines));
                $ratio=min(1,($w-.8)/$width,($h-.8)/(count($lines)*$size*25.4/72*1.35));$size=max(6,$size*$ratio);
                $html='<div style="padding:0;margin:0;font-family:'.$field['font'].';font-size:'.$size.'pt;font-weight:'.($field['bold']?'bold':'normal').';color:'.$field['color'].';text-align:'.$field['align'].';line-height:1.35;">'.nl2br(e($text),false).'</div>';
                $pdf->WriteFixedPosHTML($html,$x,$y,$w,$h,'hidden');
            }
        }
        return $pdf->Output('','S');
    }
}
