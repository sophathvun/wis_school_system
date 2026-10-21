<?php

use App\Http\Controllers\ScholarshipController;
use App\Models\{Permission,SchoolInfo,User};
use App\Support\{PermissionHierarchy,ScholarshipPermissions,ScholarshipPublishedResults};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,Blade,DB};
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';
require_once __DIR__.'/../Fixtures/ScholarshipResultReports.php';
beforeEach(fn()=>createScholarshipSchema());

function publishedResultUser(bool $central=false,array $actions=['tab-scholarship-result']): User
{
    $user=Mockery::mock(User::class)->makePartial();$user->id=2;$user->is_global=$central;
    $user->setRelation('roles',collect());$user->setRelation('campuses',SchoolInfo::whereKey(1)->get());
    $user->shouldReceive('isSuperAdmin')->andReturnFalse();
    $grants=['students.view','western-scholarship.view',...array_map(fn($action)=>'western-scholarship.'.$action,$actions)];
    $user->shouldReceive('hasPermission')->andReturnUsing(fn($code)=>in_array($code,$grants,true));
    Auth::setUser($user);return $user;
}

function publishedResultRequest(User $user,array $filters=[]): Request
{
    $request=Request::create('/students/western-scholarship','GET',array_replace(['tab'=>'scholarship-result'],$filters));
    $request->setUserResolver(fn()=>$user);$request->setLaravelSession(app('session')->driver());app()->instance('request',$request);return $request;
}

it('projects only published award winners within the existing campus scope and removes confidential fields',function(){
    $exam=scholarshipResultReportExam(10);$exam->update(['published_at'=>now()]);
    $candidate=$exam->candidates()->where('campus_id',1)->first();
    foreach($exam->candidates()->get() as $candidate)$candidate->update(['result_total'=>12345.67,'result_percentage'=>987.65,'result_rank'=>1234,
        'snapshot'=>$candidate->snapshot+['eoy_average'=>'CONFIDENTIAL_EOY','private_note'=>'PRIVATE_SNAPSHOT']]);
    $rows=app(ScholarshipPublishedResults::class)->rows(publishedResultUser(),$exam);
    expect($rows)->toHaveCount(12)->and($rows->pluck('candidate.campus_id')->unique()->all())->toBe([1]);
    foreach($rows as $row){
        expect($row['award_label'])->not->toBeEmpty()->and($row['rank'])->toBeNull();
        expect($row['candidate']->relationLoaded('scores'))->toBeFalse()
            ->and(array_keys($row['candidate']->getAttributes()))->not->toContain('result_total','result_percentage','result_rank')
            ->and($row['candidate']->snapshot)->not->toHaveKeys(['eoy_average','private_note']);
    }
    expect(json_encode($rows))->not->toContain('CONFIDENTIAL_EOY','PRIVATE_SNAPSHOT','12345.67','987.65');
    $central=app(ScholarshipPublishedResults::class)->rows(publishedResultUser(true),$exam);
    expect($central)->toHaveCount(24);
});

it('does not release unpublished or reopened results through view or export URLs',function(){
    $exam=scholarshipResultReportExam();$user=publishedResultUser(false,['tab-scholarship-result','export-scholarship-result']);
    expect(fn()=>app(ScholarshipPublishedResults::class)->rows($user,$exam))->toThrow(HttpException::class);
    $controller=app(ScholarshipController::class);
    expect(fn()=>$controller->index(publishedResultRequest($user,['exam_id'=>$exam->id])))->toThrow(HttpException::class);
    foreach(['print','xlsx','pdf'] as $format)expect(fn()=>$controller->publishedResultReport(publishedResultRequest($user,['report_scope'=>'all-grades']),$exam,$format))->toThrow(HttpException::class);
    $exam->update(['published_at'=>now()]);expect(app(ScholarshipPublishedResults::class)->rows($user,$exam))->not->toBeEmpty();
    $exam->update(['published_at'=>null]);expect(fn()=>app(ScholarshipPublishedResults::class)->rows($user,$exam))->toThrow(HttpException::class);
});

