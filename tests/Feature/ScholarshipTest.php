<?php

use App\Http\Controllers\ScholarshipController;
use App\Models\ScholarshipStudentRank;
use App\Models\SchoolInfo;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';
beforeEach(fn () => createScholarshipSchema());

it('serves all six confirmed seat reports as Print Excel and real PDF downloads',function($kind){
    $exam=scholarshipSeatReportExam();$controller=app(ScholarshipController::class);
    foreach(['print','xlsx','pdf'] as $format){
        $response=$controller->seatReport(scholarshipRequest('GET',['report_center'=>$exam->centers()->first()->id,'report_scope'=>'all-rooms'],[],[1,2]),$exam,$kind,$format);
        if($format==='print')expect($response->render())->toContain('North Exam Center','data-report-room="1"')->not->toContain('South Exam Center');
        else{
            expect($response->getStatusCode())->toBe(200)->and($response->getContent())->toStartWith($format==='pdf'?'%PDF-':'PK');
            expect($response->headers->get('Content-Disposition'))->toContain($kind.'.'.$format);
        }
    }
})->with(array_keys(\App\Support\ScholarshipSeatReports::DOCUMENTS));

it('requires confirmation and the existing seat tab and export permissions for the new reports',function($denied,$accessible,$draft){
    $exam=scholarshipSeatReportExam();if($draft)$exam->forceFill(['seats_locked_at'=>null])->save();
    $request=scholarshipRequest('GET',[],$denied,$accessible);
    expect(fn()=>app(ScholarshipController::class)->seatReport($request,$exam,'candidates','print'))
        ->toThrow($draft?ValidationException::class:\Symfony\Component\HttpKernel\Exception\HttpException::class);
})->with([
    [['western-scholarship.tab-seat-plan'],[1,2],false],
    [['western-scholarship.export-seats'],[1,2],false],
    [[],[1],false],
    [[],[1,2],true],
]);

function scholarshipRequest(string $method = 'GET', array $data = [], array $denied = [], array $accessible=[1], ?array $assigned=null, ?array $contexts=null): Request
{
    DB::table('access_user_campuses')->where('user_id',1)->delete();
    foreach($assigned??$accessible as $id)DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>$id]);
    $user = Mockery::mock(User::class)->makePartial(); $user->id = 1; $user->active_campus_id = 1;
    $user->setRelation('roles',User::findOrFail(1)->roles()->get());
    $user->shouldReceive('isSuperAdmin')->andReturnFalse();
    $user->shouldReceive('canAccessCampus')->andReturnUsing(fn ($id) => in_array($id,$accessible,true));
    $user->shouldReceive('accessibleCampuses')->andReturnUsing(fn () => SchoolInfo::whereIn('id',$accessible));
    $user->shouldReceive('hasPermission')->andReturnUsing(fn ($code, $campus) => in_array($campus,$contexts??$accessible,true) && !in_array($code, $denied, true));
    $request = Request::create('/students/western-scholarship', $method, $data);
    $request->setUserResolver(fn () => $user); return $request;
}

it('offers only ranked campuses in the regular academic year for exam centers',function(){
    ScholarshipStudentRank::create(['student_enrollment_id'=>1,'rank'=>1,'assigned_by'=>1]);
    ScholarshipStudentRank::create(['student_enrollment_id'=>2,'rank'=>1,'assigned_by'=>1]);
    $controller=app(ScholarshipController::class);
    $data=$controller->examCenterCampuses(scholarshipRequest('GET',['academic_year_id'=>1]))->getData(true);
    expect($data['campuses'])->toBe([['id'=>1,'label'=>'BCH','count'=>1]]);
    ScholarshipStudentRank::create(['student_enrollment_id'=>3,'rank'=>1,'assigned_by'=>1]);
    expect(fn()=>$controller->examCenterCampuses(scholarshipRequest('GET',['academic_year_id'=>1])))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    $data=$controller->examCenterCampuses(scholarshipRequest('GET',['academic_year_id'=>1],[],[1,2]))->getData(true);
    expect(array_column($data['campuses'],'label'))->toBe(['BCH','BKK']);
    expect($controller->examCenterCampuses(scholarshipRequest('GET',['academic_year_id'=>2]))->getData(true)['campuses'])->toHaveCount(1);
});

it('protects exam center campus options with settings permission and rejects summer periods',function(){
    DB::table('tb_academic_year')->where('id',2)->update(['period_type'=>'summer']);$controller=app(ScholarshipController::class);
    expect(fn()=>$controller->examCenterCampuses(scholarshipRequest('GET',['academic_year_id'=>1],['western-scholarship.settings'])))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(fn()=>$controller->examCenterCampuses(scholarshipRequest('GET',['academic_year_id'=>2])))->toThrow(ValidationException::class);
});

it('saves centers through settings and includes confirmed center names in seat exports',function(){
    DB::table('tb_student')->where('id',1)->update(['full_name_en'=>'ZULU']);
    DB::table('tb_student')->where('id',2)->update(['full_name_en'=>'ALICE']);
    foreach([1,3] as $enrollment)ScholarshipStudentRank::create(['student_enrollment_id'=>$enrollment,'rank'=>1,'assigned_by'=>1]);
    $data=['academic_year_id'=>1,'exam_date'=>'2026-11-20','room_capacity'=>25,'version'=>1,
        'subjects'=>[['name'=>'Khmer','grade_from'=>1,'grade_to'=>12,'max_score'=>100,'weight'=>1]],
        'exam_centers'=>[['key'=>'a','name'=>'BCH Exam Center'],['key'=>'b','name'=>'BKK Exam Center']],
        'center_assignments'=>[1=>'a',2=>'b']];
    $controller=app(ScholarshipController::class);
    expect(fn()=>$controller->saveExam(scholarshipRequest('POST',$data)))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(\App\Models\ScholarshipExam::count())->toBe(0);
    $request=scholarshipRequest('POST',$data,[],[1,2]);$controller->saveExam($request);
    $exam=\App\Models\ScholarshipExam::firstOrFail();$workflow=app(\App\Services\ScholarshipWorkflow::class);
    $workflow->lockPlan($request->user(),$exam,$workflow->plan($exam)['token']);$exam=$exam->fresh();
    $report=$controller->report(scholarshipRequest('GET',[],[],[1,2]),$exam,'seats','print')->getData();
    expect($report['headers'][0])->toBe('Exam Center')->and(array_column($report['rows'],0))->toBe(['BCH Exam Center','BKK Exam Center']);
    expect($report['headers'])->toHaveCount(11)->and($report['headers'][4])->toBe('Exam Code')
        ->and($report['headers'][5])->toBe('Student ID')->and($report['rows'][0])->toHaveCount(11)
        ->and($report['rows'][0][4])->toBe('2526-G03-002')->and($report['rows'][0][5])->toBe('2026001');
    $view=view('western-scholarship._settings',['exam'=>$exam,'examPermissions'=>['settings'=>true],
        'examCenterCampuses'=>app(\App\Services\ScholarshipExamCenters::class)->campuses(1),'academicYears'=>collect(),
        'errors'=>new \Illuminate\Support\ViewErrorBag()])->render();
    expect($view)->toContain('name="center_assignments[1]"','name="center_assignments[2]"','value="center-'.$exam->centers()->first()->id.'" selected','data-settings-fields disabled');
});

it('paginates the complete seat preview without limiting confirmation exports or campus permissions',function(){
    $exam=\App\Models\ScholarshipExam::create(['academic_year_id'=>1,'name'=>'2025-2026','exam_date'=>'2026-11-20','room_capacity'=>25,'campus_ids'=>[1,2],'eligible_ranks'=>[]]);
    $exam->subjects()->create(['name'=>'Khmer','grade_from'=>1,'grade_to'=>12,'max_score'=>100,'weight'=>1,'position'=>0]);
    foreach(range(1,77) as $i)$exam->candidates()->create(['campus_id'=>$i===77?2:1,'class_id'=>1,'grade_number'=>3,'class_rank'=>1,
        'snapshot'=>['student_code'=>sprintf('SEAT-%03d',$i),'name_en'=>sprintf('STUDENT %03d',$i),'name_kh'=>'សិស្ស','campus'=>$i===77?'BKK':'BCH','class'=>'A']]);
    $controller=app(ScholarshipController::class);$params=['tab'=>'seat-plan','exam_id'=>$exam->id];
    $first=$controller->index(scholarshipRequest('GET',$params,[],[1,2]))->getData();
    expect($first['seatRows']->count())->toBe(15)->and($first['seatRows']->total())->toBe(77)->and($first['seatPageSize'])->toBe(15)
        ->and($first['seatRows']->lastPage())->toBe(6)->and($first['seatPlan']['rows'])->toHaveCount(77);
    $second=$controller->index(scholarshipRequest('GET',$params+['seat_page'=>2],[],[1,2]))->getData();
    expect($second['seatRows']->firstItem())->toBe(16)->and($second['seatRows']->lastItem())->toBe(30)
        ->and($second['seatRows']->first()['snapshot']['student_code'])->toBe('SEAT-016')->and($second['seatPlan']['token'])->toBe($first['seatPlan']['token']);
    foreach([25,50,75,100,'all'] as $size){
        $data=$controller->index(scholarshipRequest('GET',$params+['seat_per_page'=>$size],[],[1,2]))->getData();
        expect($data['seatRows']->count())->toBe($size==='all'?77:min(77,$size));
    }
    $last=$controller->index(scholarshipRequest('GET',$params+['seat_page'=>999],[],[1,2]))->getData();
    expect($last['seatRows']->currentPage())->toBe(6)->and($last['seatRows']->firstItem())->toBe(76);
    $request=scholarshipRequest('GET',$params+['seat_page'=>2,'seat_list'=>1],[],[1,2]);$request->headers->set('Accept','application/json');
    $html=$controller->index($request)->getData(true)['html'];
    expect($html)->toContain('SEAT-016','SEAT-030','data-page-size="15"')->not->toContain('SEAT-001','SEAT-031');
    $scoped=$controller->index(scholarshipRequest('GET',$params+['seat_per_page'=>'all']))->getData();
    expect($scoped['seatRows']->total())->toBe(76)->and($scoped['seatRows']->pluck('snapshot.campus')->unique()->all())->toBe(['BCH']);
    $controller->lockPlan(scholarshipRequest('POST',['plan_token'=>$first['seatPlan']['token'],'confirm'=>1],[],[1,2]),$exam);
    expect($exam->candidates()->whereNotNull('room_seat')->count())->toBe(77);
    $report=$controller->report(scholarshipRequest('GET',[],[],[1,2]),$exam->fresh(),'seats','print')->getData();
    expect($report['rows'])->toHaveCount(77);
    $confirmed=$controller->index(scholarshipRequest('GET',$params+['seat_page'=>2],[],[1,2]))->getData();
    expect($confirmed['seatRows']->count())->toBe(15)->and($confirmed['seatRows']->first()['snapshot']['student_code'])->toBe('SEAT-016');
});

