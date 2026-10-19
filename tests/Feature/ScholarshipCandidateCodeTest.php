<?php

use App\Models\User;
use App\Services\ScholarshipWorkflow;
use App\Support\ScholarshipCandidateCode;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';

beforeEach(fn()=>createScholarshipSchema());

it('derives the candidate prefix from the academic year',function($year,$prefix){
    expect(ScholarshipCandidateCode::yearPrefix($year))->toBe($prefix);
})->with([
    ['2025-2026','2526'],['2026-2027','2627'],['2025 – 2026','2526'],['2025/2026','2526'],['2099-2100','9900'],['2026',null],
]);

it('restarts grade sequences under different year prefixes and persists the generated codes',function(){
    $workflow=app(ScholarshipWorkflow::class);$first=scholarshipSeatReportExam(2);
    $first->update(['seats_locked_at'=>null]);
    $plan=$workflow->plan($first);
    expect(collect($plan['rows'])->pluck('candidate_code')->all())->toBe(['2526-G01-001','2526-G01-002','2526-G02-001','2526-G02-002']);
    $user=Mockery::mock(User::class)->makePartial();$user->id=1;$user->shouldReceive('isSuperAdmin')->andReturnTrue();
    $workflow->lockPlan($user,$first,$plan['token']);
    expect($first->candidates()->orderBy('id')->pluck('candidate_code')->all())->toBe(collect($plan['rows'])->pluck('candidate_code')->all());
    $next=$first->replicate();$next->academic_year_id=2;$next->name='2026-2027';$next->seats_locked_at=null;$next->save();
    $next=$next->fresh();
    $copy=$first->candidates()->first()->replicate();$copy->exam_id=$next->id;$copy->save();
    expect($workflow->plan($next)['rows'][0]['candidate_code'])->toBe('2627-G01-001');
});

it('keeps grade candidate numbers unique across exam centers',function(){
    $exam=scholarshipSeatReportExam(2);$exam->candidates()->where('grade_number',2)->update(['grade_number'=>1]);
    $rows=collect(app(ScholarshipWorkflow::class)->plan($exam)['rows']);
    expect($rows->pluck('candidate_code')->unique())->toHaveCount(4)
        ->and($rows->pluck('candidate_code')->sort()->values()->all())->toBe(['2526-G01-001','2526-G01-002','2526-G01-003','2526-G01-004'])
        ->and($rows->groupBy('exam_center_id')->map(fn($group)=>$group->first()['room_seat'])->values()->all())->toBe([1,1]);
});

it('upgrades existing generated codes without changing student score seat or award data',function(){
    $exam=scholarshipSeatReportExam(2);$candidates=$exam->candidates()->orderBy('id')->get();$candidate=$candidates[0];
    $candidates[1]->update(['candidate_code'=>'CUSTOM-001']);
    $candidate->update(['reviewed_at'=>now()]);
    $candidate->scores()->create(['subject_id'=>$exam->subjects()->first()->id,'score'=>85]);
    $award=$exam->awards()->create(['grade_number'=>1,'slot'=>'total_1','candidate_id'=>$candidate->id,'award_percent'=>100]);
    $historical=$exam->replicate();$historical->academic_year_id=2;$historical->historical_imported_at=now();$historical->save();
    $original=$candidate->replicate();$original->exam_id=$historical->id;$original->candidate_code='G01-001';$original->save();
    $before=$candidate->fresh()->getRawOriginal();$examBefore=$exam->fresh()->getRawOriginal();
    $migration=require database_path('migrations/2026_10_19_000001_prefix_scholarship_candidate_codes_with_academic_year.php');
    $migration->up();$migration->up();
    expect($candidate->fresh()->candidate_code)->toBe('2526-G01-001')
        ->and(Arr::except($candidate->fresh()->getRawOriginal(),'candidate_code'))->toBe(Arr::except($before,'candidate_code'))
        ->and($exam->fresh()->getRawOriginal())->toBe($examBefore)
        ->and($candidate->scores()->first()->score)->toEqual(85)
        ->and($award->fresh()->candidate_id)->toBe($candidate->id)
        ->and($candidates[1]->fresh()->candidate_code)->toBe('CUSTOM-001')
        ->and($original->fresh()->candidate_code)->toBe('G01-001');
    $migration->down();
    expect($candidate->fresh()->candidate_code)->toBe('G01-001')->and($candidates[1]->fresh()->candidate_code)->toBe('CUSTOM-001')->and($original->fresh()->candidate_code)->toBe('G01-001');
});

it('requires a recognizable academic year before generating candidate codes',function(){
    expect(fn()=>app(ScholarshipWorkflow::class)->allocate(collect(),25,'Unknown'))->toThrow(ValidationException::class);
});
