<?php

use App\Models\{G9ExamCandidate,G9ExamCertificateFile,G9ExamCertificateTracking,User};
use App\Services\{G9CertificateBackupImport,G9CertificateTracking};
use App\Support\{G9CertificateWorkbook,G9ExamFields};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema,Storage};

require_once __DIR__.'/../Fixtures/G12NationalExam.php';

beforeEach(function(){
    createG12NationalSchema();
    Schema::create('tb_academic_year',function(Blueprint $t){$t->id();$t->string('academic_year');$t->softDeletes();});
    DB::table('tb_academic_year')->insert(['id'=>1,'academic_year'=>'2025-2026']);
    foreach(['2026_11_04_000002_create_g9_exam_candidates','2026_11_04_000004_preserve_g9_legacy_tracking','2026_11_06_000002_add_g9_exam_attendance',
        '2026_11_06_000003_add_g9_absence_reason','2026_11_07_000001_create_g9_certificate_tracking','2026_11_07_000002_create_g9_certificate_numbers',
        '2026_11_07_000003_add_g9_certificate_import_provenance'] as $migration)(require database_path('migrations/'.$migration.'.php'))->up();
    $this->importer=app(G9CertificateBackupImport::class);$this->admin=User::find(1);
    $this->root=Storage::disk('local')->path('backup');
    foreach(['certificates','received_certificates'] as $folder)Storage::disk('local')->makeDirectory('backup/2025-2026/'.$folder);
});

function g9BackupCandidate(string $code='001',array $attributes=[]): G9ExamCandidate
{
    return G9ExamCandidate::create(array_replace(['academic_year_id'=>1,'campus_id'=>1,'student_code'=>$code,'name_kh'=>'សិស្ស '.$code,'name_en'=>'TEST '.$code,
        'data'=>array_replace(array_fill_keys(array_keys(G9ExamFields::GROUPS['National Examination Scores']),30),['eoy_average'=>70,'exam_language'=>'Khmer']),
        'legacy_data'=>['certificate_number'=>'៩២៦០០១']],$attributes));
}

function g9BackupRow(string $code='001',array $values=[]): array
{
    return array_replace(['_sheet'=>'2025-2026','_row'=>2,'academic_year'=>'2025-2026','stu_id'=>$code,'full_name_khmer'=>'សិស្ស '.$code,'certificate_number'=>'៩២៦០០១',
        'cert_name'=>'TEST_'.$code.'_Cert','cert_received_name'=>'TEST_'.$code.'_RCert','received_cert'=>'Withdrew','received_cert_Date'=>'2026-10-07',
        'received_by'=>'Student','certDate'=>'រាជធានីភ្នំពេញ ថ្ងៃទី០១ ខែធ្នូ ឆ្នាំ២០២៦'], $values);
}

it('imports literal old numbers and receipt history through the normal webp pipeline and reruns without duplicates',function(){
    $c=g9BackupCandidate()->fresh();$before=$c->getAttributes();$row=g9BackupRow();
    foreach(['certificates/TEST_001_Cert.jpg','received_certificates/TEST_001_RCert.jpg'] as $path)file_put_contents($this->root.'/2025-2026/'.$path,g12PhotoBytes());
    $sourceHash=hash_file('sha256',$this->root.'/2025-2026/certificates/TEST_001_Cert.jpg');
    $plan=$this->importer->plan($this->admin,[$row],$this->root,'workbook');
    expect($plan[0]['errors'])->toBe([])->and($this->importer->import($this->admin,$plan[0]))->toBe('imported');
    $tracking=G9ExamCertificateTracking::sole();
    expect($tracking->number)->toBe('៩២៦០០១')->and($tracking->status)->toBe('received')->and($tracking->received_date)->toBe('2026-10-07')
        ->and($tracking->import_source['certificate_date'])->toBe('2026-12-01')->and($tracking->printed_signed_date)->toBeNull()
        ->and($c->fresh()->getAttributes())->toBe($before)->and(G9ExamCertificateFile::count())->toBe(2)
        ->and(implode(' ',$tracking->import_source['warnings']))->toContain('receipt date is before');
    foreach(G9ExamCertificateFile::all() as $file){
        expect($file->mime)->toBe('image/webp')->and($file->size)->toBeLessThanOrEqual(819200)
            ->and(getimagesize(Storage::disk('local')->path($file->path))['mime'])->toBe('image/webp');
        Storage::disk('local')->assertExists($file->thumbnail_path);
    }
    expect(hash_file('sha256',$this->root.'/2025-2026/certificates/TEST_001_Cert.jpg'))->toBe($sourceHash);
    $again=$this->importer->plan($this->admin,[$row],$this->root,'workbook');
    expect($again[0]['errors'])->toBe([])->and($this->importer->import($this->admin,$again[0]))->toBe('unchanged')
        ->and(G9ExamCertificateFile::count())->toBe(2)->and(DB::table('tb_g9_exam_event')->count())->toBe(1);
});

