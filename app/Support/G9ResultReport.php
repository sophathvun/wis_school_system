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
        'subject'=>'ស្ថិតិជាប់ធ្លាក់តាមមុខវិជ្ជា',
        'doeys'=>'ស្ថិតិលទ្ធផលប្រឡង (DoEYS)',
        'branch'=>'ស្ថិតិលទ្ធផលប្រឡង (សាខា)',
        'wis'=>'ស្ថិតិលទ្ធផលប្រឡងសញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិ',
    ];
    public const GRADES=['ល្អ','ល្អបង្គួរ','មធ្យម','ធ្លាក់'];
    public const DOEYS_TITLE='បញ្ជីលទ្ធផលប្រឡងសញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិ (សាលាឯកជនប្រឡងដោយឡែកមានច្បាប់អនុញ្ញាត)';

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
                    $table=['widths'=>[6.6,23.8,5.6,13.6,24,7.7,7.6,11.1],
                        'headers'=>[array_map(fn($label)=>self::cell($label),['លេខតុ','នាមត្រកូល និងនាមខ្លួន','ភេទ','ថ្ងៃខែឆ្នាំកំណើត','អាគតដ្ឋាន','លទ្ធផល','និទ្ទេស','ផ្សេងៗ'])],'rows'=>[]];
                    foreach($chunk as $r){
                        $dob=preg_match('/^\d{4}-\d{2}-\d{2}$/',$r['dob'])?date('d-m-Y',strtotime($r['dob'])):$r['dob'];
                        $table['rows'][]=['outcome'=>$r['outcome'],'cells'=>[
                            self::cell((string)$r['seat'],$r['outcome']==='passed'?'seat-passed':''),self::cell($r['name_kh']?:$r['name_en'],'left'),
                            self::cell($r['female']?'ស្រី':(['male'=>'ប្រុស','m'=>'ប្រុស'][mb_strtolower(trim($r['gender']))]??$r['gender'])),
                            self::cell($dob),self::cell(preg_replace('/^វិទ្យាល័យ\s*/u','វិ. ',$r['school']?:$r['campus']),'left school'),
                            self::cell($r['outcome']==='absent'?'ធ្លាក់':($r['result']??'មិនទាន់ពេញលេញ'),'result'),self::cell($r['outcome']==='passed'?($r['letter_grade']??''):'','result'),
                            self::cell($r['outcome']==='absent'?($r['attendance_reason']?:'អវត្តមាន'):'','small'),
                        ]];
                    }
                    $totals=$this->counts($chunk);
                    $pages[]=$base+['header'=>true,'tables'=>[$table],'signature'=>true,'notes'=>[],'list_totals'=>$totals,
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
                $table=['caption'=>'មណ្ឌលប្រឡង៖ '.$first['exam_center'],
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
                        $row=$this->gradeRow((string)$group->first()['room'],$group,false,true);
                        $row['cells'][0]['style']='';
                        $rows[]=$row;
                    }
                    $rows[]=$this->gradeRow('សរុប',$centerRows,true,true);
                }
                $rows[]=['section'=>'លទ្ធផលសរុប'];$rows[]=$this->gradeRow('សរុប',$records,true,true);
            }elseif($type==='wis'){
                foreach($records->groupBy('exam_center') as $center=>$centerRows){
                    $rows[]=['section'=>'មណ្ឌលប្រឡង៖ '.($center?:'មិនបានបញ្ជាក់')];
                    foreach($centerRows->groupBy('campus_id')->sortBy(fn($g)=>$g->first()['campus'],SORT_NATURAL) as $campus){
                        $rows[]=$this->gradeRow($campus->first()['campus'],$campus,false,true);
                    }
                    $rows[]=$this->gradeRow('សរុបមណ្ឌល',$centerRows,true,true);
                }
                $rows[]=$this->gradeRow('សរុបរួម',$records,true,true)+['grand_total'=>true];
            }elseif($type==='branch'){
                foreach($records->groupBy('campus_id')->sortBy(fn($g)=>$g->first()['campus'],SORT_NATURAL) as $campus){
                    $rows[]=$this->doeysRow($campus->first()['campus'],$campus);
                }
                $rows[]=$this->doeysRow('សរុបគ្រប់សាខា',$records,true);
            }else{
                foreach($records->groupBy('exam_center') as $center=>$group)$rows[]=$this->doeysRow($center?:'មិនបានបញ្ជាក់',$group);
                $rows[]=$this->doeysRow('សរុបរួម',$records,true);
            }
            $gradeTable=in_array($type,['room','wis'],true);
            $chunks=$this->chunkRows($rows,$type==='room'?41:(in_array($type,['doeys','branch'],true)?13:27),$type==='room'?28:(in_array($type,['doeys','branch'],true)?7:null));
            foreach($chunks as $i=>$chunk){
                $table=in_array($type,['doeys','branch'],true)?$this->doeysTable():($gradeTable?$this->gradeTable($type==='room'?"បន្ទប់\nលេខ":'សាខា / ភាសា'):$this->outcomeTable('ថ្នាក់'));
                if($type==='room'){
                    $table['widths'][0]=8;
                    $table['headers'][1][3]['value']='ខ្សោយ';
                    $table['headers'][1][3]['style']='weak-header';
                }
                if($type==='branch')$table['headers'][0][0]['value']='សាខា';
                if($type==='wis'){
                    $table['headers'][0][0]['value']='សាខា';
                    $table['headers'][0][2]['value']='បេក្ខជនមកប្រឡង';
                }
                $table['rows']=$chunk;
                $pages[]=$this->heading($records->first(),$settings,$type,$year)+['header'=>$i===0,'tables'=>[$table],'signature'=>in_array($type,['doeys','branch'],true)&&$i===count($chunks)-1,
                    'summary_cards'=>$type==='room'&&$i===0?$this->summaryCards($records):[],
                    'academic_office'=>$type==='wis'&&$i===count($chunks)-1,
                    'footer'=>[],'notes'=>$type==='wis'?($i===count($chunks)-1?['អវត្តមាន៖ '.$records->where('outcome','absent')->count().' នាក់']:[]):(in_array($type,['room','doeys','branch'],true)?[]:[$gradeTable?'ភាគរយនិទ្ទេសគិតធៀបនឹងបេក្ខជនមានវត្តមាន។ អវត្តមាន និងពិន្ទុមិនទាន់ពេញលេញមិនរាប់ជាធ្លាក់។':'ភាគរយជាប់ = ជាប់ ÷ (ជាប់ + ធ្លាក់)។ មិនរាប់អវត្តមាន និងពិន្ទុមិនទាន់ពេញលេញ។',
                        'បេក្ខជនមិនទាន់ពេញលេញ៖ '.$records->where('outcome','pending')->count().' នាក់  អវត្តមាន៖ '.$records->where('outcome','absent')->count().' នាក់']),
                ];
            }
        }
        foreach($pages as $i=>&$page){$page['page']=$i+1;$page['pages']=count($pages);}unset($page);
        return $pages;
    }

    private function heading(array $first,array $settings,string $type,string $year): array
    {
        $center=$first['exam_center'];$config=collect($settings['report_centers']??[])->firstWhere('exam_center',$center)??[];
        $effective=G9ExamCenterSettings::resolve($settings,$center);$date='resultDate';
        $location=$this->compactLocation($config['location']??$settings['date_location']??'រាជធានីភ្នំពេញ');
        $solar=$this->compactLocation($effective[$date]??'');
        if(trim($solar)===''&&!empty($effective[$date.'_iso']))$solar=$this->solarDate($location,$effective[$date.'_iso']);
        return ['year'=>$year,'center'=>in_array($type,['doeys','branch'],true)?preg_replace('/^មណ្ឌលប្រឡង[\s៖:]*/u','',$center):$center,'room'=>$first['room'],'location'=>$location==='ភ្នំពេញ'?'រាជធានីភ្នំពេញ':$location,
            'department'=>$config['department']??'មន្ទីរអប់រំ យុវជន និងកីឡា','exam_date'=>G9CandidateReport::khmerDate($first['exam_date']?:null),
            'solar'=>$solar,'lunar'=>$effective['k_resultDate']??'',
            'office_date'=>!empty($effective['resultDate_iso'])?date('F j, Y',strtotime($effective['resultDate_iso'])):'',
            'doeys_signature_titles'=>str_contains($location,'ភ្នំពេញ')||preg_match('/phnom\s*penh/i',$location)?['ជ.នាយកសាលា','នាយករង']:['នាយកសាលា'],
            'prepared_by'=>$effective['score_result_by']??'','verified_by'=>$effective['score_last_verify_by']??'',
            'approval_lunar'=>$effective['k_resultDateDOEYS']??'',
            'approval_solar'=>trim($effective['resultDateDOEYS']??'')!==''?$this->compactLocation($effective['resultDateDOEYS']):
                (!empty($effective['resultDateDOEYS_iso'])?$this->solarDate($location,$effective['resultDateDOEYS_iso']):''),
            'signer'=>$config['signer']??'ប្រធានមណ្ឌល','signer_name'=>$config['signer_name']??''];
    }

    private function compactLocation(string $text): string
    {
        return preg_replace('/^(រាជធានី|ខេត្ត)[\s\x{200B}\x{FEFF}]*[:៖]?[\s\x{200B}\x{FEFF}]*/u','$1',trim($text));
    }

    private function summaryCards(Collection $records): array
    {
        $cards=[];
        foreach(['total'=>'Total Candidates','passed'=>'Passed','failed'=>'Failed'] as $outcome=>$label){
            $group=$outcome==='total'?$records:($outcome==='failed'?$records->whereIn('outcome',['failed','absent']):$records->where('outcome',$outcome));
            $languages=[];
            foreach(['english'=>'English','khmer'=>'Khmer'] as $language=>$name){
                $total=$group->where('language',$language)->count();
                $candidates=$records->where('language',$language)->count();
                $languages[]=['label'=>$name,'total'=>$total,'percentage'=>$outcome==='total'?null:$this->rate($total,$candidates)];
            }
            $cards[]=['key'=>$outcome,'label'=>$label,'total'=>$group->count(),'languages'=>$languages];
        }
        return $cards;
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

    private function gradeRow(string $label,Collection $records,bool $total=false,bool $includeAbsentAsFailed=false): array
    {
        $c=$this->counts($records);$cells=[self::cell($label,'left'),self::cell($c['total']),self::cell($c['female']),self::cell($c['attended']),self::cell($c['attended_female']),self::cell($this->rate($c['attended'],$c['total']),'percent')];
        foreach(self::GRADES as $i=>$grade){
            $group=$records->where('outcome','!=','absent')->where('letter_grade',$grade);$style=['yellow','purple','orange','red'][$i];
            if($includeAbsentAsFailed&&$grade==='ធ្លាក់')$group=$group->concat($records->where('outcome','absent'));
            array_push($cells,self::cell($group->count(),$style),self::cell($group->where('female',true)->count(),$style),self::cell($this->rate($group->count(),$c[$includeAbsentAsFailed?'total':'attended']),$style.' percent'));
        }
        return ['cells'=>$cells,'total'=>$total];
    }

    private function doeysTable(): array
    {
        return ['widths'=>[39,8,11,11,9,9,12,12,10,10,12,10,10,12,10,10,12,11,12,11,12,27],'headers'=>[
            [self::cell('មណ្ឌលប្រឡង','',1,3),self::cell("ចំនួន\nបន្ទប់",'',1,3),self::cell("បេក្ខជន\nចុះឈ្មោះប្រឡង",'',2,1),self::cell('បេក្ខជនអវត្តមាន','',4,1),self::cell('បេក្ខជនប្រឡងជាប់','',13,1),self::cell('សម័យប្រឡង','',1,3)],
            [self::cell('សរុប','',1,2),self::cell('ស្រី','',1,2),self::cell('សរុប','',1,2),self::cell('ស្រី','',1,2),self::cell('សរុប%','doeys-absence',1,2),self::cell('ស្រី%','doeys-absence',1,2),
                self::cell('និទ្ទេសល្អ','doeys-good',3),self::cell('និទ្ទេសល្អបង្គួរ','doeys-fair',3),self::cell('និទ្ទេសមធ្យម','doeys-average',3),
                self::cell('សរុបរួម','',1,2),self::cell("ភាគរយ\nសរុប%",'',1,2),self::cell("សរុប\nស្រី",'',1,2),self::cell("ភាគរយ\nសរុបស្រី%",'',1,2)],
            array_map(fn($v)=>self::cell($v),['សរុប','ស្រី','%សរុប','សរុប','ស្រី','%សរុប','សរុប','ស្រី','%សរុប']),
        ],'rows'=>[]];
    }

    private function doeysRow(string $label,Collection $records,bool $total=false): array
    {
        $c=$this->counts($records);
        $rooms=$records->filter(fn($r)=>trim((string)$r['room'])!=='')->unique(fn($r)=>json_encode([$r['exam_center'],$r['room']]))->count();
        $cells=[self::cell(preg_replace('/^មណ្ឌលប្រឡង[\s៖:]*/u','',$label),'left'),self::cell($rooms),self::cell($c['total']),self::cell($c['female']),self::cell($c['absent']),self::cell($c['absent_female']),
            self::cell($this->rate($c['absent'],$c['total']),'percent'),self::cell($this->rate($c['absent_female'],$c['female']),'percent')];
        foreach(array_slice(self::GRADES,0,3) as $grade){
            $group=$records->where('outcome','passed')->where('letter_grade',$grade);
            array_push($cells,self::cell($group->count()),self::cell($group->where('female',true)->count()),self::cell($this->rate($group->count(),$c['attended']),'percent'));
        }
        array_push($cells,self::cell($c['passed']),self::cell($this->rate($c['passed'],$c['attended']),'percent'),self::cell($c['passed_female']),
            self::cell($this->rate($c['passed_female'],$c['attended_female']),'percent'),
            self::cell($records->pluck('exam_date')->filter()->unique()->map(fn($date)=>G9CandidateReport::khmerDate($date))->implode(' / '),'exam-date'));
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
    private function chunkRows(array $rows,int $limit,?int $firstLimit=null): array
    {
        $chunks=[];$chunk=[];$section=null;
        foreach($rows as $row){
            if(count($chunk)>=(($chunks?$limit:($firstLimit??$limit))-(isset($row['section'])?1:0))){
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
        $left=$type==='list'?5.5:8;$pageWidth=210-2*$left;
        $pdf=new Mpdf(['mode'=>'utf-8','format'=>'A4','orientation'=>in_array($type,['doeys','branch'],true)?'L':'P','tempDir'=>$folder,'margin_left'=>$left,'margin_right'=>$left,'margin_top'=>7,'margin_bottom'=>$type==='list'?6:8,
            'fontDir'=>[...(new ConfigVariables)->getDefaults()['fontDir'],public_path('fonts/khmer')],
            'fontdata'=>(new FontVariables)->getDefaults()['fontdata']+['g9body'=>['R'=>'KhmerOSsiemreap.ttf','useOTL'=>0xFF],
                'g9heading'=>['R'=>'KhmerOSmuollight.ttf','useOTL'=>0xFF],'g9ornament'=>['R'=>'Tacteing.ttf']],
            'default_font'=>'g9body','default_font_size'=>8,'shrink_tables_to_fit'=>1]);
        $pdf->SetTitle(self::TITLES[$type]);
        $css=preg_replace('/@page(?:\s+[\w-]+)?\s*\{[^}]*\}/','',file_get_contents(resource_path('css/pages/g9-result-print.css')));
        $pdf->WriteHTML(str_replace(['page:result-list-page;','page:result-doeys-page;'],'',$css),\Mpdf\HTMLParserMode::HEADER_CSS);
        foreach($pages as $index=>$page){
            if($index)$pdf->AddPage();
            if($type==='wis'){
                $widths=$page['tables'][0]['widths'];
                foreach($page['tables'][0]['rows'] as &$row){
                    if(!isset($row['cells']))continue;
                    foreach($row['cells'] as $ci=>&$cell){
                        $pdf->SetFont('g9body',!empty($row['total'])?'B':'',7.5);
                        $text=str_contains($cell['style'],'percent')?($cell['value']===null?'—':number_format($cell['value']*100,2).'%'):(string)$cell['value'];
                        $available=$pageWidth*$widths[$ci]/array_sum($widths)-1.2;
                        $cell['font_size']=round(max(5.5,min(7.5,7.5*$available/max(.1,$pdf->GetStringWidth($text)))),2);
                    }unset($cell);
                }unset($row);
            }
            if($type==='list'){
                $widths=$page['tables'][0]['widths'];
                foreach($page['tables'][0]['rows'] as &$row)foreach($row['cells'] as $ci=>&$cell){
                    $base=$ci===4?8.5:9.5;$pdf->SetFont('g9body','',$base);
                    $available=$pageWidth*$widths[$ci]/array_sum($widths)-1.2;
                    $cell['font_size']=round(max(5.5,min($base,$base*$available/max(.1,$pdf->GetStringWidth((string)$cell['value'])))),2);
                }unset($row,$cell);
                $this->listPdfHeader($pdf,$page);$pdf->SetY(48);$page['header']=false;
            }
            $pdf->WriteHTML('<div class="result-'.$type.($type==='branch'?' result-doeys':'').'">'.view('g9-national-exams.result-report-page',['page'=>$page,'type'=>$type,'pdfExport'=>true])->render().'</div>');
            if($type==='list'){
                $pdf->SetDrawColor(227,51,67);$pdf->SetLineWidth(.3);
                foreach($page['tables'][0]['rows'] as $ri=>$row){
                    $anchor=$pdf->internallink['result-row-'.$page['page'].'-'.$ri]??null;
                    if(!$anchor||$anchor['PAGE']!==$pdf->page)continue;
                    $y=$anchor['Y']+2.4;
                    if($row['outcome']==='passed')$pdf->Ellipse($left+$pageWidth*$widths[0]/array_sum($widths)/2,$y,5.8,2.4);
                    elseif(in_array($row['outcome'],['failed','absent'],true))$pdf->Line($left,$y,210-$left,$y);
                }
            }
        }
        return $pdf->Output('',\Mpdf\Output\Destination::STRING_RETURN);
    }

    public function xlsx(array $pages,string $type): string
    {
        return app(G9ResultWorkbook::class)->generate($pages,$type);
    }

    /** Fixed A4 header coordinates keep mPDF aligned with the browser's positioned header. */
    private function listPdfHeader(Mpdf $pdf,array $page): void
    {
        $draw=function(string $html,float $x,float $y,float $width,float $height,string $style='')use($pdf){
            $pdf->WriteFixedPosHTML('<div style="font-family:g9heading;font-size:9.5pt;line-height:1.8;'.$style.'">'.$html.'</div>',$x,$y,$width,$height,'hidden');
        };
        $draw('ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ',5.5,9.5,199,17,'text-align:center;font-size:12pt;line-height:1.65');
        $pdf->WriteFixedPosHTML('<div style="line-height:0">'.file_get_contents(resource_path('svg/g9-result-ornament.svg')).'</div>',94,23,22,3,'hidden');
        $draw(e($page['location']).'<br>មណ្ឌលប្រឡង៖ '.e($page['center']),5.5,19,199,15);
        $draw('បន្ទប់លេខ៖ '.G9CandidateReport::roomHeading((string)$page['room']),5.5,31.5,29,9,'font-size:10.5pt');
        $draw('បញ្ជីរាយនាមលទ្ធផលប្រឡងសញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិ<br>សម័យប្រឡង៖ '.e($page['exam_date']),34.5,34,165,14,'text-align:center;font-size:10pt');
    }

    private function solarDate(string $location,string $iso): string
    {
        [$day,$month,$year]=explode(' ',G9CandidateReport::khmerDate($iso));
        return $location.' ថ្ងៃទី'.$day.' ខែ'.$month.' ឆ្នាំ'.$year;
    }
}