it('returns success feedback only after the seat plan has been saved and locked',function(){
    $exam=\App\Models\ScholarshipExam::create(['academic_year_id'=>1,'name'=>'2025-2026','exam_date'=>'2026-11-20','room_capacity'=>25,'campus_ids'=>[1],'eligible_ranks'=>[]]);
    $exam->subjects()->create(['name'=>'Khmer','grade_from'=>1,'grade_to'=>12,'max_score'=>100,'weight'=>1,'position'=>0]);
    $candidate=$exam->candidates()->create(['campus_id'=>1,'class_id'=>1,'grade_number'=>3,'class_rank'=>1,
        'snapshot'=>['student_code'=>'SEAT-001','name_en'=>'STUDENT ONE','campus'=>'BCH','class'=>'A']]);
    $controller=app(ScholarshipController::class);$workflow=app(\App\Services\ScholarshipWorkflow::class);
    $plan=$workflow->plan($exam->fresh());
    $request=scholarshipRequest('POST',['plan_token'=>str_repeat('0',64),'confirm'=>1]);
    $request->headers->set('Accept','application/json');
    expect(fn()=>$controller->lockPlan($request,$exam))->toThrow(ValidationException::class);
    expect($exam->fresh()->seats_locked_at)->toBeNull()->and($candidate->fresh()->room_seat)->toBeNull();
    $request=scholarshipRequest('POST',['plan_token'=>$plan['token'],'confirm'=>1]);
    $request->headers->set('Accept','application/json');
    $response=$controller->lockPlan($request,$exam);
    expect($response->getStatusCode())->toBe(200)->and($response->getData(true))->toBe([
        'message'=>'Seat plan confirmed and locked.',
        'redirect'=>route('western-scholarship.index',['exam_id'=>$exam->id,'tab'=>'seat-plan']),
    ])->and($exam->fresh()->seats_locked_at)->not->toBeNull()->and($candidate->fresh()->room_seat)->toBe(1);
});

it('handles an empty seat list and all mode without an invalid page',function(){
    $exam=\App\Models\ScholarshipExam::create(['academic_year_id'=>1,'name'=>'2025-2026','exam_date'=>'2026-11-20','room_capacity'=>25,'campus_ids'=>[1],'eligible_ranks'=>[]]);
    $data=app(ScholarshipController::class)->index(scholarshipRequest('GET',['tab'=>'seat-plan','exam_id'=>$exam->id,'seat_per_page'=>'all','seat_page'=>999]))->getData();
    expect($data['seatRows']->total())->toBe(0)->and($data['seatRows']->currentPage())->toBe(1)->and($data['seatRows']->lastPage())->toBe(1);
});

it('rejects unsupported seat page sizes',function($size){
    $exam=\App\Models\ScholarshipExam::create(['academic_year_id'=>1,'name'=>'2025-2026','exam_date'=>'2026-11-20','room_capacity'=>25,'campus_ids'=>[1],'eligible_ranks'=>[]]);
    app(ScholarshipController::class)->index(scholarshipRequest('GET',['tab'=>'seat-plan','exam_id'=>$exam->id,'seat_per_page'=>$size]));
})->with([0,10,16,1000])->throws(ValidationException::class);

it('searches students by filters and English Khmer names or student ID without leaking other campuses', function ($q) {
    $response = app(ScholarshipController::class)->students(scholarshipRequest('GET', ['academic_year_id'=>1,'campus_id'=>1,'grade_id'=>1,'q'=>$q]))->getData(true);
    expect(array_column($response['data'], 'id'))->toBe([1])->and($response['has_more'])->toBeFalse();
})->with(['CHEAN', 'ចាន់', '2026001', '']);

it('saves and updates a numeric rank for one enrollment without overwriting another year', function () {
    $controller = app(ScholarshipController::class);
    foreach ([['year'=>1,'enrollment'=>1,'rank'=>2],['year'=>2,'enrollment'=>2,'rank'=>3],['year'=>1,'enrollment'=>1,'rank'=>1]] as $row) {
        $controller->saveRank(scholarshipRequest('POST', ['academic_year_id'=>$row['year'],'campus_id'=>1,'enrollment_id'=>$row['enrollment'],'rank'=>$row['rank'],'eoy_average'=>95,'rank_id'=>ScholarshipStudentRank::where('student_enrollment_id',$row['enrollment'])->value('id')]));
    }
    expect(ScholarshipStudentRank::count())->toBe(2)->and(ScholarshipStudentRank::where('student_enrollment_id',1)->value('rank'))->toBe(1)
        ->and(ScholarshipStudentRank::where('student_enrollment_id',2)->value('rank'))->toBe(3);
    $data = $controller->students(scholarshipRequest('GET', ['academic_year_id'=>1,'campus_id'=>1]))->getData(true);
    expect($data['data'])->toBe([]);
});

it('rejects missing fractional negative and zero ranks', function ($rank) {
    app(ScholarshipController::class)->saveRank(scholarshipRequest('POST', ['academic_year_id'=>1,'campus_id'=>1,'enrollment_id'=>1,'rank'=>$rank,'eoy_average'=>95]));
})->with([null, 0, -1, 1.5, 'first', 4, 1000000])->throws(ValidationException::class);

it('rejects a selected student from another year grade or campus', function ($filters, $enrollment) {
    app(ScholarshipController::class)->saveRank(scholarshipRequest('POST', $filters + ['enrollment_id'=>$enrollment,'rank'=>1,'eoy_average'=>95]));
})->with([
    [['academic_year_id'=>1,'campus_id'=>1], 2],
    [['academic_year_id'=>1,'campus_id'=>1,'grade_id'=>2], 1],
    [['academic_year_id'=>1,'campus_id'=>1], 3],
    [['academic_year_id'=>1,'campus_id'=>2], 3],
])->throws(ValidationException::class);

