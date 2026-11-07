<?php

use App\Models\{G9ExamCandidate,G9ExamCertificateTracking,User};
use App\Services\{G9CertificateNumbers,G9CertificateTracking};
use App\Support\{G9ExamFields,G9NationalExamPermissions as Access};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Schema,Storage};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../Fixtures/G12NationalExam.php';

beforeEach(function(){
    createG12NationalSchema();
    Schema::create('tb_academic_year',function(Blueprint $t){$t->id();$t->string('academic_year');$t->softDeletes();});
    DB::table('tb_academic_year')->insert([['id'=>1,'academic_year'=>'2025-2026'],['id'=>2,'academic_year'=>'2024-2025']]);
    foreach(['2026_11_04_000002_create_g9_exam_candidates','2026_11_04_000004_preserve_g9_legacy_tracking',
        '2026_11_06_000002_add_g9_exam_attendance','2026_11_06_000003_add_g9_absence_reason','2026_11_07_000001_create_g9_certificate_tracking','2026_11_07_000002_create_g9_certificate_numbers'] as $migration)
        (require database_path('migrations/'.$migration.'.php'))->up();
    $this->numbers=app(G9CertificateNumbers::class);$this->tracking=app(G9CertificateTracking::class);$this->admin=User::find(1);g12Request($this->admin);
});

function g9NumberCandidate(string $code,string $name='កក',array $data=[],array $attributes=[]): G9ExamCandidate
{
    return G9ExamCandidate::create(array_replace(['academic_year_id'=>1,'campus_id'=>1,'student_code'=>$code,'name_en'=>'NUMBER STUDENT '.$code,'name_kh'=>$name,
        'data'=>array_replace(array_fill_keys(array_keys(G9ExamFields::GROUPS['National Examination Scores']),20),[
            'eoy_average'=>70,'score_math'=>80,'exam_language'=>'Khmer','exam_center'=>'មណ្ឌល ភ្នំពេញ','room_number'=>'1','seat_number'=>$code,'grade'=>'9A','gender'=>'Female'], $data)],$attributes));
}

function g9SaveNumberSettings($test,array $change=[]): array
{
    $config=$test->numbers->configuration($test->admin,1);
    foreach($config['centers'] as &$center){$center['region']=str_contains($center['exam_center'],'ខេត្ត')?'province':'pp';}unset($center);
    return $test->numbers->saveSettings($test->admin,1,array_replace($config,$change));
}

it('uses the ending academic year and exactly four Khmer sequence digits',function(){
    expect(G9CertificateNumbers::prefix('2024-2025'))->toBe('925')->and(G9CertificateNumbers::prefix('២០២៥-២០២៦'))->toBe('926');
    $candidate=g9NumberCandidate('1');$before=$candidate->fresh()->toJson();
    $preview=$this->numbers->preview($this->admin,1);
    expect($preview['rows'][0]['number'])->toBe('៩២៦០០០១')->and($preview['summary'])->toBe(['passed'=>1,'new'=>1,'existing'=>0,'excluded'=>0])
        ->and(DB::table('tb_g9_exam_certificate_number')->count())->toBe(0)->and(G9ExamCertificateTracking::count())->toBe(0);
    $result=$this->numbers->generate($this->admin,1,$preview['preview_token']);
    expect($result['generated'])->toBe(1)->and($candidate->fresh()->toJson())->toBe($before)
        ->and($this->tracking->state($candidate->fresh()))->toMatchArray(['number'=>'៩២៦០០០១','status'=>'awaiting']);
    expect(mb_strlen($this->tracking->state($candidate->fresh())['number']))->toBe(7);
    g9NumberCandidate('old-year','កក',[],['academic_year_id'=>2]);
    expect($this->numbers->preview($this->admin,2)['rows'][0]['number'])->toBe('៩២៥០០០១');
});

