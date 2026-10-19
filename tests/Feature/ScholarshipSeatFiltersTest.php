<?php

use App\Http\Controllers\ScholarshipController;
use App\Models\{ScholarshipExam,SchoolInfo,User};
use App\Services\ScholarshipWorkflow;
use App\Support\ScholarshipSeatReports;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';
beforeEach(fn()=>createScholarshipSchema());

function seatFiltersRequest(array $params=[],bool $super=true,array $denied=[]): Request
{
    $user=Mockery::mock(User::class)->makePartial();$user->id=1;$user->active_campus_id=1;$user->setRelation('roles',collect());
    $user->shouldReceive('isSuperAdmin')->andReturn($super);$user->shouldReceive('canAccessCampus')->andReturnTrue();
    $user->shouldReceive('accessibleCampuses')->andReturnUsing(fn()=>SchoolInfo::query());
    $user->shouldReceive('hasPermission')->andReturnUsing(fn($code,$campus)=>$campus===1&&!in_array($code,$denied,true));
    $request=Request::create('/students/western-scholarship','GET',$params);$request->setUserResolver(fn()=>$user);return $request;
}

function seatFiltersExam(): ScholarshipExam
{
    $exam=scholarshipSeatReportExam();$first=$exam->candidates()->first();
    $copy=$first->replicate();$copy->room_number=2;$copy->room_seat=27;$copy->grade_exam_number=27;$copy->candidate_code='G01-027';
    $copy->snapshot=array_replace($copy->snapshot,['name_en'=>'ROOM TWO STUDENT ALPHA','student_code'=>'ROOM2']);$copy->save();
    return $exam->fresh();
}

it('summarizes all filtered students and distinct rooms per center before pagination without leaking campus data',function(){
    $exam=seatFiltersExam();$controller=app(ScholarshipController::class);$params=['tab'=>'seat-plan','exam_id'=>$exam->id];
    $data=$controller->index(seatFiltersRequest($params+['seat_page'=>2]))->getData();
    expect($data['seatSummary']['total'])->toBe(29)->and($data['seatSummary']['male'])->toBe(15)->and($data['seatSummary']['female'])->toBe(14)->and($data['seatRows']->count())->toBe(14)
        ->and($data['seatSummary']['centers']->all())->toBe([['name'=>'North Exam Center','rooms'=>2],['name'=>'South Exam Center','rooms'=>1]]);
    $html=view('western-scholarship._seat-summary',$data)->render();expect($html)->toContain('Total Students:','29','North Exam Center','South Exam Center','Exam Rooms:','Male: <strong>15</strong>','Female: <strong>14</strong>');
    $scoped=app(ScholarshipController::class)->index(seatFiltersRequest($params,false))->getData();
    expect($scoped['seatSummary']['total'])->toBe(27)->and($scoped['seatSummary']['male'])->toBe(14)->and($scoped['seatSummary']['female'])->toBe(13)->and($scoped['seatSummary']['centers']->pluck('name')->all())->toBe(['North Exam Center']);
    $request=seatFiltersRequest($params+['seat_center'=>1,'seat_room'=>[2],'seat_list'=>1]);$request->headers->set('Accept','application/json');
    $json=$controller->index($request)->getData(true);
    expect($json['summary'])->toContain('data-seat-summary','North Exam Center','Male: <strong>1</strong>','Female: <strong>0</strong>')->not->toContain('South Exam Center');
    $empty=$controller->index(seatFiltersRequest($params+['seat_search'=>'no matching student']))->getData();
    expect($empty['seatSummary']['total'])->toBe(0)->and($empty['seatSummary']['male'])->toBe(0)->and($empty['seatSummary']['female'])->toBe(0)->and($empty['seatSummary']['centers'])->toBeEmpty();
});