it('allows viewing without assignment permission but blocks changing rank', function () {
    $request = scholarshipRequest('POST', ['academic_year_id'=>1,'campus_id'=>1,'enrollment_id'=>1,'rank'=>1], ['western-scholarship.assign-rank']);
    app(ScholarshipController::class)->students($request);
    app(ScholarshipController::class)->saveRank($request);
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

it('blocks viewing when the scholarship or Students permission is denied', function ($permission) {
    app(ScholarshipController::class)->students(scholarshipRequest('GET', ['academic_year_id'=>1,'campus_id'=>1], [$permission]));
})->with(['students.view', 'western-scholarship.view'])->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

it('loads the scholarship tabs and restores the selected student and saved rank', function () {
    ScholarshipStudentRank::create(['student_enrollment_id'=>1,'rank'=>3,'assigned_by'=>1]);
    $data = app(ScholarshipController::class)->index(scholarshipRequest('GET', ['academic_year_id'=>1,'campus_id'=>1,'enrollment_id'=>1]))->getData();
    expect($data['tabs'])->toHaveCount(8)->toHaveKeys(['scholarship-result','certificates','answer-sheets'])->not->toHaveKeys(['result','data-transfer'])->and($data['tab'])->toBe('assign-rank')->and($data['selected']->id)->toBe(1)->and($data['selectedRank'])->toBe(3)->and($data['canAssign'])->toBeTrue();
});

it('excludes summer periods and rejects forged summer filter and rank requests',function(){
    DB::table('tb_academic_year')->where('id',2)->update(['period_type'=>'summer']);
    $controller=app(ScholarshipController::class);
    $data=$controller->index(scholarshipRequest())->getData();
    expect($data['academicYears']->pluck('id')->all())->toBe([1]);
    expect(fn()=>$controller->students(scholarshipRequest('GET',['academic_year_id'=>2,'campus_id'=>1])))->toThrow(ValidationException::class);
    expect(fn()=>$controller->saveRank(scholarshipRequest('POST',['academic_year_id'=>2,'campus_id'=>1,'enrollment_id'=>2,'rank'=>1])))->toThrow(ValidationException::class);
    expect(ScholarshipStudentRank::count())->toBe(0);
});

it('synchronizes candidates through the real rank endpoint and selects the matching academic year automatically',function(){
    $controller=app(ScholarshipController::class);$request=scholarshipRequest('POST',['academic_year_id'=>1,'exam_date'=>'2026-11-20','room_capacity'=>25,'version'=>1,
        'subjects'=>[['name'=>'Khmer','grade_from'=>1,'grade_to'=>12,'max_score'=>100,'weight'=>1]]]);
    $controller->saveExam($request);$exam=\App\Models\ScholarshipExam::first();
    $controller->saveRank(scholarshipRequest('POST',['academic_year_id'=>1,'campus_id'=>1,'enrollment_id'=>1,'rank'=>3,'eoy_average'=>90]));
    expect($exam->candidates()->first()->class_rank)->toBe(3);
    $data=$controller->index(scholarshipRequest('GET',['academic_year_id'=>1]))->getData();
    expect($data['exam']->id)->toBe($exam->id)->and($data['candidateCount'])->toBe(1);
    $controller->removeRank(scholarshipRequest('POST'),ScholarshipStudentRank::first());
    expect($exam->candidates()->count())->toBe(0);
});

it('registers scholarship in Students permissions and grants new actions only to the super administrator', function () {
    DB::table('access_roles')->insert([['id'=>1,'code'=>'super-admin'],['id'=>2,'code'=>'registrar']]);
    $migration = require database_path('migrations/2026_10_14_000005_add_scholarship_permissions.php');
    $migration->up(); $migration->up();
    expect(DB::table('access_permissions')->where('module','western-scholarship')->count())->toBe(count(\App\Support\ScholarshipPermissions::catalog()))
        ->and(DB::table('access_role_permissions')->where('role_id',1)->count())->toBe(count(\App\Support\ScholarshipPermissions::catalog()))
        ->and(DB::table('access_role_permissions')->where('role_id',2)->count())->toBe(0)
        ->and(\App\Support\PermissionHierarchy::groups()['students']['modules'])->toContain('western-scholarship');
});

it('hides forbidden scholarship tabs and blocks their direct URLs',function($tab,$permission){
    $controller=app(ScholarshipController::class);
    $request=scholarshipRequest('GET',[],['western-scholarship.'.$permission]);
    $data=$controller->index($request)->getData();
    expect($data['tabs'])->not->toHaveKey($tab);
    if($tab==='assign-rank')expect($data['tab'])->toBe('settings');
    expect(fn()=>$controller->index(scholarshipRequest('GET',['tab'=>$tab],['western-scholarship.'.$permission])))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
})->with(collect(\App\Support\ScholarshipPermissions::TABS)->map(fn($permission,$tab)=>[$tab,$permission])->values()->all());

it('requires a tab permission as well as its action permission',function($action,$parent){
    $request=scholarshipRequest('GET',[],['western-scholarship.'.$parent]);
    expect(\App\Support\ScholarshipPermissions::allows($request->user(),$action,1))->toBeFalse();
})->with(collect(\App\Support\ScholarshipPermissions::PARENTS)->map(fn($parent,$action)=>[$action,$parent])->values()->all());

it('blocks rank AJAX endpoints and deletion when the rank tab is denied',function(){
    $controller=app(ScholarshipController::class);$denied=['western-scholarship.tab-assign-rank'];
    $record=ScholarshipStudentRank::create(['student_enrollment_id'=>1,'rank'=>1,'assigned_by'=>1]);
    foreach(['students','rankFilters'] as $method)
        expect(fn()=>$controller->$method(scholarshipRequest('GET',['academic_year_id'=>1,'campus_id'=>1],$denied)))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(fn()=>$controller->saveRank(scholarshipRequest('POST',['academic_year_id'=>1,'campus_id'=>1,'enrollment_id'=>1,'rank'=>1,'eoy_average'=>95],$denied)))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(fn()=>$controller->removeRank(scholarshipRequest('POST',[],$denied),$record))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect($record->fresh())->not->toBeNull();
});

it('limits campus staff to assigned campuses even if generic accessible campuses are broader',function(){
    $controller=app(ScholarshipController::class);
    foreach([1,3] as $id)ScholarshipStudentRank::create(['student_enrollment_id'=>$id,'rank'=>1,'eoy_average'=>95,'assigned_by'=>1]);
    $request=scholarshipRequest('GET',['academic_year_id'=>1],[],[1,2],[1]);
    $data=$controller->index($request)->getData();
    expect($data['campuses']->pluck('id')->all())->toBe([1])->and($data['records']->pluck('student_enrollment_id')->all())->toBe([1]);
    $html=$controller->rankFilters($request)->getData(true)['html'];
    expect($html)->toContain('CHEAN')->not->toContain('SECOND STUDENT');
    foreach(['students','rankFilters'] as $method)
        expect(fn()=>$controller->$method(scholarshipRequest('GET',['academic_year_id'=>1,'campus_id'=>2],[],[1,2],[1])))->toThrow(ValidationException::class);
    $foreign=ScholarshipStudentRank::where('student_enrollment_id',3)->firstOrFail();
    expect(fn()=>$controller->saveRank(scholarshipRequest('POST',['academic_year_id'=>1,'campus_id'=>2,'enrollment_id'=>3,'rank_id'=>$foreign->id,'rank'=>1,'eoy_average'=>96],[],[1,2],[1])))->toThrow(ValidationException::class);
    expect(fn()=>$controller->removeRank(scholarshipRequest('POST',[],[],[1,2],[1]),$foreign))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect($foreign->fresh()->eoy_average)->toBe(95.0);
});

it('lets Central staff filter all campuses and manage permitted ranks using Central grants',function(){
    DB::table('tb_school_info')->insert(['id'=>3,'campus_name_en'=>'CEN','status'=>1]);
    $request=fn($method='GET',$data=[])=>scholarshipRequest($method,$data,[],[3],[3],[3]);
    $controller=app(ScholarshipController::class);
    $data=$controller->index($request('GET',['academic_year_id'=>1]))->getData();
    expect($data['campuses']->pluck('id')->all())->toBe([1,2]);
    $students=$controller->students($request('GET',['academic_year_id'=>1,'campus_id'=>2]))->getData(true)['data'];
    expect(array_column($students,'id'))->toBe([3]);
    $controller->saveRank($request('POST',['academic_year_id'=>1,'campus_id'=>2,'enrollment_id'=>3,'rank'=>1,'eoy_average'=>95]));
    $record=ScholarshipStudentRank::firstOrFail();
    $controller->saveRank($request('POST',['academic_year_id'=>1,'campus_id'=>2,'enrollment_id'=>3,'rank_id'=>$record->id,'rank'=>1,'eoy_average'=>96]));
    expect($record->fresh()->eoy_average)->toBe(96.0);
    $controller->removeRank($request('POST'),$record->fresh());
    expect(ScholarshipStudentRank::count())->toBe(0);
});

it('keeps Central cross-campus access subject to the tab and action grants',function(){
    DB::table('tb_school_info')->insert(['id'=>3,'campus_name_en'=>'Central Office','status'=>1]);
    $request=scholarshipRequest('GET',[],['western-scholarship.assign-rank'],[3],[3],[3]);
    expect(\App\Support\ScholarshipPermissions::allows($request->user(),'tab-assign-rank',2))->toBeTrue()
        ->and(\App\Support\ScholarshipPermissions::allows($request->user(),'assign-rank',2))->toBeFalse();
});

it('normalizes scholarship actions under their tab permissions',function(){
    $migration=require database_path('migrations/2026_10_14_000005_add_scholarship_permissions.php');$migration->up();
    foreach(['students.view','western-scholarship.view'] as $code)
        \App\Models\Permission::firstOrCreate(['code'=>$code],['module'=>str_starts_with($code,'students.')?'students':'western-scholarship','action'=>'view','name'=>$code]);
    $permissions=\App\Models\Permission::all();$ids=$permissions->keyBy('code');
    $selected=[$ids['students.view']->id,$ids['western-scholarship.view']->id,$ids['western-scholarship.assign-rank']->id];
    expect(\App\Support\PermissionHierarchy::normalizeIds($selected,$permissions))->not->toContain($ids['western-scholarship.assign-rank']->id);
    $selected[]=$ids['western-scholarship.tab-assign-rank']->id;
    expect(\App\Support\PermissionHierarchy::normalizeIds($selected,$permissions))->toContain($ids['western-scholarship.assign-rank']->id);
    $html=view('partials.permission-tree',['permissionHierarchy'=>\App\Support\PermissionHierarchy::tree($permissions),'assignedPermissions'=>$permissions,'permissionPrefix'=>'scholarship-test'])->render();
    foreach(\App\Support\ScholarshipPermissions::TABS as $tabPermission)
        expect($html)->toContain('data-permission-code="western-scholarship.'.$tabPermission.'"');
    expect($html)->toContain('data-requires-permission="western-scholarship.tab-assign-rank"');
});

it('uses real campus assignments and Central-scoped role grants for cross-campus access',function(){
    DB::table('tb_school_info')->insert(['id'=>3,'campus_name_en'=>'CEN','status'=>1]);
    DB::table('access_roles')->insert([['id'=>1,'code'=>'campus-registrar'],['id'=>2,'code'=>'central-registrar']]);
    $codes=['students.view','western-scholarship.view','western-scholarship.tab-assign-rank','western-scholarship.assign-rank'];
    foreach($codes as $code){
        $id=DB::table('access_permissions')->insertGetId(['code'=>$code,'module'=>'western-scholarship','action'=>substr($code,strrpos($code,'.')+1),'name'=>$code]);
        foreach([1,2] as $role)DB::table('access_role_permissions')->insert(['role_id'=>$role,'permission_id'=>$id]);
    }
    DB::table('access_user_roles')->insert([['user_id'=>1,'role_id'=>1,'campus_id'=>1],['user_id'=>2,'role_id'=>2,'campus_id'=>3]]);
    DB::table('access_user_campuses')->where('user_id',2)->update(['campus_id'=>3]);
    $staff=User::findOrFail(1);$central=User::findOrFail(2);
    expect(\App\Support\ScholarshipPermissions::allows($staff,'assign-rank',1))->toBeTrue()
        ->and(\App\Support\ScholarshipPermissions::allows($staff,'assign-rank',2))->toBeFalse()
        ->and($central->canAccessCampus(2))->toBeFalse()
        ->and(\App\Support\ScholarshipPermissions::allows($central,'assign-rank',2))->toBeTrue();
    DB::table('access_roles')->where('id',1)->update(['is_global'=>true]);
    expect(\App\Support\ScholarshipPermissions::allows(User::findOrFail(1),'assign-rank',2))->toBeTrue();
    DB::table('access_roles')->where('id',1)->update(['status'=>false]);
    expect(\App\Support\ScholarshipPermissions::allows(User::findOrFail(1),'assign-rank',2))->toBeFalse();
});

it('registers tab permissions idempotently and preserves existing grants without granting new actions',function(){
    DB::table('access_roles')->insert([['id'=>1,'code'=>'super-admin'],['id'=>2,'code'=>'registrar']]);
    foreach(['view','scoring'] as $action){
        $id=DB::table('access_permissions')->insertGetId(['code'=>'western-scholarship.'.$action,'module'=>'western-scholarship','action'=>$action,'name'=>$action]);
        DB::table('access_role_permissions')->insert(['role_id'=>2,'permission_id'=>$id]);
        if($action==='scoring'){
            DB::table('access_department_permissions')->insert(['department_id'=>9,'permission_id'=>$id]);
            DB::table('access_user_permission_overrides')->insert(['user_id'=>1,'permission_id'=>$id,'allowed'=>true]);
            DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>$id,'allowed'=>false]);
        }
    }
    $migration=require database_path('migrations/2026_10_15_000002_add_scholarship_tab_permissions.php');$migration->up();$migration->up();
    $codes=DB::table('access_role_permissions')->join('access_permissions','access_permissions.id','=','permission_id')->where('role_id',2)->pluck('code')->all();
    expect($codes)->toContain('western-scholarship.view','western-scholarship.scoring','western-scholarship.tab-assign-rank','western-scholarship.tab-scoring')
        ->not->toContain('western-scholarship.settings','western-scholarship.tab-settings','western-scholarship.assign-rank');
    expect(count($codes))->toBe(4);
    $tabId=DB::table('access_permissions')->where('code','western-scholarship.tab-scoring')->value('id');
    expect(DB::table('access_department_permissions')->where(['department_id'=>9,'permission_id'=>$tabId])->count())->toBe(1)
        ->and(DB::table('access_user_permission_overrides')->where(['user_id'=>1,'permission_id'=>$tabId,'allowed'=>true])->count())->toBe(1)
        ->and(DB::table('access_user_permission_overrides')->where(['user_id'=>2,'permission_id'=>$tabId])->exists())->toBeFalse();
    expect(DB::table('access_permissions')->whereIn('action',array_values(\App\Support\ScholarshipPermissions::TABS))->count())->toBe(count(\App\Support\ScholarshipPermissions::TABS));
});

it('shows the selected years assigned ranks across permitted campuses before a campus is chosen',function(){
    foreach([1,2,3]as$id)ScholarshipStudentRank::create(['student_enrollment_id'=>$id,'rank'=>$id,'assigned_by'=>1]);
    $controller=app(ScholarshipController::class);
    $data=$controller->index(scholarshipRequest('GET',['academic_year_id'=>1],[],[1,2]))->getData();
    expect($data['records']->total())->toBe(2)->and($data['filters']['campus_id']??null)->toBeNull()
        ->and($data['campuses']->pluck('id')->all())->toBe([1,2])->and($data['grades'])->toHaveCount(0);
    $data=$controller->index(scholarshipRequest('GET',['academic_year_id'=>2],[],[1,2]))->getData();
    expect($data['records']->pluck('student_enrollment_id')->all())->toBe([2])->and($data['campuses']->pluck('id')->all())->toBe([1]);
});

it('refreshes campus and combined grade choices and the rank list according to the parent filters',function(){
    foreach([1,2,3]as$id)ScholarshipStudentRank::create(['student_enrollment_id'=>$id,'rank'=>$id,'assigned_by'=>1]);
    $controller=app(ScholarshipController::class);
    $load=fn($filters)=>$controller->rankFilters(scholarshipRequest('GET',$filters,[],[1,2]))->getData(true);
    $year=$load(['academic_year_id'=>1]);
    expect(array_column($year['campuses'],'id'))->toBe([1,2])->and($year['grades'])->toBe([])->and($year['html'])->toContain('CHEAN','SECOND STUDENT');
    $campus=$load(['academic_year_id'=>1,'campus_id'=>2]);
    expect(array_column($campus['grades'],'id'))->toBe(['1:1'])->and(array_column($campus['grades'],'label'))->toBe(['3A'])->and($campus['html'])->toContain('SECOND STUDENT')->not->toContain('CHEAN');
    $grade=$load(['academic_year_id'=>1,'campus_id'=>2,'grade_class'=>'1:1']);
    expect($grade['filters']['grade_id'])->toBe(1)->and($grade['filters']['class_id'])->toBe(1)->and($grade)->not->toHaveKey('classes');
    $otherYear=$load(['academic_year_id'=>2,'campus_id'=>2,'grade_id'=>1,'class_id'=>1,'enrollment_id'=>3]);
    expect(array_column($otherYear['campuses'],'id'))->toBe([1])->and($otherYear['filters']['campus_id']??null)->toBeNull()
        ->and($otherYear['filters']['grade_id']??null)->toBeNull()->and($otherYear['filters']['enrollment_id']??null)->toBeNull()
        ->and($otherYear['html'])->toContain('CHEAN')->not->toContain('SECOND STUDENT');
});