it('prints gender from student records when published winners have older snapshots and photo-only relations',function($savedGender,$studentGender,$studentGenderKh,$expected){
    $exam=scholarshipResultReportExam();$exam->update(['published_at'=>now()]);
    foreach($exam->candidates()->where('campus_id',1)->get() as $candidate){
        $studentId=DB::table('tb_student')->insertGetId(['gender'=>$studentGender,'gender_kh'=>$studentGenderKh]);
        $snapshot=$candidate->snapshot;unset($snapshot['gender']);
        if($savedGender!==null)$snapshot['gender']=$savedGender;
        $candidate->update(['student_id'=>$studentId,'snapshot'=>$snapshot]);
    }
    $user=publishedResultUser(false,['tab-scholarship-result','export-scholarship-result']);
    $projection=app(ScholarshipPublishedResults::class)->rows($user,$exam);
    expect($projection->first()['candidate']->student->getAttributes())->not->toHaveKey('gender');
    $request=publishedResultRequest($user,['report_scope'=>'all-grades']);
    $report=app(ScholarshipController::class)->publishedResultReport($request,$exam,'print');
    foreach($report->getData()['pages'] as $page){
        expect($page['students']->pluck('gender')->unique()->all())->toBe([$expected])
            ->and($page['female'])->toBe($expected==='F'?$page['total']:0);
    }
    expect($report->render())->toContain('>'.$expected.'</td>','Female: '.($expected==='F'?4:0));
})->with([
    'missing snapshot gender'=>[null,'Female',null,'F'],
    'blank snapshot gender'=>['',' Male ',null,'M'],
    'Khmer student gender'=>[null,null,'ស្រី','F'],
    'saved snapshot takes precedence'=>['M','Female',null,'M'],
]);