it('selects the academic year before building centers seats and exam actions',function(){
    $first=seatFiltersExam();$second=$first->replicate();$second->academic_year_id=2;$second->name='2026-2027';$second->save();
    $center=$second->centers()->create(['name'=>'New Year Exam Center','position'=>0]);$center->campuses()->attach(1,['exam_id'=>$second->id]);
    $candidate=$first->candidates()->first()->replicate();$candidate->exam_id=$second->id;$candidate->exam_center_id=$center->id;$candidate->exam_center_name=$center->name;$candidate->seat_scope=$center->id;$candidate->save();
    $controller=app(ScholarshipController::class);
    foreach([[],['exam_id'=>$first->id]] as $oldExam){
        $data=$controller->index(seatFiltersRequest(['tab'=>'seat-plan','academic_year_id'=>2]+$oldExam))->getData();
        expect($data['exam']->id)->toBe($second->id)->and((int)$data['filters']['academic_year_id'])->toBe(2)
            ->and($data['seatRows']->total())->toBe(1)->and($data['seatFilterOptions']['centers']->pluck('label')->all())->toBe(['New Year Exam Center'])
            ->and($data['seatFilterOptions']['rooms'])->toBeEmpty()->and($data['seatFilterOptions']['students'])->toBeEmpty();
        $html=view('western-scholarship._seat-filters',$data)->render();
        expect(strpos($html,'id="seatFilterYear"'))->toBeLessThan(strpos($html,'id="seatFilterCenter"'));
        expect(view('western-scholarship._seat-exports',$data)->render())->toContain('/exams/'.$second->id.'/');
    }
    expect(fn()=>$controller->index(seatFiltersRequest(['tab'=>'seat-plan','academic_year_id'=>2,'seat_center'=>1])))->toThrow(ValidationException::class);
});

it('uses student profiles when seat snapshots have no gender and leaves unknown genders unclassified',function(){
    \Illuminate\Support\Facades\DB::table('tb_student')->where('id',1)->update(['gender'=>' Male ']);
    \Illuminate\Support\Facades\DB::table('tb_student')->where('id',2)->update(['gender'=>null,'gender_kh'=>'ស្រី']);
    $exam=ScholarshipExam::create(['academic_year_id'=>1,'name'=>'2025-2026','exam_date'=>'2026-11-20','room_capacity'=>25,'campus_ids'=>[1],'eligible_ranks'=>[],'seats_locked_at'=>now()]);
    foreach([1,2,null] as $index=>$id)$exam->candidates()->create(['student_id'=>$id,'campus_id'=>1,'class_id'=>1,'grade_number'=>3,'class_rank'=>1,'room_number'=>1,'room_seat'=>$index+1,
        'snapshot'=>['student_code'=>'PROFILE-'.$index,'name_en'=>'PROFILE STUDENT '.$index,'name_kh'=>'','campus'=>'BCH','class'=>'A']]);
    $params=['tab'=>'seat-plan','exam_id'=>$exam->id];$data=app(ScholarshipController::class)->index(seatFiltersRequest($params))->getData();
    expect($data['seatSummary']['total'])->toBe(3)->and($data['seatSummary']['male'])->toBe(1)->and($data['seatSummary']['female'])->toBe(1);
    $first=$exam->candidates()->where('student_id',1)->first();$first->snapshot=array_replace($first->snapshot,['gender'=>'F']);$first->save();
    $data=app(ScholarshipController::class)->index(seatFiltersRequest($params))->getData();
    expect($data['seatSummary']['male'])->toBe(0)->and($data['seatSummary']['female'])->toBe(2);
});

it('offers only configured academic years with scholarship candidates and defaults to an available year',function(){
    $exam=seatFiltersExam();$controller=app(ScholarshipController::class);
    $data=$controller->index(seatFiltersRequest(['tab'=>'seat-plan']))->getData();
    expect($data['academicYears']->pluck('id')->all())->toBe([1])->and($data['exam']->id)->toBe($exam->id);
    $empty=$exam->replicate();$empty->academic_year_id=2;$empty->name='2026-2027';$empty->save();
    expect($controller->index(seatFiltersRequest(['tab'=>'seat-plan']))->getData()['academicYears']->pluck('id')->all())->toBe([1]);
    $candidate=$exam->candidates()->first()->replicate();$candidate->exam_id=$empty->id;$candidate->save();
    $data=$controller->index(seatFiltersRequest(['tab'=>'seat-plan']))->getData();
    expect($data['academicYears']->pluck('id')->all())->toBe([2,1])->and($data['exam']->id)->toBe($empty->id);
    expect($controller->index(seatFiltersRequest(['tab'=>'settings']))->getData()['academicYears']->pluck('id')->all())->toBe([2,1]);
    $candidate->delete();$exam->candidates()->delete();
    $data=$controller->index(seatFiltersRequest(['tab'=>'seat-plan']))->getData();
    expect($data['academicYears'])->toBeEmpty()->and($data['exam'])->toBeNull();
    expect(preg_match('/id="seatFilterYear"[^>]*disabled/',view('western-scholarship._seat-filters',$data)->render()))->toBe(1);
});