it('filters assigned ranks by the selected student without exposing another campus or year',function(){
    foreach([1,2,3]as$id)ScholarshipStudentRank::create(['student_enrollment_id'=>$id,'rank'=>$id,'assigned_by'=>1]);
    $controller=app(ScholarshipController::class);
    $own=$controller->rankFilters(scholarshipRequest('GET',['academic_year_id'=>1,'campus_id'=>1,'grade_class'=>'1:1','enrollment_id'=>1]))->getData(true);
    expect($own['html'])->toContain('CHEAN')->not->toContain('SECOND STUDENT')->and(array_column($own['campuses'],'id'))->toBe([1]);
    foreach([2,3]as$id){
        $foreign=$controller->rankFilters(scholarshipRequest('GET',['academic_year_id'=>1,'campus_id'=>1,'grade_class'=>'1:1','enrollment_id'=>$id]))->getData(true);
        expect($foreign['html'])->toContain('No student ranks assigned')->not->toContain('CHEAN','SECOND STUDENT');
    }
});

it('keeps AJAX rank results read-only when seats are confirmed and respects assignment permissions',function(){
    ScholarshipStudentRank::create(['student_enrollment_id'=>1,'rank'=>1,'assigned_by'=>1]);
    $controller=app(ScholarshipController::class);
    $denied=$controller->rankFilters(scholarshipRequest('GET',['academic_year_id'=>1],['western-scholarship.assign-rank']))->getData(true);
    expect($denied['html'])->toContain('CHEAN')->not->toContain('Edit Rank','ranks/1/remove');
    \App\Models\ScholarshipExam::create(['name'=>'2025-2026','academic_year_id'=>1,'exam_date'=>'2026-11-20','campus_ids'=>[1],'eligible_ranks'=>[],'seats_locked_at'=>now()]);
    $locked=$controller->rankFilters(scholarshipRequest('GET',['academic_year_id'=>1]))->getData(true);
    expect($locked['ranks_locked'])->toBeTrue()->and($locked['html'])->not->toContain('Edit Rank','ranks/1/remove');
});

