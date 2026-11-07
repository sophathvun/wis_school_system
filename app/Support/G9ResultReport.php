<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Mpdf\Config\{ConfigVariables,FontVariables};
use Mpdf\Mpdf;

class G9ResultReport
{
    public const TITLES=[
        'list'=>'បញ្ជីលទ្ធផលប្រឡង',
        'room'=>'ស្ថិតិលទ្ធផលតាមបន្ទប់',
        'subject'=>'ស្ថិតិលទ្ធផលតាមមុខវិជ្ជា',
        'doeys'=>'ស្ថិតិលទ្ធផលប្រឡង (DoEYS)',
        'branch'=>'ស្ថិតិលទ្ធផលប្រឡង (សាខា)',
        'wis'=>'ស្ថិតិលទ្ធផលប្រឡង (WIS)',
    ];
    public const GRADES=['ល្អ','ល្អបង្គួរ','មធ្យម','ធ្លាក់'];

    public static function cell(mixed $value,string $style='',int $colspan=1,int $rowspan=1): array
    {
        return ['value'=>$value,'style'=>$style,'colspan'=>$colspan,'rowspan'=>$rowspan];
    }

    /** One page model drives the browser, PDF and Excel versions. */
    public function pages(Collection $records,array $settings,string $type,string $year): array
    {
        $pages=[];
        if($type==='list'){
            foreach($records->groupBy(fn($r)=>json_encode([$r['exam_center'],$r['room'],$r['exam_date']])) as $group){
                $base=$this->heading($group->first(),$settings,$type,$year);
                foreach($group->chunk(25) as $chunk){
                    $table=['widths'=>[7,24,6,14,25,8,9,7],
                        'headers'=>[array_map(fn($label)=>self::cell($label),['លេខតុ','នាមត្រកូល និងនាមខ្លួន','ភេទ','ថ្ងៃខែឆ្នាំកំណើត','អាគតដ្ឋាន','លទ្ធផល','និទ្ទេស','ផ្សេងៗ'])],'rows'=>[]];
                    foreach($chunk as $r){
                        $dob=preg_match('/^\d{4}-\d{2}-\d{2}$/',$r['dob'])?date('d-m-Y',strtotime($r['dob'])):$r['dob'];
                        $table['rows'][]=['outcome'=>$r['outcome'],'cells'=>[
                            self::cell((string)$r['seat'],$r['outcome']==='passed'?'seat-passed':''),self::cell($r['name_kh']?:$r['name_en'],'left'),
                            self::cell($r['female']?'ស្រី':(['male'=>'ប្រុស','m'=>'ប្រុស'][mb_strtolower(trim($r['gender']))]??$r['gender'])),
                            self::cell($dob),self::cell($r['school']?:$r['campus'],'left school'),
                            self::cell($r['result']??'មិនទាន់ពេញលេញ','result'),self::cell($r['letter_grade']??'','result'),
                            self::cell($r['outcome']==='absent'?($r['attendance_reason']?:'អវត្តមាន'):'','small'),
                        ]];
                    }
                    $totals=$this->counts($chunk);
                    $pages[]=$base+['header'=>true,'tables'=>[$table],'signature'=>true,'notes'=>[],
                        'footer'=>['បេក្ខជនក្នុងទំព័រនេះ៖ '.$totals['total'].' នាក់  ស្រី៖ '.$totals['female'].' នាក់',
                            'ជាប់៖ '.$totals['passed'].' នាក់  ស្រី៖ '.$totals['passed_female'].' នាក់',
                            'ធ្លាក់៖ '.$totals['failed'].' នាក់  អវត្តមាន៖ '.$totals['absent'].' នាក់  មិនទាន់ពេញលេញ៖ '.$totals['pending'].' នាក់']];
                }
            }
        }elseif($type==='subject'){
            // Keep all eleven subjects together; the sample has two center blocks per sheet.
            $tables=[];
            foreach($records->groupBy(fn($r)=>json_encode([$r['exam_center'],$r['language']])) as $group){
                $first=$group->first();
                $table=['caption'=>'មណ្ឌលប្រឡង៖ '.$first['exam_center'].' — ភាសាប្រឡង៖ '.($first['language']==='english'?'អង់គ្លេស':($first['language']==='khmer'?'ខ្មែរ':'មិនបានបញ្ជាក់')),
                    'widths'=>[7,25,9,8,9,8,12,9,8,12],
                    'headers'=>[
                        [self::cell('ល.រ','blue',1,2),self::cell('មុខវិជ្ជា','blue',1,2),self::cell('បេក្ខជន','blue',2),self::cell('ជាប់មធ្យមភាគ','pale-yellow',3),self::cell('ធ្លាក់មធ្យមភាគ','pale-orange',3)],
                        array_map(fn($v)=>self::cell($v),['សរុប','ស្រី','សរុប','ស្រី','%','សរុប','ស្រី','%']),
                    ],'rows'=>[]];
                foreach(G9ScoringReport::SUBJECTS as $field=>[$label,$max]){
                    $eligible=$group->filter(fn($r)=>$r['outcome']!=='absent'&&$r['language']!==null
                        &&!($field==='score_english'&&$r['language']==='english')
                        &&isset($r['scores'][$field])&&$r['scores'][$field]!==''&&is_numeric($r['scores'][$field])&&is_finite((float)$r['scores'][$field])&&(float)$r['scores'][$field]>=0);
                    $passed=$eligible->filter(fn($r)=>(float)$r['scores'][$field]>=(float)$max/2);
                    $failed=$eligible->filter(fn($r)=>(float)$r['scores'][$field]<(float)$max/2);
                    $table['rows'][]=['cells'=>array_map(fn($v)=>self::cell($v),[
                        count($table['rows'])+1,$label,$eligible->count(),$eligible->where('female',true)->count(),$passed->count(),$passed->where('female',true)->count(),
                        $this->rate($passed->count(),$eligible->count()),$failed->count(),$failed->where('female',true)->count(),$this->rate($failed->count(),$eligible->count()),
                    ])];
                    $table['rows'][array_key_last($table['rows'])]['cells'][1]['style']='left';
                    foreach([6,9] as $column)$table['rows'][array_key_last($table['rows'])]['cells'][$column]['style']='percent';
                }
                $tables[]=$table;
            }
            foreach(array_chunk($tables,2) as $i=>$chunk)$pages[]=$this->heading($records->first(),$settings,$type,$year)+[
                'header'=>$i===0,'tables'=>$chunk,'signature'=>false,'footer'=>[],
                'notes'=>['គិតតែបេក្ខជនមានវត្តមាន និងមានពិន្ទុមុខវិជ្ជានោះ។ ជាប់មធ្យមភាគ៖ ពិន្ទុ ≥ ៥០% នៃពិន្ទុអតិបរមា។',
                    'ភាសាបរទេសមិនរាប់សម្រាប់ភាសាប្រឡងអង់គ្លេស។ ពិន្ទុមិនទាន់បញ្ចូលមិនត្រូវបានរាប់ជាធ្លាក់។'],
            ];
        }else{
            $rows=[];
            if($type==='room'){
                foreach($records->groupBy('exam_center') as $center=>$centerRows){
                    $rows[]=['section'=>'មណ្ឌលប្រឡង៖ '.$center];
                    foreach($centerRows->groupBy(fn($r)=>json_encode([$r['room'],$r['language']])) as $group){
                        $first=$group->first();$label=$first['room'].($first['language']==='english'?' (EN)':($first['language']==='khmer'?' (KH)':''));
                        $rows[]=$this->gradeRow($label,$group);
                    }
                    $rows[]=$this->gradeRow('សរុប',$centerRows,true);
                }
                $rows[]=['section'=>'លទ្ធផលសរុប'];$rows[]=$this->gradeRow('សរុប',$records,true);
            }elseif($type==='wis'){
                foreach($records->groupBy('campus_id')->sortBy(fn($g)=>$g->first()['campus'],SORT_NATURAL) as $campus){
                    foreach($campus->groupBy('language') as $language=>$group)$rows[]=$this->gradeRow($group->first()['campus'].' / '.($language==='english'?'EN':($language==='khmer'?'KH':'—')),$group);
                    $rows[]=$this->gradeRow($campus->first()['campus'].' សរុប',$campus,true);
                }
                $rows[]=$this->gradeRow('WIS សរុប',$records,true);
            }elseif($type==='branch'){
                foreach($records->groupBy('campus_id')->sortBy(fn($g)=>$g->first()['campus'],SORT_NATURAL) as $campus){
                    $rows[]=['section'=>'សាខា៖ '.$campus->first()['campus']];
                    foreach($campus->groupBy('grade')->sortKeys(SORT_NATURAL) as $grade=>$group)$rows[]=$this->outcomeRow($grade?:'មិនបានបញ្ជាក់',$group);
                    $rows[]=$this->outcomeRow('សរុប',$campus,true);
                }
                $rows[]=$this->outcomeRow('សរុបគ្រប់សាខា',$records,true);
            }else{
                foreach($records->groupBy('exam_center') as $center=>$group)$rows[]=$this->outcomeRow($center?:'មិនបានបញ្ជាក់',$group);
                $rows[]=$this->outcomeRow('សរុប',$records,true);
            }
            $gradeTable=in_array($type,['room','wis'],true);
            foreach($this->chunkRows($rows,27) as $i=>$chunk){
                $table=$gradeTable?$this->gradeTable($type==='room'?'លេខបន្ទប់':'សាខា / ភាសា'):$this->outcomeTable($type==='branch'?'ថ្នាក់':'មណ្ឌលប្រឡង');
                $table['rows']=$chunk;
                $pages[]=$this->heading($records->first(),$settings,$type,$year)+['header'=>$i===0,'tables'=>[$table],'signature'=>$type==='doeys',
                    'footer'=>[],'notes'=>[$gradeTable?'ភាគរយនិទ្ទេសគិតធៀបនឹងបេក្ខជនមានវត្តមាន។ អវត្តមាន និងពិន្ទុមិនទាន់ពេញលេញមិនរាប់ជាធ្លាក់។':'ភាគរយជាប់ = ជាប់ ÷ (ជាប់ + ធ្លាក់)។ មិនរាប់អវត្តមាន និងពិន្ទុមិនទាន់ពេញលេញ។',
                        'បេក្ខជនមិនទាន់ពេញលេញ៖ '.$records->where('outcome','pending')->count().' នាក់  អវត្តមាន៖ '.$records->where('outcome','absent')->count().' នាក់'],
                ];
            }
        }
        foreach($pages as $i=>&$page){$page['page']=$i+1;$page['pages']=count($pages);}unset($page);
        return $pages;
    }

