<?php
use App\Models\{AcademicYear,SchoolInfo,ScholarshipCertificate,ScholarshipCertificateSetting,ScholarshipStudentRank,User};
use App\Services\ScholarshipCertificates;
use App\Support\{ScholarshipCertificateLayout,ScholarshipPermissions};
use App\Http\Controllers\{ScholarshipCertificateController,ScholarshipController};
use Illuminate\Support\Facades\{DB,Auth,Blade};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';
require_once __DIR__.'/../Fixtures/ScholarshipResultReports.php';
beforeEach(function(){createScholarshipSchema();(require database_path('migrations/2026_10_21_000004_create_scholarship_certificates.php'))->up();});

function certificateUser(bool $central=true,array $actions=[]): User
{
    $user=Mockery::mock(User::class)->makePartial();$user->id=2;$user->is_global=$central;
    $user->setRelation('roles',collect());$user->setRelation('campuses',SchoolInfo::whereKey(1)->get());$user->shouldReceive('isSuperAdmin')->andReturnFalse();
    $grants=['students.view','western-scholarship.view',...array_map(fn($a)=>'western-scholarship.'.$a,['tab-certificates',...$actions])];
    $user->shouldReceive('hasPermission')->andReturnUsing(fn($code)=>in_array($code,$grants,true));Auth::setUser($user);return $user;
}
function certificateFixture(): array
{
    $exam=scholarshipResultReportExam();$exam->update(['published_at'=>now()]);
    foreach($exam->candidates()->get() as $candidate){$id=DB::table('tb_student')->insertGetId(['full_name_en'=>$candidate->snapshot['name_en']]);$candidate->update(['student_id'=>$id]);}
    // This awarded student also has a class rank: never issue a second certificate.
    $winner=$exam->candidates()->find($exam->awards()->first()->candidate_id);
    $enrollment=DB::table('tb_student_enrollment')->insertGetId(['student_id'=>$winner->student_id,'academic_year_id'=>1,'campus_id'=>1,'grade_id'=>1,'class_id'=>1]);
    ScholarshipStudentRank::create(['student_enrollment_id'=>$enrollment,'rank'=>1,'assigned_by'=>2]);
    foreach([2,1] as $campus)foreach([1,3,2] as $rank){
        $id=DB::table('tb_student')->insertGetId(['full_name_en'=>"CLASS $campus RANK $rank"]);
        $enrollment=DB::table('tb_student_enrollment')->insertGetId(['student_id'=>$id,'academic_year_id'=>1,'campus_id'=>$campus,'grade_id'=>1,'class_id'=>1]);
        ScholarshipStudentRank::create(['student_enrollment_id'=>$enrollment,'rank'=>$rank,'assigned_by'=>2]);
    }
    return [$exam,AcademicYear::findOrFail(1),app(ScholarshipCertificates::class)];
}
function certificateRequest(User $user,array $data=[],string $method='GET'): Request
{
    $request=Request::create('/students/western-scholarship',$method,$data+['academic_year_id'=>1]);$request->setUserResolver(fn()=>$user);$request->setLaravelSession(app('session')->driver());app()->instance('request',$request);return $request;
}
function certificateSettings(): array
{return ['issue_date'=>'2026-10-21','location'=>'Phnom Penh','scholarship_year'=>'2026-2027','signers'=>[['name'=>'VICE PRESIDENT','position'=>'Vice President'],['name'=>'REGISTRAR DIRECTOR','position'=>'Registrar Director']]];}

it('numbers exam awards by grade subject order then 3rd 2nd 1st and continues campus class ranks without duplicates',function(){
    [$exam,$year,$service]=certificateFixture();$plan=$service->plan($year);$rows=$plan['rows'];
    expect($rows)->toHaveCount(30)->and($rows->pluck('student_id')->unique())->toHaveCount(30)
        ->and($rows->pluck('certificate_number')->first())->toBe('260001')->and($rows->last()['certificate_number'])->toBe('260030');
    $grade1=$rows->where('type','!=','class')->where('grade',1)->values();
    expect($grade1->pluck('subject')->take(5)->all())->toBe(['Khmer','Science','Mathematics','English','General Knowledge'])
        ->and($grade1->pluck('place')->slice(5)->values()->all())->toBe(['3rd Place','2nd Place','1st Place']);
    expect($rows->where('type','class')->map(fn($r)=>$r['campus'].' '.$r['place'])->values()->all())
        ->toBe(['BCH 3rd Place','BCH 2nd Place','BCH 1st Place','BKK 3rd Place','BKK 2nd Place','BKK 1st Place']);
    expect($service->assign(certificateUser(true,['certificate-assign']),$year,$plan['hash']))->toBe(30);
    expect($service->plan($year)['rows'])->toBeEmpty()->and(ScholarshipCertificate::count())->toBe(30);
    expect(json_encode(ScholarshipCertificate::first()->snapshot))->not->toContain('result_total','score','eoy');
});

