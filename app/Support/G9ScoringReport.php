<?php

namespace App\Support;

use Mpdf\Config\{ConfigVariables,FontVariables};
use Mpdf\Mpdf;

class G9ScoringReport
{
    public const TITLE='តារាងផ្ទៀងផ្ទាត់ពិន្ទុ';
    public const COLLECTION_TITLE='តារាងស្រង់ពិន្ទុ';
    public const HEADING='តារាងស្រង់ពិន្ទុប្រឡងសញ្ញាបត្រមធ្យមសិក្សា បឋមភូមិ ថ្នាក់ទី៩';
    public const SUBJECTS=[
        'score_khmer_essay'=>['តែងសេចក្តី','60'],'score_chemistry'=>['គីមីវិទ្យា','25'],
        'score_earth_science'=>['ផែនដីវិទ្យា','25'],'score_physics'=>['រូបវិទ្យា','35'],
        'score_geography'=>['ភូមិវិទ្យា','32'],'score_values'=>['សីលធម៌ ពលរដ្ឋ','35'],
        'score_math'=>['គណិតវិទ្យា','100'],'score_khmer_dictation'=>['សរសេរ តាមអាន','40'],
        'score_biology'=>['ជីវវិទ្យា','35'],'score_history'=>['ប្រវត្តិវិទ្យា','33'],
        'score_english'=>['ភាសា បរទេស','50'],
    ];

    public function pages($records,array $settings,string $type='verify'): array
    {
        $records->loadMissing('academicYear');$pages=[];
        $centers=collect($settings['report_centers']??[])->keyBy('exam_center');
        foreach($records->groupBy(fn($r)=>json_encode([$r->data['exam_center']??'',$r->data['room_number']??'',$r->data['exam_date']??''])) as $group){
            $first=$group->first();$data=$first->data;$config=$centers->get(trim($data['exam_center']??''),[]);
            $rows=$group->sortBy(fn($r)=>(int)($r->data['seat_number']??0))->map(function($record){
                $d=$record->data;$summary=G9ScoreSummary::calculate($d);
                return ['seat'=>$d['seat_number']??'','name'=>$record->name_kh?:$record->name_en,
                    'dob'=>!empty($d['date_of_birth'])?date('d-m-Y',strtotime($d['date_of_birth'])):'',
                    'school'=>$d['school_name']??'','female'=>in_array(mb_strtolower(trim($d['gender']??'')),['female','f','ស្រី'],true),
                    'scores'=>array_intersect_key($d,self::SUBJECTS),'summary'=>$summary,'absent'=>$record->attendance_status==='absent','reason'=>$record->attendance_reason??''];
            })->values();
            $counted=$type==='scores'?$rows->reject(fn($r)=>$r['absent']):$rows;
            $passed=$counted->filter(fn($r)=>$r['summary']['complete']&&$r['summary']['total']>=260);
            $failed=$counted->filter(fn($r)=>$r['summary']['complete']&&$r['summary']['total']<260);
            $summary=['total'=>$rows->count(),'female'=>$rows->where('female',true)->count(),
                'passed'=>$passed->count(),'passed_female'=>$passed->where('female',true)->count(),
                'failed'=>$failed->count(),'failed_female'=>$failed->where('female',true)->count(),
                'pending'=>$counted->filter(fn($r)=>!$r['summary']['complete'])->count(),
                'absent'=>$rows->where('absent',true)->count(),'absent_female'=>$rows->where('absent',true)->where('female',true)->count()];
            $effective=G9ExamCenterSettings::resolve($settings,(string)($data['exam_center']??''));
            $location=$config['location']??$settings['date_location']??'រាជធានីភ្នំពេញ';
            $solar=$effective['resultDate']??'';
            if(!empty($effective['resultDate_iso'])){
                $parts=explode(' ',G9CandidateReport::khmerDate($effective['resultDate_iso']));
                $solar=$location.' ថ្ងៃទី'.$parts[0].' ខែ'.$parts[1].' ឆ្នាំ'.$parts[2];
            }
            $chunks=$rows->chunk(25);
            foreach($chunks as $index=>$chunk)$pages[]=[
                'center'=>$data['exam_center']??'','room'=>$data['room_number']??'',
                'location'=>G9CandidateReport::locationHeading($config['location']??$settings['date_location']??'រាជធានីភ្នំពេញ'),
                'exam_date'=>G9CandidateReport::khmerDate($data['exam_date']??null),'year'=>$first->academicYear?->academic_year??'',
                'rows'=>$chunk->values()->all(),'summary'=>$summary,'page'=>$index+1,'pages'=>$chunks->count(),
                'solar'=>$solar,'lunar'=>$effective['k_resultDate']??'','signer_title'=>$config['signer']??'ប្រធានមណ្ឌល','signer_name'=>$config['signer_name']??'',
            ];
        }
        return $pages;
    }