    private function heading(array $first,array $settings,string $type,string $year): array
    {
        $center=$first['exam_center'];$config=collect($settings['report_centers']??[])->firstWhere('exam_center',$center)??[];
        $effective=G9ExamCenterSettings::resolve($settings,$center);$date=$type==='doeys'?'resultDateDOEYS':'resultDate';
        $location=$config['location']??$settings['date_location']??'រាជធានីភ្នំពេញ';
        $solar=$effective[$date]??'';
        if(!empty($effective[$date.'_iso']))$solar=$location.' ថ្ងៃទី '.G9CandidateReport::khmerDate($effective[$date.'_iso']);
        return ['year'=>$year,'center'=>$center,'room'=>$first['room'],'location'=>G9CandidateReport::locationHeading($location),
            'department'=>$config['department']??'មន្ទីរអប់រំ យុវជន និងកីឡា','exam_date'=>G9CandidateReport::khmerDate($first['exam_date']?:null),
            'solar'=>$solar,'lunar'=>$effective[$type==='doeys'?'k_resultDateDOEYS':'k_resultDate']??'',
            'signer'=>$config['signer']??'ប្រធានមណ្ឌល','signer_name'=>$config['signer_name']??''];
    }

    private function counts(Collection $records): array
    {
        $counts=['total'=>$records->count(),'female'=>$records->where('female',true)->count()];
        foreach(['passed','failed','pending','absent'] as $key){$group=$records->where('outcome',$key);$counts[$key]=$group->count();$counts[$key.'_female']=$group->where('female',true)->count();}
        $counts['attended']=$counts['total']-$counts['absent'];$counts['attended_female']=$counts['female']-$counts['absent_female'];
        return $counts;
    }