it('keeps unconfigured years out of the filter even when requested directly',function(){
    $exam=seatFiltersExam();$data=app(ScholarshipController::class)->index(seatFiltersRequest(['tab'=>'seat-plan','academic_year_id'=>2,'exam_id'=>$exam->id]))->getData();
    expect($data['exam'])->toBeNull()->and((int)$data['filters']['academic_year_id'])->toBe(2)
        ->and($data['seatFilterOptions']['centers'])->toBeEmpty();
    $html=view('western-scholarship._seat-filters',$data)->render();
    expect($data['academicYears']->pluck('id')->all())->toBe([1]);
    expect($html)->toContain('id="seatFilterYear"','<option value="">Select Academic Year</option>','id="seatFilterCenter"')->not->toContain('value="2"');
    expect(preg_match('/id="seatFilterCenter"[^>]*disabled/',$html))->toBe(1);
});

it('requires a room before offering or accepting a student filter',function(){
    $exam=seatFiltersExam();$controller=app(ScholarshipController::class);
    $params=['tab'=>'seat-plan','exam_id'=>$exam->id,'seat_center'=>1];
    $data=$controller->index(seatFiltersRequest($params))->getData();
    expect($data['seatFilterOptions']['students'])->toBeEmpty()->and($data['seatRows']->total())->toBe(27);
    expect(fn()=>$controller->index(seatFiltersRequest($params+['seat_student'=>$exam->candidates()->first()->id])))->toThrow(ValidationException::class);
});

it('cascades center rooms and student choices before pagination and searches all requested fields',function(){
    $exam=seatFiltersExam();$controller=app(ScholarshipController::class);$params=['tab'=>'seat-plan','exam_id'=>$exam->id,'seat_center'=>1,'seat_room'=>[2]];
    $data=$controller->index(seatFiltersRequest($params))->getData();
    expect($data['seatRows']->total())->toBe(1)->and($data['seatFilterOptions']['rooms']->pluck('id')->all())->toBe([1,2])
        ->and($data['seatFilterOptions']['students']->pluck('label')->all())->toBe(['27 - ROOM TWO STUDENT ALPHA']);
    $id=$data['seatRows']->first()['id'];
    expect($controller->index(seatFiltersRequest($params+['seat_student'=>$id]))->getData()['seatRows']->total())->toBe(1);
    foreach(['North Exam Center','Room 2','Seat 27','student alpha','BCH'] as $search){
        $found=$controller->index(seatFiltersRequest($params+['seat_search'=>$search]))->getData();expect($found['seatRows']->total())->toBe(1);
    }
    expect($controller->index(seatFiltersRequest($params+['seat_search'=>'BKK']))->getData()['seatRows']->total())->toBe(0);
    $both=$controller->index(seatFiltersRequest(array_replace($params,['seat_room'=>[1,2]])))->getData();
    expect($both['seatRows']->total())->toBe(27)->and($both['seatRows']->count())->toBe(15);
    $second=$controller->index(seatFiltersRequest(array_replace($params,['seat_room'=>[1,2],'seat_page'=>2])))->getData();
    expect($second['seatRows']->count())->toBe(12)->and($second['seatRows']->firstItem())->toBe(16);
});

it('does not expose another campus candidates through filters or options',function(){
    $exam=seatFiltersExam();$controller=app(ScholarshipController::class);
    $data=$controller->index(seatFiltersRequest(['tab'=>'seat-plan','exam_id'=>$exam->id],false))->getData();
    expect($data['seatRows']->total())->toBe(27)->and($data['seatFilterOptions']['centers']->pluck('label')->all())->toBe(['North Exam Center']);
    expect(fn()=>$controller->index(seatFiltersRequest(['tab'=>'seat-plan','exam_id'=>$exam->id,'seat_center'=>2],false)))->toThrow(ValidationException::class);
});

it('does not offer a year whose scholarship students are outside the users campus scope',function(){
    $first=seatFiltersExam();$second=$first->replicate();$second->academic_year_id=2;$second->name='2026-2027';$second->save();
    $candidate=$first->candidates()->where('campus_id',2)->first()->replicate();$candidate->exam_id=$second->id;$candidate->save();
    $controller=app(ScholarshipController::class);
    expect($controller->index(seatFiltersRequest(['tab'=>'seat-plan'],false))->getData()['academicYears']->pluck('id')->all())->toBe([1]);
    expect($controller->index(seatFiltersRequest(['tab'=>'seat-plan']))->getData()['academicYears']->pluck('id')->all())->toBe([2,1]);
});

it('rejects invalid dependent filter selections',function($filters){
    $exam=seatFiltersExam();expect(fn()=>app(ScholarshipController::class)->index(seatFiltersRequest(['tab'=>'seat-plan','exam_id'=>$exam->id]+$filters)))->toThrow(ValidationException::class);
})->with([[['seat_room'=>[1]]],[['seat_center'=>2,'seat_room'=>[2]]],[['seat_center'=>1,'seat_student'=>9999]],[['seat_center'=>1,'seat_room'=>[2],'seat_student'=>1]]]);