it('rejects unauthorized campus and summer requests to the dependent filter endpoint',function(){
    $controller=app(ScholarshipController::class);
    expect(fn()=>$controller->rankFilters(scholarshipRequest('GET',['academic_year_id'=>1,'campus_id'=>2])))->toThrow(ValidationException::class);
    DB::table('tb_academic_year')->where('id',2)->update(['period_type'=>'summer']);
    expect(fn()=>$controller->rankFilters(scholarshipRequest('GET',['academic_year_id'=>2])))->toThrow(ValidationException::class);
    expect(fn()=>$controller->rankFilters(scholarshipRequest('GET',['academic_year_id'=>1],['western-scholarship.view'])))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('uses combined grade and class choices to isolate student searches ranks and saved assignments',function(){
    DB::table('tb_class')->insert(['id'=>3,'academic_year_id'=>1,'grade_id'=>1,'class_name'=>'B','class_order'=>2]);
    DB::table('tb_student')->insert(['id'=>3,'student_id'=>'2026003','full_name_en'=>'OTHER CLASS STUDENT']);
    DB::table('tb_student_enrollment')->insert(['id'=>4,'student_id'=>3,'academic_year_id'=>1,'campus_id'=>1,'grade_id'=>1,'class_id'=>3]);
    ScholarshipStudentRank::create(['student_enrollment_id'=>1,'rank'=>1,'assigned_by'=>1]);
    ScholarshipStudentRank::create(['student_enrollment_id'=>4,'rank'=>2,'assigned_by'=>1]);
    $controller=app(ScholarshipController::class);
    $options=$controller->rankFilters(scholarshipRequest('GET',['academic_year_id'=>1,'campus_id'=>1]))->getData(true);
    expect(array_column($options['grades'],'label'))->toBe(['3A','3B'])->and(array_column($options['grades'],'id'))->toBe(['1:1','1:3']);
    $filters=['academic_year_id'=>1,'campus_id'=>1,'grade_class'=>'1:3'];
    $students=$controller->students(scholarshipRequest('GET',$filters))->getData(true);
    expect(array_column($students['data'],'id'))->toBe([]);
    $ranks=$controller->rankFilters(scholarshipRequest('GET',$filters))->getData(true);
    expect($ranks['html'])->toContain('OTHER CLASS STUDENT')->not->toContain('CHEAN');
    expect(fn()=>$controller->saveRank(scholarshipRequest('POST',$filters+['enrollment_id'=>1,'rank'=>3])))->toThrow(ValidationException::class);
    $controller->saveRank(scholarshipRequest('POST',$filters+['enrollment_id'=>4,'rank'=>3,'eoy_average'=>90,'rank_id'=>ScholarshipStudentRank::where('student_enrollment_id',4)->value('id')]));
    expect(ScholarshipStudentRank::where('student_enrollment_id',4)->value('rank'))->toBe(3);
    $data=$controller->index(scholarshipRequest('GET',$filters+['enrollment_id'=>4]))->getData();
    expect($data['filters']['grade_class'])->toBe('1:3')->and($data['selected']->id)->toBe(4)->and($data['selectedRank'])->toBe(3);
});

it('saves required EOY averages including zero and preserves edit values without mixing academic years',function(){
    DB::table('tb_session')->insert(['id'=>1,'session_name'=>'Full-Day','session_short_name'=>'III']);
    DB::table('tb_student_enrollment')->where('id',1)->update(['session_id'=>1]);
    $controller=app(ScholarshipController::class);
    $filters=['academic_year_id'=>1,'campus_id'=>1,'grade_class'=>'1:1','enrollment_id'=>1];
    $response=$controller->saveRank(scholarshipRequest('POST',$filters+['rank'=>1,'eoy_average'=>87.25]));
    expect(session('scholarship_rank_saved'))->toBe(1);
    parse_str(parse_url($response->getTargetUrl(),PHP_URL_QUERY),$redirectFilters);
    expect($redirectFilters)->toMatchArray(['academic_year_id'=>'1','campus_id'=>'1','grade_class'=>'1:1'])->not->toHaveKey('enrollment_id');
    $controller->saveRank(scholarshipRequest('POST',['academic_year_id'=>2,'campus_id'=>1,'enrollment_id'=>2,'rank'=>2,'eoy_average'=>100]));
    $data=$controller->index(scholarshipRequest('GET',$filters))->getData();
    expect($data['selectedEoyAverage'])->toBe(87.25)->and($data['selectedRank'])->toBe(1);
    $students=$controller->students(scholarshipRequest('GET',$filters))->getData(true);
    expect($students['data'])->toBe([]);
    $list=$controller->rankFilters(scholarshipRequest('GET',$filters))->getData(true);
    expect($list['html'])->toContain('87.25','EOY Avg.','Student Info','<th class="text-center">Class Rank</th>','Assigned By','Rank Officer','BCH-3A-III','ti-trash','title="Delete rank"');
    $controller->saveRank(scholarshipRequest('POST',$filters+['rank'=>3,'eoy_average'=>0,'rank_id'=>ScholarshipStudentRank::where('student_enrollment_id',1)->value('id')]));
    expect(ScholarshipStudentRank::where('student_enrollment_id',1)->first()->eoy_average)->toBe(0.0)
        ->and(ScholarshipStudentRank::where('student_enrollment_id',2)->first()->eoy_average)->toBe(100.0);
});

it('rejects invalid EOY averages without saving a rank',function($average){
    app(ScholarshipController::class)->saveRank(scholarshipRequest('POST',['academic_year_id'=>1,'campus_id'=>1,'enrollment_id'=>1,'rank'=>1,'eoy_average'=>$average]));
})->with([null,'',' ',-0.01,100.01,90.123,'NaN','invalid'])->throws(ValidationException::class);

it('sorts assigned ranks by campus grade class and rank before pagination',function(){
    DB::table('tb_class')->insert([
        ['id'=>3,'academic_year_id'=>1,'grade_id'=>2,'class_name'=>'A','class_order'=>1],
        ['id'=>4,'academic_year_id'=>1,'grade_id'=>1,'class_name'=>'B','class_order'=>1],
    ]);
    $groups=[];$next=100;
    // Deliberately insert campuses, grades, classes and ranks in reverse display order.
    foreach([[2,1,1],[1,2,3],[1,1,4],[1,1,1]] as [$campus,$grade,$class]){
        foreach([3,2,1] as $rank){
            $id=$next++;
            DB::table('tb_student')->insert(['id'=>$id,'student_id'=>'SORT-'.$id,'full_name_en'=>'SORT STUDENT '.$id]);
            DB::table('tb_student_enrollment')->insert(['id'=>$id,'student_id'=>$id,'academic_year_id'=>1,'campus_id'=>$campus,'grade_id'=>$grade,'class_id'=>$class]);
            $record=ScholarshipStudentRank::create(['student_enrollment_id'=>$id,'rank'=>$rank,'assigned_by'=>1]);
            $groups[$campus.':'.$grade.':'.$class][$rank]=$record->id;
        }
    }
    $expected=[];
    foreach(['1:1:1','1:1:4','1:2:3','2:1:1'] as $key)foreach([1,2,3] as $rank)$expected[]=$groups[$key][$rank];
    foreach(range(1,12) as $extra){
        $id=$next++;
        DB::table('tb_student')->insert(['id'=>$id,'student_id'=>'SORT-'.$id,'full_name_en'=>'SORT STUDENT '.$id]);
        DB::table('tb_student_enrollment')->insert(['id'=>$id,'student_id'=>$id,'academic_year_id'=>1,'campus_id'=>2,'grade_id'=>1,'class_id'=>1]);
        $expected[]=ScholarshipStudentRank::create(['student_enrollment_id'=>$id,'rank'=>3,'assigned_by'=>1])->id;
    }
    $controller=app(ScholarshipController::class);
    foreach([1,2,3] as $page){
        $request=scholarshipRequest('GET',['academic_year_id'=>1,'page'=>$page],[],[1,2]);
        $records=$controller->index($request)->getData()['records'];
        expect($records->pluck('id')->all())->toBe(array_slice($expected,($page-1)*10,10));
        expect($records->firstItem())->toBe(($page-1)*10+1);
        $html=$controller->rankFilters($request)->getData(true)['html'];
        expect($html)->toContain('SORT STUDENT '.ScholarshipStudentRank::find($expected[($page-1)*10])->student_enrollment_id);
    }
    foreach(['all',10,25,50,75,100] as $size){
        $request=scholarshipRequest('GET',['academic_year_id'=>1,'per_page'=>$size],[],[1,2]);
        $records=$controller->index($request)->getData()['records'];
        $wanted=$size==='all'?count($expected):min($size,count($expected));
        expect($records->count())->toBe($wanted)->and($records->total())->toBe(count($expected));
        expect($records->pluck('id')->all())->toBe(array_slice($expected,0,$wanted));
        $payload=$controller->rankFilters($request)->getData(true);
        expect($payload['filters']['per_page'])->toBe($size)->and($payload['html'])->toContain('data-rank-pagination=');
        if($size==='all')expect($records->lastPage())->toBe(1);
    }
    $all=$controller->index(scholarshipRequest('GET',['academic_year_id'=>1,'per_page'=>'all','page'=>3],[],[1,2]))->getData()['records'];
    expect($all->currentPage())->toBe(1)->and($all->count())->toBe(count($expected));
    $filtered=$controller->index(scholarshipRequest('GET',['academic_year_id'=>1,'campus_id'=>1,'grade_class'=>'1:4'],[],[1,2]))->getData()['records'];
    expect($filtered->pluck('id')->all())->toBe(array_map(fn($rank)=>$groups['1:1:4'][$rank],[1,2,3]));
});

it('rejects unsupported rank page sizes',function($size){
    app(ScholarshipController::class)->rankFilters(scholarshipRequest('GET',['academic_year_id'=>1,'per_page'=>$size]));
})->with([0,-1,20,9999,'invalid'])->throws(ValidationException::class);

it('keeps All rank results scoped to the users campus',function(){
    $controller=app(ScholarshipController::class);
    $empty=$controller->index(scholarshipRequest('GET',['academic_year_id'=>1,'per_page'=>'all','page'=>4]))->getData()['records'];
    expect($empty->total())->toBe(0)->and($empty->currentPage())->toBe(1)->and($empty->lastPage())->toBe(1);
    foreach([1,3] as $enrollment)ScholarshipStudentRank::create(['student_enrollment_id'=>$enrollment,'rank'=>1,'assigned_by'=>1]);
    $records=$controller->index(scholarshipRequest('GET',['academic_year_id'=>1,'per_page'=>'all']))->getData()['records'];
    expect($records->total())->toBe(1)->and($records->pluck('student_enrollment_id')->all())->toBe([1]);
    $payload=$controller->rankFilters(scholarshipRequest('GET',['academic_year_id'=>1,'per_page'=>'all']))->getData(true);
    expect($payload['html'])->toContain('CHEAN')->not->toContain('SECOND STUDENT');
});

it('hides assigned students blocks duplicate creates and permits explicit edits',function(){
    $controller=app(ScholarshipController::class);
    $values=['academic_year_id'=>1,'campus_id'=>1,'enrollment_id'=>1,'rank'=>1,'eoy_average'=>95];
    $controller->saveRank(scholarshipRequest('POST',$values));
    $record=ScholarshipStudentRank::firstOrFail();
    expect($controller->students(scholarshipRequest('GET',['academic_year_id'=>1,'campus_id'=>1]))->getData(true)['data'])->toBe([]);
    DB::table('tb_student_enrollment')->insert(['id'=>4,'student_id'=>1,'academic_year_id'=>1,'campus_id'=>2,'grade_id'=>1,'class_id'=>1]);
    expect(array_column($controller->students(scholarshipRequest('GET',['academic_year_id'=>1,'campus_id'=>2],[],[1,2]))->getData(true)['data'],'id'))->toBe([3]);
    try{$controller->saveRank(scholarshipRequest('POST',array_replace($values,['eoy_average'=>96])));$this->fail('Duplicate create was accepted.');}
    catch(ValidationException $error){expect($error->errors())->toHaveKey('enrollment_id');}
    expect($record->fresh()->eoy_average)->toBe(95.0);
    $request=scholarshipRequest('POST',array_replace($values,['rank_id'=>$record->id,'eoy_average'=>96]));
    $request->headers->set('Accept','application/json');
    expect($controller->saveRank($request)->getData(true)['message'])->toBe('Class rank saved successfully.');
    expect(ScholarshipStudentRank::count())->toBe(1)->and($record->fresh()->eoy_average)->toBe(96.0);
    $controller->removeRank(scholarshipRequest('POST'),$record->fresh());
    expect(array_column($controller->students(scholarshipRequest('GET',['academic_year_id'=>1,'campus_id'=>1]))->getData(true)['data'],'id'))->toBe([1]);
});

it('rejects equal and reversed EOY averages in the same class and group',function($peerRank,$peerAverage,$rank,$average){
    ScholarshipStudentRank::create(['student_enrollment_id'=>1,'rank'=>$peerRank,'eoy_average'=>$peerAverage,'assigned_by'=>1]);
    DB::table('tb_student_enrollment')->where('id',3)->update(['campus_id'=>1]);
    try{app(ScholarshipController::class)->saveRank(scholarshipRequest('POST',['academic_year_id'=>1,'campus_id'=>1,'enrollment_id'=>3,'rank'=>$rank,'eoy_average'=>$average]));$this->fail('Incorrect score order was accepted.');}
    catch(ValidationException $error){expect($error->errors())->toHaveKey('eoy_average');}
    expect(ScholarshipStudentRank::count())->toBe(1);
})->with([[1,90,2,90],[1,90,2,95],[2,90,1,85],[3,80,1,80],[2,90,3,92],[3,80,2,75]]);

it('allows valid EOY ordering regardless of the order students are assigned',function($peerRank,$peerAverage,$rank,$average){
    ScholarshipStudentRank::create(['student_enrollment_id'=>1,'rank'=>$peerRank,'eoy_average'=>$peerAverage,'assigned_by'=>1]);
    DB::table('tb_student_enrollment')->where('id',3)->update(['campus_id'=>1]);
    app(ScholarshipController::class)->saveRank(scholarshipRequest('POST',['academic_year_id'=>1,'campus_id'=>1,'enrollment_id'=>3,'rank'=>$rank,'eoy_average'=>$average]));
    expect(ScholarshipStudentRank::where('student_enrollment_id',3)->first()->eoy_average)->toBe((float)$average);
})->with([[1,90,2,89.99],[2,90,1,90.01],[3,80,2,80.01],[2,90,3,0]]);

it('checks EOY ordering on edits without changing the saved value on failure',function(){
    ScholarshipStudentRank::create(['student_enrollment_id'=>1,'rank'=>1,'eoy_average'=>95,'assigned_by'=>1]);
    DB::table('tb_student_enrollment')->where('id',3)->update(['campus_id'=>1]);
    $record=ScholarshipStudentRank::create(['student_enrollment_id'=>3,'rank'=>2,'eoy_average'=>90,'assigned_by'=>1]);
    try{app(ScholarshipController::class)->saveRank(scholarshipRequest('POST',['academic_year_id'=>1,'campus_id'=>1,'enrollment_id'=>3,'rank_id'=>$record->id,'rank'=>2,'eoy_average'=>96]));$this->fail('Incorrect edit was accepted.');}
    catch(ValidationException $error){expect($error->errors())->toHaveKey('eoy_average');}
    expect($record->fresh()->eoy_average)->toBe(90.0);
});

it('does not compare EOY averages across different campuses classes or groups',function($scope){
    ScholarshipStudentRank::create(['student_enrollment_id'=>1,'rank'=>1,'eoy_average'=>90,'assigned_by'=>1]);
    if($scope==='class'){
        DB::table('tb_class')->insert(['id'=>3,'academic_year_id'=>1,'grade_id'=>1,'class_name'=>'B']);
        DB::table('tb_student_enrollment')->where('id',3)->update(['campus_id'=>1,'class_id'=>3]);
    }
    if($scope==='group'){
        DB::table('tb_session')->insert(['id'=>1,'session_name'=>'Full-Day','session_short_name'=>'III']);
        DB::table('tb_student_enrollment')->where('id',3)->update(['campus_id'=>1,'session_id'=>1]);
    }
    $campus=$scope==='campus'?2:1;
    app(ScholarshipController::class)->saveRank(scholarshipRequest('POST',['academic_year_id'=>1,'campus_id'=>$campus,'enrollment_id'=>3,'rank'=>2,'eoy_average'=>95],[],[1,2]));
    expect(ScholarshipStudentRank::where('student_enrollment_id',3)->first()->eoy_average)->toBe(95.0);
})->with(['campus','class','group']);

it('counts and exports all filtered ranks across pages without including other campuses or years',function(){
    foreach(range(101,112) as $id){
        DB::table('tb_student')->insert(['id'=>$id,'student_id'=>'EXPORT-'.$id,'full_name_en'=>'EXPORT STUDENT '.$id,'full_name_kh'=>'ចាន់']);
        DB::table('tb_student_enrollment')->insert(['id'=>$id,'student_id'=>$id,'academic_year_id'=>1,'campus_id'=>1,'grade_id'=>1,'class_id'=>1]);
        ScholarshipStudentRank::create(['student_enrollment_id'=>$id,'rank'=>($id-101)%3+1,'eoy_average'=>95,'assigned_by'=>1]);
    }
    foreach([2,3] as $id)ScholarshipStudentRank::create(['student_enrollment_id'=>$id,'rank'=>1,'assigned_by'=>1]);
    $controller=app(ScholarshipController::class);
    $filters=['academic_year_id'=>1,'campus_id'=>1,'grade_class'=>'1:1','per_page'=>10,'page'=>2];
    $view=$controller->index(scholarshipRequest('GET',$filters))->getData();
    expect($view['records']->count())->toBe(2)->and($view['rankSummary'])->toBe(['total'=>12,1=>4,2=>4,3=>4]);
    $html=$controller->rankFilters(scholarshipRequest('GET',$filters))->getData(true)['html'];
    expect($html)->toContain('data-rank-count="total">12','data-rank-count="1">4','data-rank-count="2">4','data-rank-count="3">4','/ranks/report/print?','/ranks/report/xlsx?','/ranks/report/pdf?');
    $print=$controller->rankReport(scholarshipRequest('GET',$filters),'print');
    expect($print->getData()['rows'])->toHaveCount(12)->and($print->render())->toContain('EXPORT STUDENT 101','EXPORT STUDENT 112','>3A</td>','>BCH</td>','List of Outstanding Students');
    $excel=$controller->rankReport(scholarshipRequest('GET',$filters),'xlsx');
    expect($excel->headers->get('Content-Type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $path=tempnam(sys_get_temp_dir(),'scholarship-test-');file_put_contents($path,$excel->getContent());
    try{
        $zip=new ZipArchive();$zip->open($path);$sheet=$zip->getFromName('xl/worksheets/sheet1.xml');$zip->close();
        expect($sheet)->toContain('EXPORT-101','EXPORT-112','List of Outstanding Students','BCH Campus','គោត្តនាម-នាម')->not->toContain('SECOND STUDENT','2026-2027','EOY Avg.','Assigned By');
    }finally{unlink($path);}
    $pdf=$controller->rankReport(scholarshipRequest('GET',$filters),'pdf');
    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf')->and($pdf->getContent())->toStartWith('%PDF-');
    $single=$controller->rankReport(scholarshipRequest('GET',$filters+['enrollment_id'=>101]),'print')->getData();
    expect($single['rows'])->toHaveCount(1)->and($single['rankSummary'])->toBe(['total'=>1,1=>1,2=>0,3=>0]);
});

it('requires rank tab and export permissions for every rank export format',function($format,$permission){
    $request=scholarshipRequest('GET',['academic_year_id'=>1],[$permission]);
    expect(fn()=>app(ScholarshipController::class)->rankReport($request,$format))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    if($permission==='western-scholarship.export-ranks'){
        $data=app(ScholarshipController::class)->index($request)->getData();
        expect($data['canExportRanks'])->toBeFalse();
        expect(app(ScholarshipController::class)->rankFilters($request)->getData(true)['html'])->not->toContain('/ranks/report/print?','/ranks/report/xlsx?','/ranks/report/pdf?');
    }
})->with(['print','xlsx','pdf'])->with(['western-scholarship.export-ranks','western-scholarship.tab-assign-rank']);

it('rejects forged campuses on rank exports and returns zero counts for an empty filter',function(){
    $controller=app(ScholarshipController::class);
    expect(fn()=>$controller->rankReport(scholarshipRequest('GET',['academic_year_id'=>1,'campus_id'=>2]),'print'))->toThrow(ValidationException::class);
    $data=$controller->rankReport(scholarshipRequest('GET',['academic_year_id'=>1]),'print')->getData();
    expect($data['rows'])->toBe([])->and($data['rankSummary'])->toBe(['total'=>0,1=>0,2=>0,3=>0]);
});

it('uses Branding School Logo 1 and separates gender grade group and campus in the sample report',function(){
    Schema::create('tb_branding_setting',function(Blueprint $t){$t->id();$t->string('report_logo_1_path')->nullable();$t->string('report_logo_2_path')->nullable();$t->timestamps();});
    DB::table('tb_branding_setting')->insert(['report_logo_1_path'=>'school_logo/student_profile_report_logo.png','report_logo_2_path'=>'school_logo/wis_logo.png']);
    DB::table('tb_student')->where('id',1)->update(['gender'=>'Female']);
    DB::table('tb_session')->insert(['id'=>1,'session_name'=>'Full-Day','session_short_name'=>'III']);
    DB::table('tb_student_enrollment')->where('id',1)->update(['session_id'=>1]);
    ScholarshipStudentRank::create(['student_enrollment_id'=>1,'rank'=>1,'assigned_by'=>1]);
    $request=scholarshipRequest('GET',['academic_year_id'=>1]);$request->user()->name='REPORT PREPARER';
    $view=app(ScholarshipController::class)->rankReport($request,'print');$data=$view->getData();
    expect($data['logoSrc'])->toBe('data:image/png;base64,'.base64_encode(file_get_contents(storage_path('app/public/school_logo/student_profile_report_logo.png'))));
    expect($data['pages'][0][0])->toMatchArray(['gender'=>'F','grade'=>'3A','group'=>'III','campus'=>'BCH']);
    expect($view->render())->toContain('School Logo 1','KINGDOM OF CAMBODIA','List of Outstanding Students','Page 1 of 1','Total students: 1','REPORT PREPARER','Prepared by','Approved by')->not->toContain('CHEA Phearumn','CHOEUNG Sengheng');
    DB::table('tb_branding_setting')->update(['report_logo_1_path'=>'missing-logo.png']);
    expect(app(\App\Support\ScholarshipExport::class)->rankLogo())->toBeNull();
});

it('keeps all rows numbered across report pages with room for final signatures',function($total){
    $rows=$total?array_map(fn($i)=>['number'=>$i],range(1,$total)):[];
    $pages=app(\App\Support\ScholarshipExport::class)->rankPages($rows);
    expect(array_merge(...$pages))->toBe($rows);
    expect(count($pages[0]))->toBeLessThanOrEqual(25);
    foreach(array_slice($pages,1) as $page)expect(count($page))->toBeLessThanOrEqual(30);
    expect(count($pages[array_key_last($pages)]))->toBeLessThanOrEqual(count($pages)===1?20:26);
    if($total===75)expect(array_map('count',$pages))->toBe([25,30,20]);
})->with([0,1,20,21,25,26,49,50,55,56,75,76,85,100]);

it('renders portrait report pages without separating final signatures onto a blank page',function($total,$expectedPages){
    $rows=array_map(fn($i)=>['number'=>$i,'id'=>'TEST-'.$i,'name_en'=>'SAMPLE STUDENT '.$i,'name_kh'=>'ចាន់ សុភា','gender'=>'F','grade'=>'3A','group'=>'III','rank'=>($i-1)%3+1,'campus'=>'BCH'],range(1,$total));
    $export=app(\App\Support\ScholarshipExport::class);
    $data=['pages'=>$export->rankPages($rows),'logoSrc'=>null,'year'=>'2025-2026','rankSummary'=>['total'=>$total],'preparedBy'=>'Sample Registrar','reportDate'=>'October 15, 2026','pdfExport'=>true];
    $bytes=$export->ranksPdf($data);
    $reader=new \setasign\Fpdi\PdfReader\PdfReader(new \setasign\Fpdi\PdfParser\PdfParser(\setasign\Fpdi\PdfParser\StreamReader::createByString($bytes)));
    expect($reader->getPageCount())->toBe($expectedPages);
    $size=$reader->getPage(1)->getBoundary(\setasign\Fpdi\PdfReader\PageBoundaries::MEDIA_BOX);
    expect($size->getWidth())->toBeLessThan($size->getHeight());
    $html=view('western-scholarship.ranks-print',$data)->render();
    expect(substr_count($html,'Prepared by'))->toBe(1)->and(substr_count($html,'<h1>'))->toBe(1)->and(substr_count($html,'<thead>'))->toBe($expectedPages);
})->with([[75,3],[51,2],[20,1],[25,2],[21,2]]);

it('keeps short first-page lists filled when final signatures need another page',function(){
    $rows=array_map(fn($i)=>['number'=>$i,'campus'=>'BCH'],range(1,25));
    $pages=app(\App\Support\ScholarshipExport::class)->rankPages($rows);
    expect(array_map('count',$pages))->toBe([22,3]);
    expect(array_column(array_merge(...$pages),'number'))->toBe(range(1,25));
    expect($pages[0][0]['show_campus_header'])->toBeTrue()->and($pages[1][0]['show_campus_header'])->toBeTrue();
});

it('saves campus print names and independent dates per campus and regular academic year',function(){
    $controller=app(ScholarshipController::class);
    $values=['academic_year_id'=>1,'campus_id'=>1,'prepared_staff_id'=>1,'prepared_date'=>'2026-10-01','checked_staff_id'=>2,'checked_date'=>'2026-10-05','approved_staff_id'=>3,'approved_date'=>'2026-10-08'];
    $controller->saveRankPrintSettings(scholarshipRequest('POST',$values));
    $controller->saveRankPrintSettings(scholarshipRequest('POST',array_replace($values,['academic_year_id'=>2])));
    $read=$controller->rankPrintSettings(scholarshipRequest('GET',['academic_year_id'=>1]))->getData(true);
    expect($read['campus_id'])->toBe(1)->and(array_column($read['campuses'],'id'))->toBe([1]);
    expect($read['campuses'][0]['is_central'])->toBeFalse();
    expect($read['requires_verifier'])->toBeFalse();
    expect($read['values'])->toMatchArray(['prepared_staff_id'=>1,'prepared_by_name'=>'BCH PREPARER','prepared_position'=>'Registrar','prepared_date'=>'2026-10-01','checked_staff_id'=>null,'checked_by_name'=>'','approved_by_name'=>'BCH APPROVER','approved_date'=>'2026-10-08']);
    DB::table('hrm_staff')->where('id',1)->update(['name_en'=>'UPDATED PREPARER']);
    $controller->saveRankPrintSettings(scholarshipRequest('POST',array_replace($values,['prepared_by_name'=>'SPOOFED NAME','prepared_position'=>'SPOOFED POSITION','updated_by'=>999])));
    expect(\App\Models\ScholarshipPrintSetting::count())->toBe(2)->and(\App\Models\ScholarshipPrintSetting::where('academic_year_id',1)->first()->updated_by)->toBe(1);
    ScholarshipStudentRank::create(['student_enrollment_id'=>1,'rank'=>1,'assigned_by'=>1]);
    $html=$controller->rankReport(scholarshipRequest('GET',['academic_year_id'=>1]),'print')->render();
    expect($html)->toContain('UPDATED PREPARER','BCH APPROVER','Registrar','School Principal','October 1, 2026','October 8, 2026','Khmer OS Muol Light','signatures-campus')->not->toContain('SPOOFED','BCH VERIFIER','Checked and Verified by','October 5, 2026');
    expect(\App\Models\ScholarshipPrintSetting::where('academic_year_id',2)->value('prepared_by_name'))->toBe('BCH PREPARER');
});

it('prevents campus staff from reading or overwriting another campus print settings',function($method){
    $data=['academic_year_id'=>1,'campus_id'=>2,'prepared_by_name'=>'OTHER CAMPUS','prepared_date'=>'2026-10-01','approved_by_name'=>'OTHER APPROVER','approved_date'=>'2026-10-08'];
    // A broad accessibleCampuses response must not bypass the actual campus assignment.
    $request=scholarshipRequest($method,$data,[],[1,2],[1]);
    $controller=app(ScholarshipController::class);
    expect(fn()=>$method==='GET'?$controller->rankPrintSettings($request):$controller->saveRankPrintSettings($request))->toThrow(ValidationException::class);
    expect(\App\Models\ScholarshipPrintSetting::count())->toBe(0);
})->with(['GET','POST']);

it('requires rank assignment permission for campus print settings',function($method){
    $controller=app(ScholarshipController::class);
    $request=scholarshipRequest($method,['academic_year_id'=>1,'campus_id'=>1],['western-scholarship.assign-rank']);
    expect(fn()=>$method==='GET'?$controller->rankPrintSettings($request):$controller->saveRankPrintSettings($request))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    if($method==='GET')expect($controller->rankFilters($request)->getData(true)['html'])->not->toContain('data-rank-print-settings');
})->with(['GET','POST']);

it('validates every campus print field and excludes summer settings',function($changes,$field){
    $data=array_replace(['academic_year_id'=>1,'campus_id'=>1,'prepared_staff_id'=>1,'prepared_date'=>'2026-10-01','checked_staff_id'=>2,'checked_date'=>'2026-10-05','approved_staff_id'=>3,'approved_date'=>'2026-10-08'],$changes);
    DB::table('tb_academic_year')->where('id',2)->update(['period_type'=>'summer']);
    try{app(ScholarshipController::class)->saveRankPrintSettings(scholarshipRequest('POST',$data));$this->fail('Invalid print settings accepted.');}
    catch(ValidationException $error){expect($error->errors())->toHaveKey($field);}
    expect(\App\Models\ScholarshipPrintSetting::count())->toBe(0);
})->with([
    [['prepared_staff_id'=>null],'prepared_staff_id'],[['approved_staff_id'=>''],'approved_staff_id'],
    [['prepared_date'=>'2026-02-31'],'prepared_date'],[['approved_date'=>''],'approved_date'],
    [['approved_staff_id'=>999],'approved_staff_id'],[['academic_year_id'=>2],'academic_year_id'],
]);

it('saves campus settings with only a preparer and approver and ignores submitted verifier fields',function(){
    $controller=app(ScholarshipController::class);
    $values=['academic_year_id'=>1,'campus_id'=>1,'prepared_staff_id'=>1,'prepared_date'=>'2026-10-01','approved_staff_id'=>3,'approved_date'=>'2026-10-08'];
    $controller->saveRankPrintSettings(scholarshipRequest('POST',$values));
    $controller->saveRankPrintSettings(scholarshipRequest('POST',$values+['checked_staff_id'=>999,'checked_by_name'=>'FORGED VERIFIER','checked_date'=>'invalid']));
    $setting=\App\Models\ScholarshipPrintSetting::first();
    expect($setting->prepared_by_name)->toBe('BCH PREPARER')->and($setting->approved_by_name)->toBe('BCH APPROVER');
    expect($setting->checked_staff_id)->toBeNull()->and($setting->checked_by_name)->toBeNull()->and($setting->checked_date)->toBeNull()->and($setting->checked_position)->toBeNull();
});

it('requires a valid verifier and date for Central settings',function($changes,$field){
    DB::table('tb_school_info')->insert(['id'=>3,'campus_name_en'=>'CEN','status'=>1]);
    $values=array_replace(['academic_year_id'=>1,'campus_id'=>3,'prepared_staff_id'=>7,'prepared_date'=>'2026-10-01','checked_staff_id'=>8,'checked_date'=>'2026-10-05','approved_staff_id'=>9,'approved_date'=>'2026-10-08'],$changes);
    try{app(ScholarshipController::class)->saveRankPrintSettings(scholarshipRequest('POST',$values,[],[3],[3],[3]));$this->fail('Invalid Central settings accepted.');}
    catch(ValidationException $error){expect($error->errors())->toHaveKey($field);}
    expect(\App\Models\ScholarshipPrintSetting::count())->toBe(0);
})->with([[['checked_staff_id'=>null],'checked_staff_id'],[['checked_date'=>''],'checked_date'],[['checked_date'=>'2026-02-31'],'checked_date'],[['checked_staff_id'=>4],'checked_staff_id']]);

it('prints one Central report grouped by campus with three signatures once at the end',function(){
    DB::table('tb_school_info')->insert(['id'=>3,'campus_name_en'=>'CEN','status'=>1]);
    $controller=app(ScholarshipController::class);
    $settings=['academic_year_id'=>1,'campus_id'=>1,'prepared_staff_id'=>1,'prepared_date'=>'2026-10-01','checked_staff_id'=>2,'checked_date'=>'2026-10-05','approved_staff_id'=>3,'approved_date'=>'2026-10-08'];
    $controller->saveRankPrintSettings(scholarshipRequest('POST',$settings,[],[3],[3],[3]));
    $controller->saveRankPrintSettings(scholarshipRequest('POST',array_replace($settings,['campus_id'=>2,'prepared_staff_id'=>4,'checked_staff_id'=>5,'approved_staff_id'=>6]),[],[3],[3],[3]));
    $controller->saveRankPrintSettings(scholarshipRequest('POST',array_replace($settings,['campus_id'=>3,'prepared_staff_id'=>7,'checked_staff_id'=>8,'approved_staff_id'=>9]),[],[3],[3],[3]));
    $available=$controller->rankPrintSettings(scholarshipRequest('GET',['academic_year_id'=>1,'campus_id'=>2],[],[3],[3],[3]))->getData(true);
    expect(array_column($available['campuses'],'id'))->toContain(1,2)->and($available['values']['prepared_by_name'])->toBe('BKK PREPARER');
    $central=$controller->rankPrintSettings(scholarshipRequest('GET',['academic_year_id'=>1],[],[3],[3],[3]))->getData(true);
    expect($central['campus_id'])->toBe(3)->and($central['requires_verifier'])->toBeTrue()->and($central['values']['checked_by_name'])->toBe('CENTRAL VERIFIER');
    $centralOption=collect($central['campuses'])->firstWhere('id',3);
    expect($centralOption['is_central'])->toBeTrue()->and($centralOption['label'])->toContain('Central','All Campuses','CEN');
    foreach([1,3] as $id)ScholarshipStudentRank::create(['student_enrollment_id'=>$id,'rank'=>1,'assigned_by'=>1]);
    $view=$controller->rankReport(scholarshipRequest('GET',['academic_year_id'=>1],[],[3],[3],[3]),'print');
    $sections=$view->getData()['reportSections'];
    expect($sections)->toHaveCount(1)->and($sections[0]['preparedBy'])->toBe('CENTRAL PREPARER')->and($sections[0]['checkedBy'])->toBe('CENTRAL VERIFIER')->and($sections[0]['total'])->toBe(2);
    $html=$view->render();
    expect($html)->toContain('Campus: BCH','Campus: BKK','CENTRAL PREPARER','CENTRAL VERIFIER','CENTRAL APPROVER','Page 1 of 1','Total students: 2','class="report-campus">All Campuses')->not->toContain('BCH APPROVER','BKK APPROVER');
    expect(substr_count($html,'Prepared by'))->toBe(1)->and(substr_count($html,'Checked and Verified by'))->toBe(1)->and(substr_count($html,'Approved by'))->toBe(1)->and(substr_count($html,'class="campus-group"'))->toBe(2);
    $only=$controller->rankReport(scholarshipRequest('GET',['academic_year_id'=>1,'campus_id'=>1],[],[3],[3],[3]),'print')->render();
    expect($only)->toContain('BCH PREPARER','BCH APPROVER','signatures-campus','class="report-campus">BCH Campus')->not->toContain('BKK PREPARER','Checked and Verified by');
    $campus=$controller->rankReport(scholarshipRequest('GET',['academic_year_id'=>1]),'print')->render();
    expect($campus)->toContain('Campus: BCH','BCH PREPARER','BCH APPROVER','class="report-campus">BCH Campus')->not->toContain('Campus: BKK','CENTRAL PREPARER','BCH VERIFIER','Checked and Verified by');
});

it('reserves space for campus headers and carries every student through grouped pagination',function($total,$groupSize){
    $rows=array_map(fn($i)=>['number'=>$i,'campus'=>'CAMPUS '.intdiv($i-1,$groupSize)],range(1,$total));
    $pages=app(\App\Support\ScholarshipExport::class)->rankPages($rows);
    $restored=[];
    foreach($pages as $index=>$page){
        $previous=null;$headers=0;
        foreach($page as $row){
            expect($row['show_campus_header'])->toBe($previous===null||$previous!==$row['campus']);
            $headers+=(int)$row['show_campus_header'];$previous=$row['campus'];unset($row['show_campus_header'],$row['campus_number']);$restored[]=$row;
        }
        expect(count($page)+$headers)->toBeLessThanOrEqual($index===0?26:31);
        if($index===array_key_last($pages))expect(count($page)+$headers)->toBeLessThanOrEqual($index===0?21:27);
    }
    expect($restored)->toBe($rows);
})->with([[75,59],[50,25],[31,1],[1,1],[20,1],[75,6]]);

it('continues overall and campus numbering across pages and restarts only for each campus',function(){
    $rows=array_map(fn($i)=>['number'=>$i,'campus_id'=>$i<60?1:2,'campus'=>$i<60?'BCH':'BKK'],range(1,75));
    $pages=app(\App\Support\ScholarshipExport::class)->rankPages($rows);
    $all=collect(array_merge(...$pages));
    expect($all->pluck('number')->all())->toBe(range(1,75));
    expect($all->where('campus_id',1)->pluck('campus_number')->all())->toBe(range(1,59));
    expect($all->where('campus_id',2)->pluck('campus_number')->all())->toBe(range(1,16));
    expect($pages[1][0]['campus_number'])->toBe(26)->and($pages[2][0]['campus_number'])->toBe(56);
    $short=app(\App\Support\ScholarshipExport::class)->rankPages(array_slice($rows,0,25));
    expect($short[1][0]['number'])->toBe(23)->and($short[1][0]['campus_number'])->toBe(23);
});

it('keeps existing preparer and approver settings when the verifier has not yet been configured',function(){
    \App\Models\ScholarshipPrintSetting::create(['academic_year_id'=>1,'campus_id'=>1,'prepared_by_name'=>'EXISTING PREPARER','prepared_date'=>'2026-10-01','approved_by_name'=>'EXISTING APPROVER','approved_date'=>'2026-10-08']);
    $controller=app(ScholarshipController::class);
    $read=$controller->rankPrintSettings(scholarshipRequest('GET',['academic_year_id'=>1]))->getData(true)['values'];
    expect($read['checked_by_name'])->toBe('')->and($read['checked_date'])->toBe(now()->toDateString());
    expect($read['prepared_by_name'])->toBe('EXISTING PREPARER')->and($read['approved_by_name'])->toBe('EXISTING APPROVER');
    $html=$controller->rankReport(scholarshipRequest('GET',['academic_year_id'=>1]),'print')->render();
    expect($html)->toContain('EXISTING PREPARER','EXISTING APPROVER','October 1, 2026','October 8, 2026')->not->toContain('Checked and Verified by');
});

it('limits HRM signer choices to active staff in the selected campus including active shared assignments',function(){
    DB::table('hrm_staff')->where('id',2)->update(['employment_status'=>'resigned']);
    DB::table('hrm_staff')->where('id',3)->update(['deleted_at'=>now()]);
    DB::table('hrm_staff_campuses')->insert([['staff_id'=>4,'campus_id'=>1,'status'=>true],['staff_id'=>5,'campus_id'=>1,'status'=>false]]);
    $data=app(ScholarshipController::class)->rankPrintSettings(scholarshipRequest('GET',['academic_year_id'=>1]))->getData(true);
    expect(array_column($data['staff'],'id'))->toBe([1,4]);
    expect(array_keys($data['staff'][0]))->toBe(['id','code','name','name_kh','position']);
    expect($data['staff'][0]['position'])->toBe('Registrar');
    $values=['academic_year_id'=>1,'campus_id'=>1,'prepared_staff_id'=>2,'prepared_date'=>'2026-10-01','checked_staff_id'=>1,'checked_date'=>'2026-10-02','approved_staff_id'=>4,'approved_date'=>'2026-10-03'];
    expect(fn()=>app(ScholarshipController::class)->saveRankPrintSettings(scholarshipRequest('POST',$values)))->toThrow(ValidationException::class);
});

it('retains saved signer names and positions when HRM records change until settings are explicitly saved again',function(){
    $controller=app(ScholarshipController::class);
    $values=['academic_year_id'=>1,'campus_id'=>1,'prepared_staff_id'=>1,'prepared_date'=>'2026-10-01','checked_staff_id'=>2,'checked_date'=>'2026-10-02','approved_staff_id'=>3,'approved_date'=>'2026-10-03'];
    $controller->saveRankPrintSettings(scholarshipRequest('POST',$values));
    DB::table('hrm_staff')->where('id',1)->update(['name_en'=>'NEW HRM NAME']);
    DB::table('access_positions')->where('id',1)->update(['name'=>'NEW HRM POSITION']);
    $before=$controller->rankReport(scholarshipRequest('GET',['academic_year_id'=>1]),'print')->render();
    expect($before)->toContain('BCH PREPARER','Registrar')->not->toContain('NEW HRM NAME','NEW HRM POSITION');
    $controller->saveRankPrintSettings(scholarshipRequest('POST',$values));
    $after=$controller->rankReport(scholarshipRequest('GET',['academic_year_id'=>1]),'print')->render();
    expect($after)->toContain('NEW HRM NAME','NEW HRM POSITION');
});

it('limits Central print signers to CEN staff and campus print signers to their campus',function(){
    DB::table('tb_school_info')->insert(['id'=>3,'campus_name_en'=>'CEN','status'=>1]);
    $controller=app(ScholarshipController::class);
    $central=$controller->rankPrintSettings(scholarshipRequest('GET',['academic_year_id'=>1],[],[3],[3],[3]))->getData(true);
    expect(array_column($central['staff'],'id'))->toBe([9,7,8]);
    $campus=$controller->rankPrintSettings(scholarshipRequest('GET',['academic_year_id'=>1,'campus_id'=>2],[],[3],[3],[3]))->getData(true);
    expect(array_column($campus['staff'],'id'))->toBe([6,4,5]);
    $bch=$controller->rankPrintSettings(scholarshipRequest('GET',['academic_year_id'=>1]))->getData(true);
    expect(array_column($bch['staff'],'id'))->toBe([3,1,2]);
});

it('rejects signers from another campus when saving Central print settings',function($role){
    DB::table('tb_school_info')->insert(['id'=>3,'campus_name_en'=>'CEN','status'=>1]);
    $values=['academic_year_id'=>1,'campus_id'=>3,'prepared_staff_id'=>7,'prepared_date'=>'2026-10-01','checked_staff_id'=>8,'checked_date'=>'2026-10-02','approved_staff_id'=>9,'approved_date'=>'2026-10-03'];
    $values[$role.'_staff_id']=1;
    try{
        app(ScholarshipController::class)->saveRankPrintSettings(scholarshipRequest('POST',$values,[],[3],[3],[3]));
        $this->fail('A BCH signer must not be accepted for Central print settings.');
    }catch(ValidationException $exception){
        expect($exception->errors())->toHaveKey($role.'_staff_id');
    }
    expect(\App\Models\ScholarshipPrintSetting::count())->toBe(0);
})->with(['prepared','checked','approved']);


it('saves independent seat report signers for each center without changing rank print settings',function(){
    $exam=scholarshipSeatReportExam();$centers=$exam->centers()->orderBy('position')->get();$controller=app(ScholarshipController::class);
    $rank=\App\Models\ScholarshipPrintSetting::create(['campus_id'=>1,'academic_year_id'=>1,'prepared_by_name'=>'RANK SIGNER','prepared_date'=>'2026-01-01','approved_by_name'=>'RANK APPROVER','approved_date'=>'2026-01-01']);
    foreach($centers as $index=>$center){
        $campus=$index+1;$offset=$index*3;$center->update(['location'=>$index?'Battambang':'Kampong Cham']);
        $input=['exam_id'=>$exam->id,'exam_center_id'=>$center->id,'campus_id'=>$campus,'signing_location'=>'IGNORED OLD LOCATION'];
        foreach(['prepared','checked','approved'] as $i=>$role){$input[$role.'_staff_id']=$offset+$i+1;$input[$role.'_date']='2026-10-'.($index?'11':'10');}
        $input['prepared_by_name']='FORGED NAME';$input['prepared_position']='FORGED POSITION';
        $response=$controller->saveSeatPrintSettings(scholarshipRequest('POST',$input,[],[1,2]));
        expect($response->getData(true)['message'])->toContain($center->name);
        $loaded=$controller->seatPrintSettings(scholarshipRequest('GET',['exam_id'=>$exam->id,'exam_center_id'=>$center->id],[],[1,2]))->getData(true);
        expect($loaded['requires_verifier'])->toBeTrue()->and($loaded['campus_id'])->toBe($campus)
            ->and($loaded['values']['prepared_by_name'])->toBe(($index?'BKK':'BCH').' PREPARER')
            ->and($loaded['values']['prepared_date'])->toBe($input['prepared_date'])

            ->and(collect($loaded['staff'])->pluck('id')->sort()->values()->all())->toBe($index?[4,5,6]:[1,2,3]);
    }
    expect($rank->fresh()->prepared_by_name)->toBe('RANK SIGNER')->and($rank->fresh()->prepared_date->format('Y-m-d'))->toBe('2026-01-01');
    $data=scholarshipSeatReportData($exam,'candidates');
    foreach($data['pages'] as $page){
        $cells=collect($page['rows'])->flatMap(fn($row)=>$row['cells']);
        $names=$cells->where('role','signature-name')->flatMap(fn($cell)=>$cell['lines'])->pluck('text')->all();
        if(!$names)continue;
        $north=$page['center']==='North Exam Center';
        expect($names)->toContain(($north?'BCH':'BKK').' PREPARER')->not->toContain(($north?'BKK':'BCH').' PREPARER','RANK SIGNER');
        expect($cells->where('role','signature-date')->first()['lines'][0]['text'])->toBe(($north?'Kampong Cham':'Battambang').', October '.($north?'10':'11').', 2026.');
        expect($cells->where('role','signature-name')->first()['lines'][0]['bold'])->toBeTrue();
    }
});

it('validates the center staff scope and all three signer dates before saving seat print settings',function($change,$field){
    $exam=scholarshipSeatReportExam();
    $input=['exam_id'=>$exam->id,'exam_center_id'=>$exam->centers()->first()->id,'campus_id'=>1,'signing_location'=>'Phnom Penh',
        'prepared_staff_id'=>1,'checked_staff_id'=>2,'approved_staff_id'=>3,
        'prepared_date'=>'2026-10-10','checked_date'=>'2026-10-10','approved_date'=>'2026-10-10'];
    try{app(ScholarshipController::class)->saveSeatPrintSettings(scholarshipRequest('POST',array_replace($input,$change),[],[1,2]));$this->fail('Invalid seat print settings accepted.');}
    catch(ValidationException $e){expect($e->errors())->toHaveKey($field);}
    expect(\App\Models\ScholarshipSeatPrintSetting::count())->toBe(0);
})->with([
    [['exam_center_id'=>9999],'exam_center_id'],[['campus_id'=>9999],'campus_id'],
    [['prepared_staff_id'=>4],'prepared_staff_id'],[['checked_staff_id'=>null],'checked_staff_id'],
    [['approved_date'=>'2026-02-30'],'approved_date'],[['prepared_date'=>''],'prepared_date'],
]);

it('protects seat print settings with the seat tab export and exam campus permissions',function($method,$denied,$campuses,$draft){
    $exam=scholarshipSeatReportExam();if($draft)$exam->update(['seats_locked_at'=>null]);
    $request=scholarshipRequest($method,['exam_id'=>$exam->id,'exam_center_id'=>$exam->centers()->first()->id],$denied,$campuses);
    $action=$method==='GET'?'seatPrintSettings':'saveSeatPrintSettings';
    expect(fn()=>app(ScholarshipController::class)->$action($request))->toThrow($draft?ValidationException::class:\Symfony\Component\HttpKernel\Exception\HttpException::class);
})->with([
    ['GET',['western-scholarship.tab-seat-plan'],[1,2],false],['POST',['western-scholarship.export-seats'],[1,2],false],
    ['GET',[],[1],false],['POST',[],[1,2],true],
]);


it('migrates saved signing locations to exam centers and uses centers as the report source',function(){
    $migration=require database_path('migrations/2026_10_16_000005_move_scholarship_location_to_exam_centers.php');$migration->down();
    $exam=scholarshipSeatReportExam();$centers=$exam->centers()->orderBy('position')->get();
    foreach($centers as $i=>$center)\App\Models\ScholarshipSeatPrintSetting::create(['exam_center_id'=>$center->id,'campus_id'=>$i+1,'signing_location'=>$i?'Battambang':'Kampong Cham','prepared_date'=>'2026-10-20']);
    $migration->up();
    expect($centers[0]->fresh()->location)->toBe('Kampong Cham')->and($centers[1]->fresh()->location)->toBe('Battambang');
    \App\Models\ScholarshipSeatPrintSetting::query()->update(['signing_location'=>'OLD UNUSED VALUE']);
    $html=view('western-scholarship.seat-report',scholarshipSeatReportData($exam,'candidates'))->render();
    expect($html)->toContain('Kampong Cham, October 20, 2026.','Battambang, October 20, 2026.')->not->toContain('OLD UNUSED VALUE');
});

it('updates only exam center locations after confirmation without changing seating subjects or results',function(){
    $exam=scholarshipSeatReportExam();$exam->update(['published_at'=>now()]);$centers=$exam->centers()->orderBy('position')->get();
    $seats=$exam->candidates()->get()->toArray();$subjects=$exam->subjects()->get()->toArray();$locked=$exam->seats_locked_at->toDateTimeString();
    $request=scholarshipRequest('POST',['version'=>$exam->version,'locations'=>[$centers[0]->id=>' Phnom Penh ',$centers[1]->id=>'Battambang'],'room_capacity'=>1,'subjects'=>[]],[],[1,2]);$request->headers->set('Accept','application/json');
    $data=app(ScholarshipController::class)->saveCenterLocations($request,$exam)->getData(true);
    expect(array_column($data['centers'],'location'))->toBe(['Phnom Penh','Battambang'])
        ->and($exam->fresh()->room_capacity)->toBe(40)->and($exam->fresh()->published_at)->not->toBeNull()
        ->and($exam->fresh()->seats_locked_at->toDateTimeString())->toBe($locked)
        ->and($exam->candidates()->get()->toArray())->toBe($seats)->and($exam->subjects()->get()->toArray())->toBe($subjects);
    foreach(['candidates','attendance','attendance-cover','envelope-cover','seat-labels','room-plan'] as $kind){
        $report=scholarshipSeatReportData($exam->fresh(),$kind);
        foreach($report['pages'] as $page){
            $expected=$kind!=='candidates'?'Exam Date: November 20, 2026':($page['center']==='North Exam Center'?'Phnom Penh':'Battambang').', November 20, 2026';
            $text=collect($page['rows'])->flatMap(fn($row)=>$row['cells'])->flatMap(fn($cell)=>$cell['lines'])->pluck('text')->implode(' ');
            expect($text)->toContain($expected);
        }
    }
    foreach(['seats','results'] as $kind){
        $reportRequest=scholarshipRequest('GET',[],[],[1,2]);$reportRequest->user()->is_global=true;
        $print=app(ScholarshipController::class)->report($reportRequest,$exam->fresh(),$kind,'print')->render();
        expect($print)->toContain('Phnom Penh, November 20, 2026','Battambang, November 20, 2026');
    }
});

it('rejects invalid or stale center location updates atomically',function($locations,$stale){
    $exam=scholarshipSeatReportExam();$center=$exam->centers()->first();
    $input=['version'=>$stale?$exam->version+1:$exam->version,'locations'=>[$center->id=>'Valid Location']+$locations];
    try{app(ScholarshipController::class)->saveCenterLocations(scholarshipRequest('POST',$input,[],[1,2]),$exam);$this->fail('Invalid center locations were saved.');}
    catch(ValidationException $e){expect($e->errors())->not->toBeEmpty();}
    expect($center->fresh()->location)->toBe('Phnom Penh')->and($exam->fresh()->version)->toBe($exam->version);
})->with([[[9999=>'Other Center'],false],[[2=>'  '],false],[[2=>str_repeat('X',81)],false],[[],true]]);

it('requires scholarship settings permission and the full exam campus scope to edit locations',function($denied,$campuses){
    $exam=scholarshipSeatReportExam();$center=$exam->centers()->first();
    expect(fn()=>app(ScholarshipController::class)->saveCenterLocations(scholarshipRequest('POST',['version'=>$exam->version,'locations'=>[$center->id=>'Battambang']],$denied,$campuses),$exam))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect($center->fresh()->location)->toBe('Phnom Penh');
})->with([[['western-scholarship.settings'],[1,2]],[['western-scholarship.tab-settings'],[1,2]],[[],[1]]]);