    private function rate(int $value,int $total): ?float {return $total?round($value/$total,6):null;}

    private function gradeTable(string $label): array
    {
        return ['widths'=>[15,6,6,6,6,7,...array_fill(0,12,6)],'headers'=>[
            [self::cell($label,'blue',1,3),self::cell('បេក្ខជនសរុប','blue',2,2),self::cell('បេក្ខជនមានវត្តមាន','blue',3,2),self::cell('លទ្ធផលប្រឡង','pale-green',12)],
            array_map(fn($grade,$style)=>self::cell($grade,$style,3),self::GRADES,['yellow','purple','orange','red']),
            array_map(fn($label)=>self::cell($label),['សរុប','ស្រី','សរុប','ស្រី','%',...array_merge(...array_fill(0,4,['សរុប','ស្រី','%']))]),
        ],'rows'=>[]];
    }

    private function gradeRow(string $label,Collection $records,bool $total=false): array
    {
        $c=$this->counts($records);$cells=[self::cell($label,'left'),self::cell($c['total']),self::cell($c['female']),self::cell($c['attended']),self::cell($c['attended_female']),self::cell($this->rate($c['attended'],$c['total']),'percent')];
        foreach(self::GRADES as $i=>$grade){
            $group=$records->where('outcome','!=','absent')->where('letter_grade',$grade);$style=['yellow','purple','orange','red'][$i];
            array_push($cells,self::cell($group->count(),$style),self::cell($group->where('female',true)->count(),$style),self::cell($this->rate($group->count(),$c['attended']),$style.' percent'));
        }
        return ['cells'=>$cells,'total'=>$total];
    }