it('scopes every document to selected rooms or all rooms within a required exam center',function($kind){
    $exam=seatFiltersExam();$controller=app(ScholarshipController::class);
    $selected=$controller->seatReport(seatFiltersRequest(['report_center'=>1,'report_scope'=>'selected-rooms','report_rooms'=>[2]]),$exam,$kind,'print')->getData();
    expect(collect($selected['pages'])->pluck('center')->unique()->all())->toBe(['North Exam Center'])->and(collect($selected['pages'])->pluck('room')->unique()->all())->toBe([2]);
    $all=$controller->seatReport(seatFiltersRequest(['report_center'=>1,'report_scope'=>'all-rooms','seat_student'=>999,'seat_search'=>'no match']),$exam,$kind,'print')->getData();
    expect(collect($all['pages'])->pluck('room')->unique()->values()->all())->toBe([1,2]);
    $south=$controller->seatReport(seatFiltersRequest(['report_center'=>2,'report_scope'=>'selected-rooms','report_rooms'=>[1]]),$exam,$kind,'print')->getData();
    expect(collect($south['pages'])->pluck('center')->unique()->all())->toBe(['South Exam Center']);
})->with(array_keys(ScholarshipSeatReports::DOCUMENTS));

it('rejects absent centers missing rooms and rooms from another center in exports',function($params){
    $exam=seatFiltersExam();expect(fn()=>app(ScholarshipController::class)->seatReport(seatFiltersRequest($params),$exam,'candidates','print'))->toThrow(ValidationException::class);
})->with([[[]],[['report_scope'=>'all-rooms']],[['report_center'=>1,'report_scope'=>'selected-rooms']],[['report_center'=>2,'report_scope'=>'selected-rooms','report_rooms'=>[2]]],[['report_center'=>999,'report_scope'=>'all-rooms']]]);

it('updates report headings after confirmation or publication without changing locked data',function($published){
    $exam=seatFiltersExam();if($published)$exam->forceFill(['published_at'=>now()])->save();
    $before=$exam->candidates()->get()->map->getAttributes()->all();$subjects=$exam->subjects()->get()->map->getAttributes()->all();$locked=$exam->seats_locked_at;
    $workflow=app(ScholarshipWorkflow::class);$request=seatFiltersRequest();
    $saved=$workflow->saveExaminationType($request->user(),$exam,'WIS Academic Excellence Examination',$exam->version);
    expect($saved->examinationTitle())->toBe('WIS Academic Excellence Examination')->and($saved->name)->toBe('2025-2026')->and($saved->seats_locked_at->eq($locked))->toBeTrue()
        ->and($saved->candidates()->get()->map->getAttributes()->all())->toBe($before)->and($saved->subjects()->get()->map->getAttributes()->all())->toBe($subjects)
        ->and($saved->events()->first()->action)->toBe('examination-type-updated');
    foreach(array_keys(ScholarshipSeatReports::DOCUMENTS) as $kind){
        $data=scholarshipSeatReportData($saved,$kind);$html=view('western-scholarship.seat-report',$data)->render();
        expect($html)->toContain('WIS Academic Excellence Examination')->not->toContain('WIS Scholarship Exams');
    }
    expect(fn()=>$workflow->saveExaminationType($request->user(),$saved,'Another title',$exam->version))->toThrow(ValidationException::class);
})->with([false,true]);

it('protects examination type updates with settings permission',function(){
    $exam=seatFiltersExam();expect(fn()=>app(ScholarshipWorkflow::class)->saveExaminationType(seatFiltersRequest([],false,['western-scholarship.settings'])->user(),$exam,'Unauthorized title',$exam->version))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('validates title saves and returns JSON feedback without accepting structural settings',function(){
    $exam=seatFiltersExam();$controller=app(ScholarshipController::class);
    expect(fn()=>$controller->saveExaminationType(seatFiltersRequest(['examination_type'=>'   ','version'=>$exam->version]),$exam))->toThrow(ValidationException::class);
    $request=seatFiltersRequest(['examination_type'=>'New Examination Heading','version'=>$exam->version,'room_capacity'=>1,'exam_date'=>'2030-01-01']);
    $request->headers->set('Accept','application/json');$response=$controller->saveExaminationType($request,$exam)->getData(true);
    expect($response['message'])->toBe('Examination type saved.')->and($response['examination_type'])->toBe('New Examination Heading')
        ->and($exam->fresh()->room_capacity)->toBe(40)->and($exam->fresh()->exam_date->format('Y-m-d'))->toBe('2026-11-20');
});