it('orders location language center and Khmer student names with stable ties',function(){
    $provinceKhmer=g9NumberCandidate('PK','ក',['exam_center'=>'មណ្ឌល ខេត្ត ក','exam_language'=>'Khmer']);
    $provinceEnglish=g9NumberCandidate('PE','ក',['exam_center'=>'មណ្ឌល ខេត្ត ក','exam_language'=>'English']);
    $ppEnglish=g9NumberCandidate('E','ក',['exam_language'=>'English']);
    $khmerSecond=g9NumberCandidate('KH2','ខ');$khmerFirst=g9NumberCandidate('KH1','ក');$khmerThird=g9NumberCandidate('KH3','គ');
    $preview=$this->numbers->preview($this->admin,1);
    expect(array_column($preview['rows'],'id'))->toBe([$khmerFirst->id,$khmerSecond->id,$khmerThird->id,$ppEnglish->id,$provinceEnglish->id,$provinceKhmer->id])
        ->and(array_column($preview['rows'],'number'))->toBe(['៩២៦០០០១','៩២៦០០០២','៩២៦០០០៣','៩២៦០០០៤','៩២៦០០០៥','៩២៦០០០៦']);
    g9SaveNumberSettings($this,['group_order'=>['province_english','pp_english','pp_khmer','province_khmer']]);
    expect($this->numbers->preview($this->admin,1)['rows'][0]['id'])->toBe($provinceEnglish->id);
});

it('preserves historical numbers including three-digit suffixes and continues after the highest reserved number',function(){
    $old=g9NumberCandidate('old','ក',[],['legacy_data'=>['certificate_number'=>'៩២៦៩៩៩','received_cert'=>'Withdrew','received_cert_Date'=>'2026-10-01','received_by'=>'Parent']]);
    $new=g9NumberCandidate('new','ខ');
    $preview=$this->numbers->preview($this->admin,1);
    expect($preview['rows'][0])->toMatchArray(['number'=>'៩២៦៩៩៩','is_new'=>false])->and($preview['rows'][1]['number'])->toBe('៩២៦១០០០');
    $this->numbers->generate($this->admin,1,$preview['preview_token']);
    $state=$this->tracking->state($old->fresh());expect($state)->toMatchArray(['number'=>'៩២៦៩៩៩','status'=>'received','received_date'=>'2026-10-01','recipient'=>'Parent','legacy'=>true]);
    $again=$this->numbers->preview($this->admin,1);expect($again['summary']['new'])->toBe(0);
    expect($this->numbers->generate($this->admin,1,$again['preview_token'])['generated'])->toBe(0);
    $later=g9NumberCandidate('later','កក');$next=$this->numbers->preview($this->admin,1);$row=collect($next['rows'])->firstWhere('id',$later->id);
    expect($row['number'])->toBe('៩២៦១០០១')->and($this->tracking->state($new->fresh())['number'])->toBe('៩២៦១០០០');
});

it('does not replace imported receipt history when assigning a missing number',function(){
    $candidate=g9NumberCandidate('legacy','ក',[],['legacy_data'=>['received_cert'=>'Withdrew','received_cert_Date'=>'2026-10-01','received_by'=>'Student']]);
    $preview=$this->numbers->preview($this->admin,1);$this->numbers->generate($this->admin,1,$preview['preview_token']);
    expect($this->tracking->state($candidate->fresh()))->toMatchArray(['number'=>'៩២៦០០០១','status'=>'received','received_date'=>'2026-10-01','recipient'=>'Student','legacy'=>true])
        ->and(G9ExamCertificateTracking::count())->toBe(0);
});