    public function pdf(array $pages,string $type='verify'): string
    {
        $folder=storage_path('app/private/g9-report-pdf');if(!is_dir($folder))mkdir($folder,0755,true);
        $pdf=new Mpdf(['mode'=>'utf-8','format'=>'A4-L','tempDir'=>$folder,
            'margin_left'=>8,'margin_right'=>8,'margin_top'=>7,'margin_bottom'=>7,
            'fontDir'=>[...(new ConfigVariables)->getDefaults()['fontDir'],public_path('fonts/khmer')],
            'fontdata'=>(new FontVariables)->getDefaults()['fontdata']+[
                'g9body'=>['R'=>'KhmerOSsiemreap.ttf','useOTL'=>0xFF],
                'g9heading'=>['R'=>'KhmerOSmuollight.ttf','useOTL'=>0xFF],
                'g9ornament'=>['R'=>'Tacteing.ttf']],
            'default_font'=>'g9body','default_font_size'=>8,'shrink_tables_to_fit'=>1]);
        $pdf->SetTitle($type==='scores'?self::COLLECTION_TITLE:self::TITLE);
        $css=preg_replace('/@page\{[^}]*\}/','',file_get_contents(resource_path('css/pages/g9-scoring-print.css')));
        $pdf->WriteHTML($css,\Mpdf\HTMLParserMode::HEADER_CSS);
        if($type==='scores')$pdf->WriteHTML(file_get_contents(resource_path('css/pages/g9-score-collection-print.css')),\Mpdf\HTMLParserMode::HEADER_CSS);
        foreach($pages as $index=>$page){
            if($index)$pdf->AddPage();
            $page['marker_prefix']='collection-'.$index;
            $pdf->WriteHTML(view($type==='scores'?'g9-national-exams.score-collection-page':'g9-national-exams.scoring-report-page',['page'=>$page,'pdfExport'=>true])->render());
            if($type==='scores'){
                $pdf->SetDrawColor(208,32,48);$pdf->SetLineWidth(.3);
                foreach($page['rows'] as $rowIndex=>$row){
                    $anchor=$pdf->internallink[$page['marker_prefix'].'-'.$rowIndex]??null;
                    if(!$anchor||$anchor['PAGE']!==$pdf->page)continue;
                    $summary=$row['summary'];$y=$anchor['Y']+1.9;
                    if($row['absent']||($summary['complete']&&$summary['total']<260))$pdf->Line(8,$y,289,$y);
                    elseif($summary['complete'])$pdf->Ellipse(13,$y,4.1,2.15);
                }
                $pdf->WriteFixedPosHTML('<div style="font-family:g9body;font-size:7pt;text-align:right">ទំព័រ '.G9CandidateReport::digits($page['page']).' / '.G9CandidateReport::digits($page['pages']).'</div>',259,201,29,5,'visible');
            }
        }
        return $pdf->Output('',\Mpdf\Output\Destination::STRING_RETURN);
    }
}