it('retains incomplete historical receipt details with review flags without fabricating evidence or dates',function(){
    $c=g9BackupCandidate();$row=g9BackupRow('001',['received_cert_Date'=>'','received_by'=>'']);
    $plan=$this->importer->plan($this->admin,[$row],$this->root,'hash');$this->importer->import($this->admin,$plan[0]);
    $state=app(G9CertificateTracking::class)->state($c->fresh());
    expect($state)->toMatchArray(['status'=>'received','received_date'=>null,'recipient'=>null,'legacy'=>true,'needs_review'=>true])
        ->and(G9ExamCertificateFile::count())->toBe(0);
    expect(app(G9CertificateTracking::class)->summary($c->fresh(),$this->admin))->toMatchArray(['legacy_source_name'=>$row['cert_name'],'legacy_receipt_name'=>$row['cert_received_name']]);
});

it('blocks mismatched identities issued number changes and existing manual tracking',function(){
    $c=g9BackupCandidate();
    foreach([['full_name_khmer'=>'Different student'],['certificate_number'=>'9260001']] as $values){
        $p=$this->importer->plan($this->admin,[g9BackupRow('001',$values)],$this->root,'hash')[0];
        expect($p['errors'])->not->toBeEmpty()->and($this->importer->import($this->admin,$p))->toBe('blocked');
    }
    G9ExamCertificateTracking::create(['candidate_id'=>$c->id,'status'=>'awaiting','number'=>'៩២៦០០១','updated_by'=>1]);
    $p=$this->importer->plan($this->admin,[g9BackupRow()],$this->root,'hash')[0];
    expect($p['errors'])->not->toBeEmpty()->and($this->importer->import($this->admin,$p))->toBe('blocked');
});

it('never imports documents or issues tracking for failed absent or pending students',function(){
    foreach(['failed'=>['data'=>['exam_language'=>'Khmer','eoy_average'=>0]+array_fill_keys(array_keys(G9ExamFields::GROUPS['National Examination Scores']),0)],
        'absent'=>['attendance_status'=>'absent'],'pending'=>['data'=>[]]] as $outcome=>$attributes){
        g9BackupCandidate($outcome,$attributes);$row=g9BackupRow($outcome);
        file_put_contents($this->root.'/2025-2026/certificates/'.$row['cert_name'].'.jpg',g12PhotoBytes());
        $p=$this->importer->plan($this->admin,[$row],$this->root,'hash')[0];
        expect($p['outcome'])->toBe($outcome)->and($this->importer->import($this->admin,$p))->toBe('ineligible');
    }
    expect(G9ExamCertificateTracking::count())->toBe(0)->and(G9ExamCertificateFile::count())->toBe(0);
});

it('preserves duplicate historical numbers literally and flags them instead of renumbering',function(){
    g9BackupCandidate('001');g9BackupCandidate('002');
    $plans=$this->importer->plan($this->admin,[g9BackupRow('001'),g9BackupRow('002')],$this->root,'hash');
    foreach($plans as $p){expect(implode(' ',$p['warnings']))->toContain('Duplicate issued number');$this->importer->import($this->admin,$p);}
    expect(G9ExamCertificateTracking::pluck('number')->all())->toBe(['៩២៦០០១','៩២៦០០១']);
});

it('rolls back metadata and newly stored photos on corrupt input and rejects stale student data',function(){
    $c=g9BackupCandidate();$row=g9BackupRow();
    file_put_contents($this->root.'/2025-2026/certificates/'.$row['cert_name'].'.jpg',g12PhotoBytes());
    file_put_contents($this->root.'/2025-2026/received_certificates/'.$row['cert_received_name'].'.jpg','invalid image');
    $p=$this->importer->plan($this->admin,[$row],$this->root,'hash')[0];
    expect(fn()=>$this->importer->import($this->admin,$p))->toThrow(\Illuminate\Validation\ValidationException::class)
        ->and(G9ExamCertificateFile::count())->toBe(0)->and(G9ExamCertificateTracking::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('g9_national_exam_documents'))->toBe([]);
    $c->update(['name_kh'=>'Changed']);
    expect(fn()=>$this->importer->import($this->admin,$p))->toThrow(\RuntimeException::class,'changed since preview');
});

