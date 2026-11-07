<?php

use App\Http\Controllers\G9CertificateTrackingController;
use App\Models\{G9ExamCandidate,G9ExamCertificateFile,G9ExamCertificateTracking,User};
use App\Services\G9CertificateTracking;
use App\Support\{G9ExamFields,G9NationalExamPermissions as Access};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Blade,DB,Schema,Storage};
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
    $this->tracking=app(G9CertificateTracking::class);$this->controller=app(G9CertificateTrackingController::class);
    $this->admin=User::find(1);
});

function g9TrackedCandidate(string $code='001',array $data=[],array $attributes=[]): G9ExamCandidate
{
    return G9ExamCandidate::create(array_replace(['academic_year_id'=>1,'campus_id'=>1,'student_code'=>$code,'name_en'=>'TRACKING STUDENT '.$code,'name_kh'=>'សិស្សប្រឡង '.$code,
        'data'=>array_replace(array_fill_keys(array_keys(G9ExamFields::GROUPS['National Examination Scores']),20),[
            'eoy_average'=>70,'score_math'=>80,'exam_language'=>'Khmer','exam_center'=>'មណ្ឌលប្រឡង ភ្នំពេញថ្មី','room_number'=>'1','seat_number'=>$code,'grade'=>'9A','gender'=>'Female','exam_date'=>'2026-08-04'], $data)],$attributes));
}

function g9TrackingInput(array $overrides=[]): array
{
    return array_replace(['status'=>'printed_signed','number'=>'G926-001','printed_signed_date'=>'2026-10-01','received_date'=>'','recipient'=>'','version'=>0],$overrides);
}

function g9TrackingUpload(string $name='signed-copy.pdf',string $text='signed-copy'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name,"%PDF-1.4\n% ".$text."\n%%EOF\n");
}