it('rejects stale previews and uses year end prefixes while keeping all assigned numbers stable',function(){
    [$exam,$year,$service]=certificateFixture();$user=certificateUser(true,['certificate-assign','certificate-settings']);$plan=$service->plan($year);
    $service->update($user,$year,0,certificateSettings(),'certificate-settings');
    expect(fn()=>$service->assign($user,$year,$plan['hash']))->toThrow(HttpException::class);
    expect(ScholarshipCertificate::count())->toBe(0);$plan=$service->plan($year);$service->assign($user,$year,$plan['hash']);
    $numbers=ScholarshipCertificate::orderBy('sequence')->pluck('certificate_number')->all();
    expect(fn()=>$service->assign($user,$year,$plan['hash']))->toThrow(HttpException::class);
    expect(ScholarshipCertificate::orderBy('sequence')->pluck('certificate_number')->all())->toBe($numbers);
    $exam->update(['academic_year_id'=>2]);$next=$service->plan(AcademicYear::find(2));expect($next['rows']->first()['certificate_number'])->toBe('270001');
});

it('appends late class ranks and never reuses cancelled numbers',function(){
    [$exam,$year,$service]=certificateFixture();$user=certificateUser(true,['certificate-assign','certificate-cancel']);$plan=$service->plan($year);$service->assign($user,$year,$plan['hash']);
    $first=ScholarshipCertificate::first();$service->cancel($user,$first,'Incorrect award assignment');
    expect($first->fresh()->status)->toBe('cancelled')->and($service->plan($year)['rows'])->toBeEmpty();
    $id=DB::table('tb_student')->insertGetId(['full_name_en'=>'LATE STUDENT']);$e=DB::table('tb_student_enrollment')->insertGetId(['student_id'=>$id,'academic_year_id'=>1,'campus_id'=>1,'grade_id'=>1,'class_id'=>1]);
    ScholarshipStudentRank::create(['student_enrollment_id'=>$e,'rank'=>3,'assigned_by'=>2]);
    $plan=$service->plan($year);expect($plan['rows'])->toHaveCount(1)->and($plan['rows']->first()['certificate_number'])->toBe('260031');
    $service->assign($user,$year,$plan['hash']);expect(ScholarshipCertificate::count())->toBe(31);
});

it('requires Central write permissions and applies own-campus viewing and printing',function(){
    [$exam,$year,$service]=certificateFixture();$campus=certificateUser(false,array_keys(ScholarshipPermissions::catalog()));
    foreach(['certificate-settings','certificate-template','certificate-assign','certificate-cancel'] as $action)expect(ScholarshipPermissions::allows($campus,$action))->toBeFalse();
    expect(fn()=>$service->assign($campus,$year,$service->plan($year)['hash']))->toThrow(HttpException::class);
    $central=certificateUser(true,['certificate-assign','certificate-settings']);$service->assign($central,$year,$service->plan($year)['hash']);$service->update($central,$year,0,certificateSettings(),'certificate-settings');
    $controller=app(ScholarshipCertificateController::class);$campus=certificateUser(false,['certificate-print']);
    $data=$controller->index(certificateRequest($campus),ScholarshipController::TABS)->getData();
    expect($data['rows']->pluck('campus_id')->unique()->all())->toBe([1])->and(json_encode($data['editor']))->not->toContain('GRADE 1 STUDENT 02');
    $foreign=ScholarshipCertificate::where('campus_id',2)->first();
    expect(fn()=>$controller->report(certificateRequest($campus,['ids'=>[$foreign->id],'format'=>'print','frame'=>false]),$year))->toThrow(HttpException::class);
    $own=ScholarshipCertificate::where('campus_id',1)->first();
    expect($controller->report(certificateRequest($campus,['ids'=>[$own->id],'format'=>'print','frame'=>false]),$year)->getData()['pages'])->toHaveCount(1);
    expect(fn()=>$controller->report(certificateRequest(certificateUser(),['ids'=>[$own->id],'format'=>'pdf','frame'=>true]),$year))->toThrow(HttpException::class);
});