    private function outcomeTable(string $label): array
    {
        return ['widths'=>[24,...array_fill(0,12,6),9],'headers'=>[
            [self::cell($label,'blue',1,2),...array_map(fn($v)=>self::cell($v,'blue',2),['បេក្ខជន','មានវត្តមាន','ជាប់','ធ្លាក់','អវត្តមាន','មិនទាន់ពេញលេញ']),self::cell('% ជាប់','pale-green',1,2)],
            array_map(fn($v)=>self::cell($v),array_merge(...array_fill(0,6,['សរុប','ស្រី']))),
        ],'rows'=>[]];
    }

    private function outcomeRow(string $label,Collection $records,bool $total=false): array
    {
        $c=$this->counts($records);$cells=[self::cell($label,'left')];
        foreach(['total','attended','passed','failed','absent','pending'] as $key){$cells[]=self::cell($c[$key]);$cells[]=self::cell($c[$key==='total'?'female':$key.'_female']);}
        $cells[]=self::cell($this->rate($c['passed'],$c['passed']+$c['failed']),'percent');
        return ['cells'=>$cells,'total'=>$total];
    }

    /** Avoid orphan section headings and repeat their context on continuation sheets. */
    private function chunkRows(array $rows,int $limit): array
    {
        $chunks=[];$chunk=[];$section=null;
        foreach($rows as $row){
            if(count($chunk)>=($limit-(isset($row['section'])?1:0))){
                $chunks[]=$chunk;$chunk=[];
                if($section&&!isset($row['section']))$chunk[]=['section'=>$section.' (បន្ត)'];
            }
            if(isset($row['section']))$section=$row['section'];
            $chunk[]=$row;
        }
        if($chunk)$chunks[]=$chunk;return $chunks;
    }

    public function pdf(array $pages,string $type): string
    {
        $folder=storage_path('app/private/g9-report-pdf');if(!is_dir($folder))mkdir($folder,0755,true);
        $pdf=new Mpdf(['mode'=>'utf-8','format'=>'A4','tempDir'=>$folder,'margin_left'=>8,'margin_right'=>8,'margin_top'=>7,'margin_bottom'=>8,
            'fontDir'=>[...(new ConfigVariables)->getDefaults()['fontDir'],public_path('fonts/khmer')],
            'fontdata'=>(new FontVariables)->getDefaults()['fontdata']+['g9body'=>['R'=>'KhmerOSsiemreap.ttf','useOTL'=>0xFF],
                'g9heading'=>['R'=>'KhmerOSmuollight.ttf','useOTL'=>0xFF],'g9ornament'=>['R'=>'Tacteing.ttf']],
            'default_font'=>'g9body','default_font_size'=>8,'shrink_tables_to_fit'=>1]);
        $pdf->SetTitle(self::TITLES[$type]);
        $pdf->WriteHTML(preg_replace('/@page\s*\{[^}]*\}/','',file_get_contents(resource_path('css/pages/g9-result-print.css'))),\Mpdf\HTMLParserMode::HEADER_CSS);
        foreach($pages as $index=>$page){
            if($index)$pdf->AddPage();
            if($type==='list'){
                $widths=$page['tables'][0]['widths'];
                foreach($page['tables'][0]['rows'] as &$row)foreach($row['cells'] as $ci=>&$cell){
                    $base=$ci===4?8:9;$pdf->SetFont('g9body','',$base);
                    $available=194*$widths[$ci]/array_sum($widths)-1.6;
                    $cell['font_size']=round(max(5.5,min($base,$base*$available/max(.1,$pdf->GetStringWidth((string)$cell['value'])))),2);
                }unset($row,$cell);
            }
            $pdf->WriteHTML(view('g9-national-exams.result-report-page',['page'=>$page,'type'=>$type,'pdfExport'=>true])->render());
            if($type==='list'){
                $pdf->SetDrawColor(227,51,67);$pdf->SetLineWidth(.3);
                foreach($page['tables'][0]['rows'] as $ri=>$row){
                    $anchor=$pdf->internallink['result-row-'.$page['page'].'-'.$ri]??null;
                    if(!$anchor||$anchor['PAGE']!==$pdf->page)continue;
                    $y=$anchor['Y']+1.8;
                    if($row['outcome']==='passed')$pdf->Ellipse(14.8,$y,5.1,2.3);
                    elseif(in_array($row['outcome'],['failed','absent'],true))$pdf->Line(8,$y,202,$y);
                }
            }
        }
        return $pdf->Output('',\Mpdf\Output\Destination::STRING_RETURN);
    }

    public function xlsx(array $pages,string $type): string
    {
        return app(G9ResultWorkbook::class)->generate($pages,$type);
    }
}
