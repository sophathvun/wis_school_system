<?php

// Synthetic report preview; reads Branding only and does not change application records.
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$count=max(0,(int)($argv[1]??75));
$campusReport=($argv[2]??'')==='campus';
$rows=[];
foreach(range(1,max(1,$count)) as $i){
    if(!$count)break;
    $rows[]=['number'=>$i,'name_en'=>$i%4===0?'KONG HENG RATTANACLIT':'SAMPLE STUDENT '.str_pad((string)$i,2,'0',STR_PAD_LEFT),'name_kh'=>$i%5===0?'គីម ឃុនសុសេនីវត្តា':'ចាន់ សុភា','id'=>(string)(9000000+$i),'gender'=>$i%2?'M':'F','grade'=>min(12,intdiv($i-1,7)+1).'A','group'=>'III','rank'=>($i-1)%3+1,'campus'=>$i<60?'BCH':'BKK'];
}
$export=app(\App\Support\ScholarshipExport::class);
$data=['pages'=>$export->rankPages($rows),'logoSrc'=>$export->rankLogo(),'year'=>'2025-2026','campusLabel'=>$campusReport?'BCH Campus':'All Campuses','rankSummary'=>['total'=>$count],'preparedBy'=>'SAMPLE REGISTRAR','reportDate'=>'October 15, 2026','pdfExport'=>false];
$data['reportSections']=[['pages'=>$data['pages'],'campus'=>'','total'=>$count,'requiresVerifier'=>true,'preparedBy'=>'SAMPLE REGISTRAR','preparedPosition'=>'Registrar','preparedDate'=>'October 2, 2026','checkedBy'=>'SAMPLE VERIFIER','checkedPosition'=>'Academic Director','checkedDate'=>'October 6, 2026','approvedBy'=>'SAMPLE DIRECTOR','approvedPosition'=>'Vice President','approvedDate'=>'October 9, 2026']];
$data['reportSections'][0]['requiresVerifier']=!$campusReport;
$reportFile='scholarship-outstanding-'.($campusReport?'campus-':'').$count;
$output=storage_path('app/testing');
if(!is_dir($output))mkdir($output,0775,true);
file_put_contents($output.'/'.$reportFile.'.html',view('western-scholarship.ranks-print',$data)->render());
$data['pdfExport']=true;
file_put_contents($output.'/'.$reportFile.'.pdf',$export->ranksPdf($data));
file_put_contents($output.'/'.$reportFile.'.xlsx',$export->ranksXlsx($data));
echo json_encode(['students'=>$count,'pages'=>array_map('count',$data['pages']),'logo'=>(bool)$data['logoSrc']]);