function g9TrackingUser(array $actions): User
{
    foreach($actions as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    return User::find(2);
}

it('renders G12-style G9 provisional and student receipt columns without a diploma workflow',function(){
    g9TrackedCandidate();$view=$this->controller->index(g12Request($this->admin));
    preg_match("/@section\('content'\)(.*)@endsection/s",file_get_contents($view->getPath()),$match);
    $html=Blade::render(preg_replace('/^@vite.*$/m','',$match[1]),$view->getData());
    expect($html)->toContain('g12-certificate-table','g12-certificate-upload-card','Provisional Certificate','Certificate Received by Student','Printed &amp; Signed')
        ->not->toContain('Diploma','MoEYS Copy');
    expect($this->controller->data(g12Request($this->admin,['academic_year'=>1]),$this->tracking)->getData(true)['records']['data'][0]['tracking'])->toMatchArray(['status'=>'awaiting','version'=>0]);
    expect(G9ExamCertificateTracking::count())->toBe(0)->and(DB::table('tb_g9_exam_event')->count())->toBe(0);
});

it('saves printed signed and student receipt details with protected evidence and an audit trail',function(){
    $candidate=g9TrackedCandidate()->fresh();$original=$candidate->toJson();
    $this->tracking->save($this->admin,$candidate,g9TrackingInput(),['provisional'=>g9TrackingUpload('certificate.pdf')]);
    expect(G9ExamCertificateTracking::sole()->only(['status','printed_signed_date','version']))->toBe(['status'=>'printed_signed','printed_signed_date'=>'2026-10-01','version'=>1]);
    $this->tracking->save($this->admin,$candidate,g9TrackingInput(['status'=>'received','received_date'=>'2026-10-03','recipient'=>'Student','version'=>1]),['receipt'=>g9TrackingUpload()]);
    $saved=G9ExamCertificateTracking::sole();$files=G9ExamCertificateFile::all();
    expect($saved->only(['status','received_date','recipient','version']))->toBe(['status'=>'received','received_date'=>'2026-10-03','recipient'=>'Student','version'=>2])
        ->and($files)->toHaveCount(2)->and($candidate->fresh()->toJson())->toBe($original)
        ->and(DB::table('tb_g9_exam_event')->count())->toBe(2);
    foreach($files as $file)Storage::disk('local')->assertExists($file->path);
    $shown=$this->controller->show(g12Request($this->admin),$candidate,$this->tracking)->getData(true);
    expect($shown['documents'])->toHaveKeys(['provisional','receipt'])->and($shown['document_history'])->toHaveCount(2)
        ->and(json_encode($shown))->not->toContain('g9_national_exam_documents');
    $response=$this->controller->file(g12Request($this->admin,['download'=>1]),$files[0],$this->tracking);
    expect($response->headers->get('Content-Type'))->toBe('application/pdf')->and($response->headers->get('Content-Disposition'))->toContain('attachment');
});

it('rejects missing receipt evidence invalid date order blank recipients and stale edits without partial writes',function(){
    $candidate=g9TrackedCandidate();
    foreach([
        ['status'=>'received','received_date'=>'2026-10-03','recipient'=>'Student'],
        ['status'=>'received','received_date'=>'2026-09-30','recipient'=>'Student'],
        ['status'=>'received','received_date'=>'2026-10-03','recipient'=>''],
        ['status'=>'printed_signed','printed_signed_date'=>'2026-99-99'],
        ['status'=>'awaiting','printed_signed_date'=>'2026-10-01'],
        ['status'=>'printed_signed','received_date'=>'2026-10-03'],
        ['status'=>'diploma'],
    ] as $input)expect(fn()=>$this->tracking->save($this->admin,$candidate,g9TrackingInput($input),[]))->toThrow(ValidationException::class);
    expect(G9ExamCertificateTracking::count())->toBe(0)->and(G9ExamCertificateFile::count())->toBe(0)->and(DB::table('tb_g9_exam_event')->count())->toBe(0);
    $this->tracking->save($this->admin,$candidate,g9TrackingInput(),[]);
    expect(fn()=>$this->tracking->save($this->admin,$candidate,g9TrackingInput(),['receipt'=>g9TrackingUpload()]))->toThrow(ValidationException::class);
    expect(G9ExamCertificateTracking::sole()->version)->toBe(1)->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('blocks issuance for failed absent and historical failed candidates',function(){
    $failed=g9TrackedCandidate('1',['eoy_average'=>0,'score_math'=>0]);
    $absent=g9TrackedCandidate('2',[],['attendance_status'=>'absent']);
    $legacy=g9TrackedCandidate('3',['score_math'=>null],['legacy_data'=>['received_cert'=>'Failed']]);
    foreach([$failed,$absent,$legacy] as $candidate){
        expect($this->tracking->summary($candidate,$this->admin)['eligible'])->toBeFalse();
        expect(fn()=>$this->tracking->save($this->admin,$candidate,g9TrackingInput(),[]))->toThrow(ValidationException::class);
    }
    $data=$this->controller->data(g12Request($this->admin,['academic_year'=>1,'status'=>'not_issued']),$this->tracking)->getData(true);
    expect($data['records']['total'])->toBe(3)->and($data['summary']['not_issued'])->toBe(3);
});

it('preserves imported collection history and flags missing imported details without writing on reads',function(){
    $candidate=g9TrackedCandidate('old',[],['legacy_data'=>['received_cert'=>'Withdrew','received_cert_Date'=>'2024-08-01','received_by'=>'Parent','certificate_number'=>'OLD-9','cert_name'=>'OriginalFile','cert_received_name'=>'SignedFile']]);
    g9TrackedCandidate('review',[],['legacy_data'=>['received_cert'=>'Withdrew']]);
    $before=G9ExamCandidate::all()->toJson();
    expect($this->tracking->state($candidate))->toMatchArray(['status'=>'received','received_date'=>'2024-08-01','recipient'=>'Parent','number'=>'OLD-9','legacy'=>true,'version'=>0,'needs_review'=>false]);
    $data=$this->controller->data(g12Request($this->admin,['academic_year'=>1,'status'=>'review']),$this->tracking)->getData(true);
    expect($data['records']['total'])->toBe(1)->and($data['records']['data'][0]['student_code'])->toBe('review')
        ->and(G9ExamCandidate::all()->toJson())->toBe($before)->and(G9ExamCertificateTracking::count())->toBe(0);
    $this->tracking->save($this->admin,$candidate,g9TrackingInput(['status'=>'received','number'=>'OLD-9','printed_signed_date'=>'','received_date'=>'2024-08-01','recipient'=>'Parent','reason'=>'Verify imported recipient']),['receipt'=>g9TrackingUpload()]);
    expect(G9ExamCertificateTracking::sole()->printed_signed_date)->toBeNull();
});

it('requires correction permission a reason and the latest version for already received certificates',function(){
    $candidate=g9TrackedCandidate();
    $this->tracking->save($this->admin,$candidate,g9TrackingInput(['status'=>'received','received_date'=>'2026-10-03','recipient'=>'Student']),['receipt'=>g9TrackingUpload()]);
    $user=g9TrackingUser(['view','certificates','certificates-manage']);g12Request($user);
    expect(fn()=>$this->tracking->save($user,$candidate,g9TrackingInput(['version'=>1]),[]))->toThrow(HttpException::class);
    g12Request($this->admin);
    expect(fn()=>$this->tracking->save($this->admin,$candidate,g9TrackingInput(['version'=>1]),[]))->toThrow(ValidationException::class);
    $this->tracking->save($this->admin,$candidate,g9TrackingInput(['status'=>'received','received_date'=>'2026-10-04','recipient'=>'Corrected Recipient','version'=>1,'reason'=>'Correct recipient and date']),[]);
    expect(G9ExamCertificateTracking::sole())->toMatchArray(['received_date'=>'2026-10-04','recipient'=>'Corrected Recipient','version'=>2])
        ->and(json_decode(DB::table('tb_g9_exam_event')->latest('id')->value('details'),true)['reason'])->toBe('Correct recipient and date');
});

it('retains prior document versions deduplicates repeat uploads and cleans storage on failed saves',function(){
    $candidate=g9TrackedCandidate();
    $this->tracking->save($this->admin,$candidate,g9TrackingInput(),['provisional'=>g9TrackingUpload('first.pdf','first')]);
    $this->tracking->save($this->admin,$candidate,g9TrackingInput(['version'=>1]),['provisional'=>g9TrackingUpload('second.pdf','second')]);
    $this->tracking->save($this->admin,$candidate,g9TrackingInput(['version'=>2]),['provisional'=>g9TrackingUpload('second.pdf','second')]);
    expect(G9ExamCertificateFile::count())->toBe(2)->and(G9ExamCertificateTracking::sole()->version)->toBe(3);
    $before=Storage::disk('local')->allFiles();
    expect(fn()=>$this->tracking->save($this->admin,$candidate,g9TrackingInput(['status'=>'received','received_date'=>'2026-10-03','recipient'=>'Student','version'=>3]),['provisional'=>g9TrackingUpload('third.pdf','third')]))->toThrow(ValidationException::class);
    expect(Storage::disk('local')->allFiles())->toBe($before)->and(G9ExamCertificateFile::count())->toBe(2)->and(G9ExamCertificateTracking::sole()->version)->toBe(3);
});

it('optimizes images creates private thumbnails supports JPG download and rejects unsupported files',function(){
    $candidate=g9TrackedCandidate();$image=UploadedFile::fake()->createWithContent('certificate.jpg',g12PhotoBytes());
    $this->tracking->save($this->admin,$candidate,g9TrackingInput(),['provisional'=>$image]);
    $file=G9ExamCertificateFile::sole();
    expect($file->mime)->toBe('image/webp')->and($file->thumbnail_path)->not->toBeNull();Storage::disk('local')->assertExists($file->thumbnail_path);
    $thumbnail=$this->controller->file(g12Request($this->admin,['thumbnail'=>1]),$file,$this->tracking);
    expect($thumbnail->headers->get('Content-Type'))->toBe('image/webp');
    $jpg=$this->controller->file(g12Request($this->admin,['jpg'=>1]),$file,$this->tracking);
    expect($jpg->headers->get('Content-Type'))->toBe('image/jpeg')->and(substr($jpg->getContent(),0,2))->toBe("\xff\xd8");
    expect(fn()=>$this->tracking->save($this->admin,$candidate,g9TrackingInput(['version'=>1]),['receipt'=>UploadedFile::fake()->createWithContent('bad.svg','<svg xmlns="http://www.w3.org/2000/svg"></svg>')]))->toThrow(ValidationException::class);
});

it('scopes filters metadata documents and mutations to permitted campuses with independent view and manage grants',function(){
    $local=g9TrackedCandidate('local');$private=g9TrackedCandidate('private',['exam_center'=>'Private Center'],['campus_id'=>2]);
    $this->tracking->save($this->admin,$private,g9TrackingInput(['number'=>'G926-PRIVATE']),['provisional'=>g9TrackingUpload()]);
    $user=g9TrackingUser(['view','certificates']);
    $data=$this->controller->data(g12Request($user,['academic_year'=>1]),$this->tracking)->getData(true);
    expect($data['records']['total'])->toBe(1)->and($data['records']['data'][0])->toMatchArray(['can_view_documents'=>false,'can_manage'=>false,'documents'=>[]]);
    expect($this->controller->options(g12Request($user,['academic_year'=>1]))->getData(true)['exam_center'])->toBe(['មណ្ឌលប្រឡង ភ្នំពេញថ្មី']);
    expect(fn()=>$this->controller->show(g12Request($user),$private,$this->tracking))->toThrow(HttpException::class)
        ->and(fn()=>$this->controller->file(g12Request($user),G9ExamCertificateFile::sole(),$this->tracking))->toThrow(HttpException::class)
        ->and(fn()=>$this->tracking->save($user,$local,g9TrackingInput(),[]))->toThrow(HttpException::class);
    foreach(['certificates-documents-view','certificates-manage'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    g12Request($user);$this->tracking->save($user,$local,g9TrackingInput(),[]);
    expect(fn()=>$this->tracking->save($user,$private,g9TrackingInput(['version'=>1]),[]))->toThrow(HttpException::class);
    expect(Access::parent('certificates-manage'))->toBe('certificates');
});

it('paginates all filtered tracking data and reconciles stage summaries',function(){
    for($i=1;$i<=30;$i++)g9TrackedCandidate((string)$i,['room_number'=>$i<=15?'2':'10']);
    $one=G9ExamCandidate::where('student_code','1')->first();$two=G9ExamCandidate::where('student_code','2')->first();
    $this->tracking->save($this->admin,$one,g9TrackingInput(),[]);
    $this->tracking->save($this->admin,$two,g9TrackingInput(['status'=>'received','number'=>'G926-002','received_date'=>'2026-10-03','recipient'=>'Student']),['receipt'=>g9TrackingUpload()]);
    $data=$this->controller->data(g12Request($this->admin,['academic_year'=>1,'page'=>2,'per_page'=>25]),$this->tracking)->getData(true);
    expect($data['records']['data'])->toHaveCount(5)->and($data['summary'])->toMatchArray(['total'=>30,'printed_signed'=>2,'received'=>1,'awaiting'=>28]);
    $filtered=$this->controller->data(g12Request($this->admin,['academic_year'=>1,'status'=>'received']),$this->tracking)->getData(true);
    expect($filtered['records']['total'])->toBe(1)->and($filtered['records']['data'][0]['id'])->toBe($two->id);
    expect(fn()=>$this->controller->data(g12Request($this->admin,['academic_year'=>1,'status'=>'diploma']),$this->tracking))->toThrow(ValidationException::class);
});
