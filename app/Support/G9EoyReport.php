<?php

namespace App\Support;

use App\Http\Controllers\G9EoyAverageController as Scores;
use Illuminate\Support\Facades\DB;
use Mpdf\Config\{ConfigVariables,FontVariables};
use Mpdf\Mpdf;

class G9EoyReport
{
    public const TITLES=['cover'=>'ក្របសលាកបត្រពិន្ទុប្រចាំឆ្នាំ','card'=>'សលាកបត្រពិន្ទុប្រចាំឆ្នាំ'];

    public function pages($records,array $settings,string $type): array
    {
        $records->loadMissing(['academicYear','campus']);$pages=[];
        $centers=collect($settings['report_centers']??[])->keyBy('exam_center');
        $campusSettings=DB::table('tb_g9_eoy_campus_setting')->whereIn('academic_year_id',$records->pluck('academic_year_id')->unique())->whereIn('campus_id',$records->pluck('campus_id')->unique())->get()->keyBy(fn($row)=>$row->academic_year_id.':'.$row->campus_id);
        $groups=$type==='cover'?$records->groupBy(fn($r)=>json_encode([$r->data['exam_center']??'',$r->data['room_number']??'',$r->data['exam_date']??''])):$records->map(fn($record)=>collect([$record]));
        foreach($groups as $group){
            $record=$group->first();$d=$record->data;$config=$centers->get(trim($d['exam_center']??''),[]);
            $location=$config['location']??$settings['date_location']??'';$solar=$settings['resultDate']??'';
            if(!empty($settings['resultDate_iso'])){
                $parts=explode(' ',G9CandidateReport::khmerDate($settings['resultDate_iso']));
                $solar=$location.' ថ្ងៃទី'.$parts[0].' ខែ'.$parts[1].' ឆ្នាំ'.$parts[2];
            }elseif($location&&($settings['date_location']??'')&&str_starts_with($solar,$settings['date_location']))$solar=$location.mb_substr($solar,mb_strlen($settings['date_location']));
            $names=$campusSettings->get($record->academic_year_id.':'.$record->campus_id);
            $first=Scores::semester($d['semester_1']??null);$second=Scores::semester($d['semester_2']??null);
            $sum=$first!==null&&$second!==null?number_format(((int)round((float)$first*100)+(int)round((float)$second*100))/100,2,'.',''):null;
            $pages[]=['type'=>$type,'center'=>$d['exam_center']??'','room'=>$d['room_number']??'','seat'=>$d['seat_number']??'',
                'exam_date'=>G9CandidateReport::khmerDate($d['exam_date']??null),'academic_year'=>$record->academicYear?->academic_year??'',
                'total'=>$group->count(),'female'=>$group->filter(fn($r)=>in_array(mb_strtolower($r->data['gender']??''),['female','f','ស្រី'],true))->count(),
                'name'=>$record->name_kh?:$record->name_en,'gender'=>['female'=>'ស្រី','f'=>'ស្រី','male'=>'ប្រុស','m'=>'ប្រុស'][mb_strtolower($d['gender']??'')]??($d['gender']??''),
                'dob'=>G9CandidateReport::khmerDate($d['date_of_birth']??null),'grade'=>$d['grade']??'','school'=>$d['school_name']??'',
                'campus'=>$record->campus?->campus_name_en??'','semester_1'=>$first,'semester_2'=>$second,'sum'=>$sum,'eoy'=>Scores::total($first,$second),
                'compiler'=>$names?->compiler_name??'','director'=>$names?->director_name??'','solar'=>$solar,'lunar'=>$settings['k_resultDate']??''];
        }
        return $pages;
    }

    public function pdf(array $pages,string $type): string
    {
        // Fit two complete front/back cards on portrait A4 without rasterizing Khmer text.
        $source=$this->sourcePdf($pages,$type);
        $output=new Mpdf(['format'=>'A4','tempDir'=>storage_path('app/private/g9-report-pdf'),
            'margin_left'=>0,'margin_right'=>0,'margin_top'=>0,'margin_bottom'=>0]);
        $output->SetTitle(self::TITLES[$type]);
        $count=$output->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($source));
        for($page=1;$page<=$count;$page++){
            if($page>1&&$page%2===1)$output->AddPage();
            $template=$output->importPage($page);
            $output->useTemplate($template,0,$page%2===1?0:148.5,210,148.5);
            $output->SetDrawColor(180,180,180);$output->SetLineWidth(.15);$output->SetDash(2,2);
            $output->Line(5,148.5,205,148.5);$output->SetDash();
        }
        return $output->Output('',\Mpdf\Output\Destination::STRING_RETURN);
    }

    private function sourcePdf(array $pages,string $type): string
    {
        $temp=storage_path('app/private/g9-report-pdf');if(!is_dir($temp))mkdir($temp,0755,true);
        $pdf=new Mpdf(['mode'=>'utf-8','format'=>'A4-L','tempDir'=>$temp,'margin_left'=>7,'margin_right'=>7,'margin_top'=>7,'margin_bottom'=>7,
            'fontDir'=>[...(new ConfigVariables)->getDefaults()['fontDir'],public_path('fonts/khmer')],
            'fontdata'=>(new FontVariables)->getDefaults()['fontdata']+['g9body'=>['R'=>'KhmerOSsiemreap.ttf','useOTL'=>0xFF],'g9heading'=>['R'=>'KhmerOSmuollight.ttf','useOTL'=>0xFF],'g9ornament'=>['R'=>'Tacteing.ttf']],
            'default_font'=>'g9body','default_font_size'=>10.5,'shrink_tables_to_fit'=>1]);
        $pdf->SetTitle(self::TITLES[$type]);
        $css=preg_replace('/@page\{[^}]*\}/','',file_get_contents(resource_path('css/pages/g9-eoy-print.css')));
        $pdf->WriteHTML($css,\Mpdf\HTMLParserMode::HEADER_CSS);
        $pdf->AddPage();
        foreach($pages as $index=>$page){
            if($index)$pdf->AddPage();
            $pdf->SetDrawColor(70,70,70);$pdf->SetLineWidth(.25);
            if($type==='cover')$pdf->SetDash(1,1);
            $pdf->Line(148.5,7,148.5,201);$pdf->SetDash();
            foreach([1,2] as $side){
                $classes='eoy-pdf-panel eoy-'.($side===1?'front':'back').' eoy-'.$type;
                $html='<div class="'.$classes.'">'.view('g9-national-exams.eoy-report-panel',['page'=>$page,'type'=>$type,'side'=>$side,'pdfExport'=>true])->render().'</div>';
                $pdf->WriteFixedPosHTML($html,$side===1?12:155.5,7,129.5,185,'auto');
                $pdf->WriteFixedPosHTML('<div style="font-family:g9body;font-size:9pt;color:'.($side===1?'#111':'#ed1717').'">ទំព័រទី'.($side===1?'១':'២').'</div>',$side===1?125:155.5,195,20,6,'visible');
            }
        }
        return $pdf->Output('',\Mpdf\Output\Destination::STRING_RETURN);
    }
}