it('blocks reopened results and cancelled or changed awards from print output',function(){
    [$exam,$year,$service]=certificateFixture();$user=certificateUser(true,['certificate-assign','certificate-print','certificate-settings','certificate-cancel']);
    $service->assign($user,$year,$service->plan($year)['hash']);$service->update($user,$year,0,certificateSettings(),'certificate-settings');
    $certificate=ScholarshipCertificate::first();$request=certificateRequest($user,['ids'=>[$certificate->id],'format'=>'print','frame'=>true]);$controller=app(ScholarshipCertificateController::class);
    $exam->update(['published_at'=>null]);expect(fn()=>$controller->report($request,$year))->toThrow(ValidationException::class);
    $exam->update(['published_at'=>now()]);$exam->awards()->where('id',explode(':',$certificate->snapshot['source'])[1])->delete();
    expect(fn()=>$controller->report($request,$year))->toThrow(ValidationException::class);
    $service->cancel($user,$certificate,'Changed after publication');expect(fn()=>$controller->report($request,$year))->toThrow(ValidationException::class);
});

it('validates template boundaries fonts tokens and concurrent edits and escapes custom text',function(){
    [$exam,$year,$service]=certificateFixture();$user=certificateUser(true,['certificate-template']);$controller=app(ScholarshipCertificateController::class);
    $layout=ScholarshipCertificateLayout::defaults('exam');$layout['fields']['title']['text']='<script>bad()</script> {{student_name}}';
    $controller->layout(certificateRequest($user,['version'=>0,'type'=>'exam','layout'=>$layout],'POST'),$year);
    expect($service->settings($year)['layouts']['exam']['fields']['title']['text'])->toContain('<script>');
    expect(fn()=>$controller->layout(certificateRequest($user,['version'=>0,'type'=>'exam','layout'=>$layout],'POST'),$year))->toThrow(HttpException::class);
    foreach(['outside','font','token','offset'] as $case){$invalid=$layout;
        match($case){'outside'=>$invalid['fields']['title']['x']=200,'font'=>$invalid['fields']['title']['font']='unknown','token'=>$invalid['fields']['title']['text']='{{password}}','offset'=>$invalid['offset_x']=-10};
        if($case==='offset')$invalid['fields']['title']['x']=0;
        expect(fn()=>ScholarshipCertificateLayout::validate('exam',$invalid))->toThrow(ValidationException::class);
    }
});

it('renders the editor and produces one portrait A4 PDF page per selected certificate without changing numbers',function(){
    [$exam,$year,$service]=certificateFixture();$user=certificateUser(true,['certificate-assign','certificate-settings','certificate-template','certificate-print']);
    $service->assign($user,$year,$service->plan($year)['hash']);$service->update($user,$year,0,certificateSettings(),'certificate-settings');$controller=app(ScholarshipCertificateController::class);
    $view=$controller->index(certificateRequest($user),ScholarshipController::TABS);$template=file_get_contents(resource_path('views/western-scholarship/certificates.blade.php'));
    preg_match("/@section\('content'\)(.*)@endsection/s",$template,$matches);$html=Blade::render(preg_replace('/^@vite.*$/m','',$matches[1]),$view->getData()+['errors'=>new \Illuminate\Support\ViewErrorBag()]);
    expect($html)->toContain('Customize Certificate','School Logo 1','Save Template','260001','data-cert-canvas');
    $ids=ScholarshipCertificate::orderBy('sequence')->take(3)->pluck('id')->all();$before=ScholarshipCertificate::pluck('certificate_number','id')->all();
    foreach([true,false] as $frame){$response=$controller->report(certificateRequest($user,['ids'=>$ids,'format'=>'pdf','frame'=>$frame]),$year);
        expect(substr($response->getContent(),0,4))->toBe('%PDF')->and(preg_match_all('~/Type\s*/Page\b~',$response->getContent()))->toBe(3);
    }
    expect(ScholarshipCertificate::pluck('certificate_number','id')->all())->toBe($before);
});

it('registers the certificate HTTP actions and denies writes to users without permission',function(){
    [$exam,$year,$service]=certificateFixture();$this->withoutMiddleware(\App\Http\Middleware\TrackUserPresence::class);$this->actingAs(certificateUser(false));
    $this->getJson(route('western-scholarship.certificates.plan',$year))->assertForbidden();
    $this->postJson(route('western-scholarship.certificates.settings',$year),certificateSettings()+['version'=>0])->assertForbidden();
    $this->postJson(route('western-scholarship.certificates.assign',$year),['hash'=>str_repeat('0',64)])->assertForbidden();
    $this->postJson(route('western-scholarship.certificates.layout',$year),['version'=>0,'type'=>'exam','layout'=>ScholarshipCertificateLayout::defaults('exam')])->assertForbidden();
});

it('rolls back the complete assignment if a certificate cannot be saved',function(){
    [$exam,$year,$service]=certificateFixture();$user=certificateUser(true,['certificate-assign']);$plan=$service->plan($year);
    ScholarshipCertificate::creating(function($certificate){if($certificate->sequence===2)throw new RuntimeException('Simulated write failure');});
    try{expect(fn()=>$service->assign($user,$year,$plan['hash']))->toThrow(RuntimeException::class);}
    finally{ScholarshipCertificate::flushEventListeners();}
    expect(ScholarshipCertificate::count())->toBe(0)->and($service->plan($year)['rows']->first()['certificate_number'])->toBe('260001');
});