it('requires published-result permission and rejects forged campus and student filters',function(){
    $exam=scholarshipResultReportExam();$exam->update(['published_at'=>now()]);$controller=app(ScholarshipController::class);
    $user=publishedResultUser(false,[]);
    expect(fn()=>$controller->index(publishedResultRequest($user)))->toThrow(HttpException::class);
    $user=publishedResultUser(false,['tab-scholarship-result','export-scholarship-result']);
    expect(fn()=>$controller->index(publishedResultRequest($user,['exam_id'=>$exam->id,'result_center'=>2])))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn()=>$controller->publishedResultReport(publishedResultRequest($user,['report_scope'=>'all-grades','report_center'=>2]),$exam,'print'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    $unawarded=$exam->candidates()->where('campus_id',1)->first();$exam->awards()->where('candidate_id',$unawarded->id)->delete();
    expect(fn()=>$controller->index(publishedResultRequest($user,['exam_id'=>$exam->id,'result_grade'=>$unawarded->grade_number,'result_student'=>$unawarded->id])))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('keeps historical award labels without exposing original totals ranks or EOY averages',function(){
    $exam=scholarshipResultReportExam();$exam->update(['published_at'=>now(),'historical_imported_at'=>now()]);
    $candidates=$exam->candidates()->where('campus_id',1)->get();
    $candidates[0]->update(['award_percent'=>100,'result_total'=>9876,'result_rank'=>123,
        'snapshot'=>$candidates[0]->snapshot+['historical_award_category'=>'total_1','eoy_average'=>1234]]);
    $candidates[1]->update(['award_percent'=>25,'snapshot'=>$candidates[1]->snapshot+['historical_award_category'=>'Original Special Award']]);
    $rows=app(ScholarshipPublishedResults::class)->rows(publishedResultUser(),$exam);
    expect($rows)->toHaveCount(2)->and($rows->pluck('award_label')->all())->toBe(['1st Place','Original Special Award'])
        ->and(json_encode($rows))->not->toContain('result_total','result_rank','eoy_average','9876');
});

it('enforces published-result authorization on the registered HTTP endpoints',function(){
    $exam=scholarshipResultReportExam();$exam->update(['published_at'=>now()]);
    $this->withoutMiddleware(\App\Http\Middleware\TrackUserPresence::class);
    $this->actingAs(publishedResultUser(false,[]))->get(route('western-scholarship.index',['tab'=>'scholarship-result']))->assertForbidden();
    $user=publishedResultUser(false,['tab-scholarship-result','export-scholarship-result']);$this->actingAs($user);
    $this->get(route('western-scholarship.index',['tab'=>'result','exam_id'=>$exam->id]))->assertForbidden();
    $this->get(route('western-scholarship.published-results.report',[$exam,'print','report_scope'=>'all-grades']))->assertOk()
        ->assertSee('Best in')->assertDontSee('EOY Avg.')->assertDontSee('No Award');
    $exam->update(['published_at'=>null]);
    $this->get(route('western-scholarship.published-results.report',[$exam,'pdf','report_scope'=>'all-grades']))->assertForbidden();
});

it('renders a separate score-free Scholarship Result view with awarded students only',function(){
    $exam=scholarshipResultReportExam(10);$exam->update(['published_at'=>now()]);$user=publishedResultUser();
    $view=app(ScholarshipController::class)->index(publishedResultRequest($user,['exam_id'=>$exam->id]));$data=$view->getData();
    $template=file_get_contents(resource_path('views/western-scholarship/published-results.blade.php'));
    preg_match("/@section\('content'\)(.*)@endsection/s",$template,$matches);
    $html=Blade::render(preg_replace('/^@vite.*$/m','',$matches[1]),$data+['errors'=>new \Illuminate\Support\ViewErrorBag()]);
    expect($html)->toContain('Scholarship Result','Scholarship Award','Best in','name="tab" value="scholarship-result"')
        ->not->toContain('Total Score','Exam Scores','EOY Avg.','Score rank','data-result-grade-metrics','Save Winners','Publish &amp; Lock','No Award','>Excel<');
    expect(substr_count($html,'data-published-winner='))->toBe(12);
});

it('requires a separate export grant and cannot expose unawarded students by changing report_students',function(){
    $exam=scholarshipResultReportExam(10);$exam->update(['published_at'=>now()]);$controller=app(ScholarshipController::class);
    $request=publishedResultRequest(publishedResultUser(),['report_scope'=>'all-grades']);
    expect(fn()=>$controller->publishedResultReport($request,$exam,'print'))->toThrow(HttpException::class);
    $user=publishedResultUser(false,['tab-scholarship-result','export-scholarship-result']);
    $request=publishedResultRequest($user,['report_scope'=>'all-grades','report_students'=>'all']);
    $view=$controller->publishedResultReport($request,$exam,'print');
    expect($view->getData()['studentScope'])->toBe('winners');
    $html=$view->render();expect($html)->not->toContain('No Award','Total Score','EOY Avg.');
    foreach($view->getData()['pages'] as $page)expect($page['students'])->toHaveCount(4);
    $xlsx=$controller->publishedResultReport(publishedResultRequest($user,['report_scope'=>'all-grades','report_students'=>'all']),$exam,'xlsx');
    $path=tempnam(sys_get_temp_dir(),'published-awards-');
    try{
        file_put_contents($path,$xlsx->getContent());$zip=new ZipArchive();expect($zip->open($path))->toBeTrue();
        expect($zip->getFromName('xl/workbook.xml'))->toContain('Grade 1','Grade 2','Grade 3');
        expect($zip->getFromName('xl/worksheets/sheet1.xml'))->not->toContain('No Award','Total Score','EOY');$zip->close();
    }finally{unlink($path);}
    $pdf=$controller->publishedResultReport(publishedResultRequest($user,['report_scope'=>'all-grades']),$exam,'pdf');
    expect(substr($pdf->getContent(),0,4))->toBe('%PDF')->and(preg_match_all('~/Type\s*/Page\b~',$pdf->getContent()))->toBe(3);
});

it('blocks confidential Central result and archive routes even when a campus role has every scholarship grant',function(){
    $exam=scholarshipResultReportExam();$exam->update(['published_at'=>now()]);
    $user=publishedResultUser(false,array_keys(ScholarshipPermissions::catalog()));$controller=app(ScholarshipController::class);
    expect(ScholarshipPermissions::allows($user,'tab-result'))->toBeFalse()
        ->and(ScholarshipPermissions::allows($user,'tab-data-transfer'))->toBeFalse()
        ->and(ScholarshipPermissions::allows($user,'tab-scholarship-result'))->toBeTrue();
    expect(fn()=>$controller->index(publishedResultRequest($user,['tab'=>'result','exam_id'=>$exam->id])))->toThrow(HttpException::class);
    expect(fn()=>$controller->resultReport(publishedResultRequest($user),$exam,'print'))->toThrow(HttpException::class);
    expect(fn()=>$controller->report(publishedResultRequest($user),$exam,'results','csv'))->toThrow(HttpException::class);
    expect(fn()=>app(\App\Services\ScholarshipHistoricalTransfer::class)->authorize($user,$exam,false))->toThrow(HttpException::class);
});

it('groups every current Scholarship function under a tab and removes child grants when the tab is off',function(){
    foreach(['students.view'=>'View Students',...array_combine(array_map(fn($action)=>'western-scholarship.'.$action,array_keys(ScholarshipPermissions::catalog())),ScholarshipPermissions::catalog())] as $code=>$name){
        $action=substr($code,strpos($code,'.')+1);Permission::create(['code'=>$code,'module'=>str_starts_with($code,'western-')?'western-scholarship':'students','action'=>$action,'name'=>$name]);
    }
    $permissions=Permission::all();$tree=PermissionHierarchy::tree($permissions);$module=$tree['students']['modules']['western-scholarship'];
    expect(array_keys($module['tabs']))->toBe(array_keys(ScholarshipPermissions::tabGroups()));
    $codes=collect($module['tabs'])->flatMap(fn($tab)=>[$tab['permission']->code,...$tab['actions']->pluck('code')->all()]);
    expect($codes->count())->toBe(count(ScholarshipPermissions::catalog())-2)->and($codes->unique()->count())->toBe($codes->count());
    $selected=$permissions->whereIn('code',['students.view','western-scholarship.view','western-scholarship.export-scholarship-result'])->pluck('id')->all();
    expect(PermissionHierarchy::normalizeIds($selected,$permissions))->not->toContain($permissions->firstWhere('code','western-scholarship.export-scholarship-result')->id);
});

it('migrates old result visibility and export grants while preserving explicit user denials',function(){
    foreach(['tab-result','export'] as $action)Permission::updateOrCreate(['code'=>'western-scholarship.'.$action],['module'=>'western-scholarship','action'=>$action,'name'=>$action]);
    $ids=Permission::pluck('id','action');
    DB::table('access_role_permissions')->insert(['role_id'=>1,'permission_id'=>$ids['tab-result']]);
    DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>$ids['export'],'allowed'=>false]);
    (require database_path('migrations/2026_10_21_000003_add_scholarship_published_result_permissions.php'))->up();
    $newTab=Permission::where('code','western-scholarship.tab-scholarship-result')->value('id');
    $newExport=Permission::where('code','western-scholarship.export-scholarship-result')->value('id');
    expect(DB::table('access_role_permissions')->where(['role_id'=>1,'permission_id'=>$newTab])->exists())->toBeTrue()
        ->and(DB::table('access_user_permission_overrides')->where(['user_id'=>2,'permission_id'=>$newExport])->value('allowed'))->toBe(0);
});