it('excludes failed absent and pending students from numbering issuance and both upload types',function(){
    g9NumberCandidate('passed');$failed=g9NumberCandidate('failed','ខ',['score_math'=>0,'eoy_average'=>0]);
    $absent=g9NumberCandidate('absent','គ',[],['attendance_status'=>'absent']);$pending=g9NumberCandidate('pending','ឃ',['score_math'=>null]);
    $preview=$this->numbers->preview($this->admin,1);
    expect($preview['summary'])->toMatchArray(['passed'=>1,'new'=>1,'excluded'=>3]);
    $this->numbers->generate($this->admin,1,$preview['preview_token']);
    foreach([$failed,$absent,$pending] as $candidate)foreach(['provisional','receipt'] as $kind){
        expect($this->tracking->summary($candidate->fresh(),$this->admin)['can_manage'])->toBeFalse();
        expect(fn()=>$this->tracking->save($this->admin,$candidate,['status'=>'printed_signed','version'=>0],[$kind=>UploadedFile::fake()->createWithContent('copy.pdf',"%PDF-1.4\n%%EOF")]))->toThrow(ValidationException::class);
    }
    expect(DB::table('tb_g9_exam_certificate_number')->count())->toBe(1)->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('requires a fresh server-issued preview after settings results or student details change',function(){
    $candidate=g9NumberCandidate('1');
    expect(fn()=>$this->numbers->generate($this->admin,1,'tampered'))->toThrow(ValidationException::class);
    $preview=$this->numbers->preview($this->admin,1);$candidate->update(['name_kh'=>'ខ']);
    expect(fn()=>$this->numbers->generate($this->admin,1,$preview['preview_token']))->toThrow(HttpException::class);
    $preview=$this->numbers->preview($this->admin,1);g9SaveNumberSettings($this,['starting_sequence'=>10]);
    expect(fn()=>$this->numbers->generate($this->admin,1,$preview['preview_token']))->toThrow(HttpException::class);
    $preview=$this->numbers->preview($this->admin,1);$candidate->update(['attendance_status'=>'absent']);
    expect(fn()=>$this->numbers->generate($this->admin,1,$preview['preview_token']))->toThrow(HttpException::class);
    expect(DB::table('tb_g9_exam_certificate_number')->count())->toBe(0);
});

it('rejects stale or invalid configuration and requires locations and Khmer names for eligible students',function(){
    $candidate=g9NumberCandidate('1','ក',['exam_center'=>'Unknown Center']);
    expect(fn()=>$this->numbers->preview($this->admin,1))->toThrow(ValidationException::class);
    $config=g9SaveNumberSettings($this);expect($config['version'])->toBe(1);
    expect(fn()=>$this->numbers->saveSettings($this->admin,1,array_replace($config,['version'=>0])))->toThrow(HttpException::class)
        ->and(fn()=>$this->numbers->saveSettings($this->admin,1,array_replace($config,['starting_sequence'=>10000])))->toThrow(ValidationException::class)
        ->and(fn()=>$this->numbers->saveSettings($this->admin,1,array_replace($config,['group_order'=>array_fill(0,4,'pp_khmer')])))->toThrow(ValidationException::class);
    $candidate->update(['name_kh'=>'']);expect(fn()=>$this->numbers->preview($this->admin,1))->toThrow(ValidationException::class);
});

it('keeps generated numbers fixed and aborts exhausted sequences without partial assignments',function(){
    g9NumberCandidate('1');g9SaveNumberSettings($this,['starting_sequence'=>9999]);$preview=$this->numbers->preview($this->admin,1);
    expect($preview['rows'][0]['number'])->toBe('៩២៦៩៩៩៩');$this->numbers->generate($this->admin,1,$preview['preview_token']);
    $candidate=G9ExamCandidate::first();
    expect(fn()=>$this->tracking->save($this->admin,$candidate,['status'=>'awaiting','number'=>'៩២៦០០០១','version'=>0],[]))->toThrow(ValidationException::class);
    g9NumberCandidate('2');expect(fn()=>$this->numbers->preview($this->admin,1))->toThrow(ValidationException::class);
    expect(DB::table('tb_g9_exam_certificate_number')->count())->toBe(1);
});

it('requires all-campus number permission and does not expose another campus through the preview',function(){
    g9NumberCandidate('local');g9NumberCandidate('private','ខ',[],['campus_id'=>2]);
    foreach(['view','certificates','certificates-number-manage'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    $user=User::find(2);g12Request($user);
    expect(fn()=>$this->numbers->configuration($user,1))->toThrow(HttpException::class)
        ->and(fn()=>$this->numbers->preview($user,1))->toThrow(HttpException::class);
    expect(Access::parent('certificates-number-manage'))->toBe('certificates');
});