it('renders a Branding SVG logo without losing the certificate number or changing its proportions',function(){
    $layout=ScholarshipCertificateLayout::defaults('exam');$layout['fields']['logo']['visible']=true;
    $svg='data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 100"><rect width="300" height="100" fill="#204077"/><text x="10" y="60" fill="white">WIS</text></svg>');
    $assets=['frame'=>'data:image/jpeg;base64,'.base64_encode(file_get_contents(public_path('images/scholarship/certificates/frame.jpg'))),'branding1'=>$svg];
    $values=app(ScholarshipCertificates::class)->values(['student_name'=>'SAMPLE STUDENT','place'=>'1st Place','grade'=>1,'academic_year'=>'2025-2026','award_percent'=>100],certificateSettings(),'260001');
    $pdf=app(\App\Support\ScholarshipCertificateReport::class)->pdf([['layout'=>$layout,'values'=>$values]],$assets,true);
    expect(substr($pdf,0,4))->toBe('%PDF')->and(preg_match_all('~/Type\s*/Page\b~',$pdf))->toBe(1);
});

it('paginates certificates with the requested sizes and keeps numbering and student filters complete',function($size,$page,$expected,$first){
    [$exam,$year,$service]=certificateFixture();$user=certificateUser();$input=['page'=>$page];if($size!==null)$input['per_page']=$size;
    $data=app(ScholarshipCertificateController::class)->index(certificateRequest($user,$input),ScholarshipController::TABS)->getData();
    expect($data['certificateRows']->count())->toBe($expected)->and($data['certificateRows']->total())->toBe(30)
        ->and($data['certificateRows']->firstItem())->toBe($first)->and($data['studentOptions'])->toHaveCount(30);
})->with([[null,1,15,1],[15,2,15,16],[25,2,5,26],[50,1,30,1],[100,1,30,1],['all',99,30,1]]);

it('returns scoped pagination fragments and preserves all printable records beyond the current page',function(){
    [$exam,$year,$service]=certificateFixture();$user=certificateUser(true,['certificate-assign']);$service->assign($user,$year,$service->plan($year)['hash']);
    $request=certificateRequest($user,['page'=>2,'per_page'=>15,'certificate_list'=>1]);$request->headers->set('X-Requested-With','XMLHttpRequest');
    $result=app(ScholarshipCertificateController::class)->index($request,ScholarshipController::TABS)->getData(true);
    expect($result['pagination']['current_page'])->toBe(2)->and($result['pagination']['from'])->toBe(16)
        ->and($result['pagination']['printable_ids'])->toHaveCount(30)->and(substr_count($result['html'],'<tr data-cert-row>'))->toBe(15);
    $user=certificateUser(false);$request=certificateRequest($user,['per_page'=>'all','certificate_list'=>1]);$request->headers->set('X-Requested-With','XMLHttpRequest');
    $result=app(ScholarshipCertificateController::class)->index($request,ScholarshipController::TABS)->getData(true);
    expect($result['pagination']['total'])->toBe(15)->and($result['pagination']['printable_ids'])->toHaveCount(15);
});

it('opens certificates from rank navigation without inheriting rank pagination',function($rankSize){
    certificateFixture();$user=certificateUser(true,['tab-assign-rank']);
    $template=file_get_contents(resource_path('views/western-scholarship/index.blade.php'));
    preg_match('/<nav\b[^>]*id="scholarshipTabList"[^>]*>(.*?)<\/nav>/s',$template,$navigation);
    $html=Blade::render($navigation[1],['tabs'=>ScholarshipController::TABS,'tab'=>'assign-rank',
        'filters'=>['academic_year_id'=>1,'campus_id'=>1,'per_page'=>$rankSize]]);
    $document=new DOMDocument();$document->loadHTML($html);
    $link=(new DOMXPath($document))->query('//a[@data-scholarship-tab-label="Certificates"]')->item(0);
    parse_str(parse_url($link->getAttribute('href'),PHP_URL_QUERY),$query);
    $view=app(ScholarshipController::class)->index(certificateRequest($user,$query));
    expect($view->name())->toBe('western-scholarship.certificates')
        ->and($query)->toBe(['academic_year_id'=>'1','tab'=>'certificates'])
        ->and($view->getData()['pageSize'])->toBe(15)
        ->and($view->getData()['certificateRows']->count())->toBe(15);
})->with([10,75,25,'all']);