it('reads every worksheet without trimming or converting the issued number',function(){
    $path=Storage::disk('local')->path('literal.xlsx');$zip=new ZipArchive();$zip->open($path,ZipArchive::CREATE);
    $zip->addFromString('xl/workbook.xml','<workbook xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="2025-2026" r:id="r1"/><sheet name="2024-2025" r:id="r2"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels','<Relationships><Relationship Id="r1" Target="worksheets/sheet1.xml"/><Relationship Id="r2" Target="worksheets/sheet2.xml"/></Relationships>');
    foreach([1=>'2025-2026',2=>'2024-2025'] as $i=>$year){
        $r=g9BackupRow('001',['academic_year'=>$year,'certificate_number'=>' ៩២១០០១ ']);unset($r['_sheet'],$r['_row']);$xml='<worksheet><sheetData>';
        foreach([array_keys($r),array_values($r)] as $j=>$values){$xml.='<row r="'.($j+1).'">';foreach($values as $column=>$value)$xml.='<c r="'.chr(65+$column).($j+1).'" t="inlineStr"><is><t>'.htmlspecialchars($value,ENT_XML1).'</t></is></c>';$xml.='</row>';}
        $zip->addFromString('xl/worksheets/sheet'.$i.'.xml',$xml.'</sheetData></worksheet>');
    }
    $zip->close();$rows=app(G9CertificateWorkbook::class)->read($path);
    expect($rows)->toHaveCount(2)->and(array_column($rows,'certificate_number'))->toBe([' ៩២១០០១ ',' ៩២១០០១ ']);
});

it('rejects another student ID in a photo reference and accepts only a hashed individually reviewed correction',function(){
    g9BackupCandidate('002');$row=g9BackupRow('002',['cert_name'=>'TEST_001_Cert','received_cert'=>'Not Yet']);
    file_put_contents($this->root.'/2025-2026/certificates/TEST_001_Cert.jpg',g12PhotoBytes());
    $correct=$this->root.'/2025-2026/certificates/TEST_002_Cert.jpg';file_put_contents($correct,g12PhotoBytes(230));
    $p=$this->importer->plan($this->admin,[$row],$this->root,'hash')[0];
    expect($p['files'])->not->toHaveKey('provisional')->and(implode(' ',$p['warnings']))->toContain('another student ID');
    $map=['2025-2026|002'=>['provisional'=>['filename'=>'TEST_002_Cert.jpg','sha256'=>hash_file('sha256',$correct),'certificate_number'=>$row['certificate_number'],'reason'=>'Visually verified certificate number and birth date.']]];
    $p=$this->importer->plan($this->admin,[$row],$this->root,'hash',$map)[0];
    expect($p['files']['provisional']['path'])->toBe(realpath($correct))->and($p['import_source']['photo_corrections'])->toHaveKey('provisional');
    $this->importer->import($this->admin,$p);
    expect(G9ExamCertificateFile::sole()->sha256)->toBe(hash_file('sha256',$correct));
    $map['2025-2026|002']['provisional']['sha256']='wrong';
    expect(fn()=>$this->importer->plan($this->admin,[$row],$this->root,'hash',$map))->toThrow(\RuntimeException::class,'changed');
});

it('never replays an old document over a later manually corrected upload even when status and number agree',function(){
    $c=g9BackupCandidate();$row=g9BackupRow('001',['received_cert'=>'Not Yet']);
    file_put_contents($this->root.'/2025-2026/certificates/TEST_001_Cert.jpg',g12PhotoBytes());
    $p=$this->importer->plan($this->admin,[$row],$this->root,'hash')[0];$this->importer->import($this->admin,$p);
    app(G9CertificateTracking::class)->save($this->admin,$c->fresh(),['status'=>'printed_signed','number'=>'៩២៦០០១','version'=>1],
        ['provisional'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('corrected.jpg',g12PhotoBytes(200))]);
    $p=$this->importer->plan($this->admin,[$row],$this->root,'hash')[0];
    expect($this->importer->import($this->admin,$p))->toBe('blocked')->and(G9ExamCertificateFile::count())->toBe(2)
        ->and(G9ExamCertificateFile::latest('id')->first()->original_name)->toBe('corrected.jpg');
});
