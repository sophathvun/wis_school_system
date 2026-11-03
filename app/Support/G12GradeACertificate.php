<?php

namespace App\Support;

class G12GradeACertificate
{
    public static function assignNumbers(string $academicYear, array $campusIds): int
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($academicYear,$campusIds) {
            $records=\App\Models\G12ExamRecord::where('academic_year',$academicYear)->lockForUpdate()->get();
            $students=$records->filter(fn($record)=>strtoupper(trim($record->overall_grade?:($record->data['g12_letter_grade']??'')))==='A')
                ->sort(function($left,$right){
                    $leftScore=$left->data['g12_score_rank']??null;
                    $rightScore=$right->data['g12_score_rank']??null;
                    $rank=(is_numeric($rightScore)?(float)$rightScore:-1)<=>(is_numeric($leftScore)?(float)$leftScore:-1);
                    return $rank?:strcmp($left->name_kh,$right->name_kh)?:($left->id<=>$right->id);
                })->values();
            $prefix='GA12'.substr($academicYear,-2).'-';$updated=0;
            foreach($students as $index=>$record){
                if(!in_array((int)$record->campus_id,array_map('intval',$campusIds),true))continue;
                $number=$prefix.str_pad((string)($index+1),3,'0',STR_PAD_LEFT);
                $data=$record->data??[];
                if(($data['g12_lettera_certnumber']??'')===$number)continue;
                $data['g12_lettera_certnumber']=$number;$record->data=$data;$record->save();$updated++;
            }
            return $updated;
        });
    }

    public static function defaults(): array
    {
        $layout=ScholarshipCertificateLayout::defaults('exam');
        $changes=[
            'number'=>['text'=>'Cert. No.: {{certificate_number}}','x'=>42,'y'=>76,'width'=>126,'height'=>8,'size'=>11],
            'title'=>['text'=>'Certificate of Achievement','x'=>25,'y'=>98,'width'=>160,'height'=>17,'size'=>30,'font'=>'schcertold','color'=>'#ed1c24'],
            'presented'=>['text'=>'This certificate is proudly presented to','x'=>28,'y'=>116,'width'=>154,'height'=>10,'size'=>16,'font'=>'schcertmaiandra','color'=>'#b59b21'],
            'name'=>['text'=>'{{student_name}}','x'=>25,'y'=>129,'width'=>160,'height'=>13,'size'=>23,'font'=>'schcertsans','color'=>'#161568','bold'=>false],
            'body'=>['text'=>"In recognition of achieving Grade A in the\nNational Baccalaureate Examination\nconducted on {{exam_date}}\nAcademic Year {{academic_year}}.",'x'=>28,'y'=>144,'width'=>154,'height'=>36,'size'=>15],
            'date'=>['text'=>'Given in {{location}} on {{issue_date}}.','x'=>26,'y'=>185,'width'=>158,'height'=>14,'size'=>14],
            'signer_1'=>['text'=>"TE Laurent\nChairman",'x'=>45,'y'=>251,'width'=>62,'height'=>17,'size'=>14,'font'=>'schcertsans','bold'=>false],
            'signer_2'=>['visible'=>false], 'campus'=>['visible'=>false],
        ];
        foreach($changes as $key=>$values)$layout['fields'][$key]=array_replace($layout['fields'][$key],$values);
        $layout['issue_date']=now('Asia/Bangkok')->format('Y-m-d');
        return $layout;
    }

    public static function values(array $row): array
    {
        $examDate=$row['exam_date'];
        $examDate=strtr($examDate,array_combine(['០','១','២','៣','៤','៥','៦','៧','៨','៩'],range(0,9)));
        $examDate=str_replace(['មករា','កុម្ភៈ','មីនា','មេសា','ឧសភា','មិថុនា','កក្កដា','សីហា','កញ្ញា','តុលា','វិច្ឆិកា','ធ្នូ'],['January','February','March','April','May','June','July','August','September','October','November','December'],$examDate);
        $examDate=trim(str_replace(["\u{200B}","\u{00A0}"],' ',$examDate));
        try { if($examDate)$examDate=\Carbon\CarbonImmutable::parse($examDate)->format('d F Y'); } catch(\Throwable) {}
        return [
            'student_name'=>$row['name_en'],'student_name_kh'=>$row['name_kh'],
            'certificate_number'=>$row['certificate_number'],'academic_year'=>$row['academic_year'],
            'campus'=>$row['campus'],'grade'=>'12','class'=>$row['grade'],
            'exam_date'=>$examDate,'score_rank'=>$row['score_rank'],
            'issue_date'=>now('Asia/Bangkok')->format('jS \d\a\y \o\f F Y'),'location'=>'Phnom Penh',
        ];
    }
}
