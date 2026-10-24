<?php

use App\Http\Controllers\ScholarshipController;
use App\Models\{Permission,ScholarshipAnswerScan,ScholarshipAnswerSheet,ScholarshipScore,ScholarshipStudentRank,StudentEnrollment};
use App\Services\ScholarshipWorkflow;
use App\Support\ScholarshipPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,Blade,DB,Storage};
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';
require_once __DIR__.'/../Fixtures/ScholarshipAnswerSheets.php';
require_once __DIR__.'/../Fixtures/ScholarshipScoredExam.php';

beforeEach(function(){
    createScholarshipSchema();
    (require database_path('migrations/2026_10_22_000001_create_scholarship_answer_sheet_workflow.php'))->up();
    (require database_path('migrations/2026_10_22_000002_add_scholarship_question_scores.php'))->up();
    (require database_path('migrations/2026_10_23_000007_add_scholarship_scan_absence_detection.php'))->up();
    Storage::fake('local');
});

function candidateLockRequest($user,array $data=[]): Request
{
    Auth::setUser($user);$request=Request::create('/students/western-scholarship','POST',$data+['tab'=>'assign-rank','academic_year_id'=>1,'campus_id'=>2,'grade_class'=>'1:1']);
    $request->setUserResolver(fn()=>$user);$request->setLaravelSession(app('session')->driver());app()->instance('request',$request);return $request;
}

it('rejects adding a candidate after scoring even through an obsolete late-add request',function($legacy,$zero){
    [$user,$workflow,$exam,$paper,$sheet,$scan]=scoredScholarshipFixture();
    if($zero)ScholarshipScore::query()->update(['score'=>0,'entry_method'=>'manual']);
    $before=['candidates'=>$exam->candidates()->get()->toArray(),'ranks'=>ScholarshipStudentRank::get()->toArray(),
        'scores'=>ScholarshipScore::get()->toArray(),'scans'=>ScholarshipAnswerScan::get()->toArray(),'sheets'=>ScholarshipAnswerSheet::get()->toArray(),'exam'=>$exam->fresh()->toArray()];
    $payload=['enrollment_id'=>3,'rank'=>1,'eoy_average'=>98];
    if($legacy)$payload+=['late_add'=>1,'late_confirmed'=>1,'late_version'=>$exam->version,'late_center'=>'new','late_center_name'=>'BKK Center','late_reason'=>'Old page submission'];
    expect(fn()=>app(ScholarshipController::class)->saveRank(candidateLockRequest($user,$payload)))->toThrow(ValidationException::class,'locked');
    expect(fn()=>$workflow->saveRank($user,StudentEnrollment::find(3),1,98))->toThrow(ValidationException::class,'locked');
    expect($exam->candidates()->get()->toArray())->toBe($before['candidates'])->and(ScholarshipStudentRank::get()->toArray())->toBe($before['ranks'])
        ->and(ScholarshipScore::get()->toArray())->toBe($before['scores'])->and(ScholarshipAnswerScan::get()->toArray())->toBe($before['scans'])
        ->and(ScholarshipAnswerSheet::get()->toArray())->toBe($before['sheets'])->and($exam->fresh()->toArray())->toBe($before['exam']);
})->with([[false,false],[true,false],[false,true],[true,true]]);

it('shows locked rank entry without late-add controls or filter metadata',function(){
    [$user]=scoredScholarshipFixture();$controller=app(ScholarshipController::class);
    $view=$controller->index(candidateLockRequest($user))->getData();
    expect($view['ranksLocked'])->toBeTrue()->and($view)->not->toHaveKey('lateAdd');
    $template=file_get_contents(resource_path('views/western-scholarship/index.blade.php'));preg_match("/@section\('content'\)(.*)@endsection/s",$template,$match);
    $html=Blade::render(preg_replace('/^@vite.*$/m','',$match[1]),$view+['errors'=>new Illuminate\Support\ViewErrorBag()]);
    expect($html)->not->toContain('Add Late Candidate','data-late-rank-fields','name="late_add"')->toContain('data-ranks-locked="true"');
    $filters=$controller->rankFilters(candidateLockRequest($user))->getData(true);
    expect($filters['ranks_locked'])->toBeTrue()->and($filters)->not->toHaveKey('late_add');
});

it('keeps ordinary rank assignment available before seats are confirmed',function(){
    $user=answerSheetUser();$workflow=app(ScholarshipWorkflow::class);
    $workflow->saveRank($user,StudentEnrollment::find(1),1,99);
    $exam=$workflow->saveExam($user,null,['academic_year_id'=>1,'exam_date'=>'2026-11-20','room_capacity'=>25,'version'=>1,
        'subjects'=>[['name'=>'English','grade_from'=>1,'grade_to'=>6,'max_score'=>100,'weight'=>1]]]);
    $workflow->saveRank($user,StudentEnrollment::find(3),1,98);
    expect($exam->candidates()->count())->toBe(2)->and($exam->fresh()->seats_locked_at)->toBeNull();
    $workflow->lockPlan($user,$exam,$workflow->plan($exam->fresh())['token']);
    expect(fn()=>$workflow->saveRank($user,StudentEnrollment::find(3),2,97))->toThrow(ValidationException::class,'locked');
});

it('removes the retired permission and grants without touching other permissions or recreating it',function(){
    (require database_path('migrations/2026_10_23_000003_add_late_scholarship_candidates_permission.php'))->up();
    $id=Permission::where('code','western-scholarship.add-late-candidates')->sole()->id;
    DB::table('access_role_permissions')->insert(['role_id'=>1,'permission_id'=>$id]);
    DB::table('access_department_permissions')->insert(['department_id'=>1,'permission_id'=>$id]);
    DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>$id,'allowed'=>true]);
    ScholarshipPermissions::registerMissing();$reset=Permission::where('code','western-scholarship.reset-for-reseating')->sole()->toArray();
    $migration=require database_path('migrations/2026_10_23_000005_remove_late_scholarship_candidates_permission.php');$migration->up();$migration->up();
    ScholarshipPermissions::registerMissing();
    expect(Permission::where('code','western-scholarship.add-late-candidates')->exists())->toBeFalse()
        ->and(ScholarshipPermissions::catalog())->not->toHaveKey('add-late-candidates')
        ->and(ScholarshipPermissions::tabGroups()['assign-rank']['actions'])->not->toContain('add-late-candidates')
        ->and(ScholarshipPermissions::allows(answerSheetUser(),'add-late-candidates'))->toBeFalse()
        ->and(method_exists(ScholarshipWorkflow::class,'addLateCandidate'))->toBeFalse()
        ->and(Permission::whereKey($reset['id'])->sole()->toArray())->toBe($reset);
    foreach(['access_role_permissions','access_department_permissions','access_user_permission_overrides'] as $table)
        expect(DB::table($table)->where('permission_id',$id)->exists())->toBeFalse();
});
