<?php
use App\Models\{User,AcademicYear,ScholarshipExam,ScholarshipCertificate};
use App\Services\{ScholarshipLegacyImport,ScholarshipCertificates};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';
beforeEach(function(){createScholarshipSchema();(require database_path('migrations/2026_10_21_000004_create_scholarship_certificates.php'))->up();});

function scholarshipLegacyRow(array $replace=[]): array
{
    return array_replace(['awarding_id'=>'OLD-1','student_id'=>'2026001','student_name_eng'=>'ORIGINAL CERTIFICATE NAME','student_name_kh'=>'ឈ្មោះដើម','academic_year'=>'2025-2026','campus'=>'BCH','grade'=>'3A','grade_level'=>'3','stu_rank'=>'1','stu_group'=>'III',
        'score_khmer'=>'80','score_science'=>'0','score_physics'=>'0','score_math'=>'90','score_english'=>'70','result_rank'=>'','result_award'=>'','certificate_no'=>'26-001','exam_date'=>'21-Jul-26','certificate_date'=>'14-August-2026','result_approved_date'=>'29-Jul-26','award_for_ay'=>'',
        'registrar_name'=>'ORIGINAL REGISTRAR','registrar_position'=>'Registrar Director','president_name'=>'ORIGINAL PRESIDENT','president_position'=>'Vice President','exam_center'=>'STADIUM CAMPUS','exam_room'=>'1','exam_seat'=>'1'], $replace);
}
function scholarshipLegacyFile(array $rows): string
{
    $path=tempnam(sys_get_temp_dir(),'scholarship-legacy-');$h=fopen($path,'wb');fputcsv($h,array_keys($rows[0]),',','"','');foreach($rows as $row)fputcsv($h,array_values($row),',','"','');fclose($h);return $path;
}
it('imports original certificates by student ID and preserves partial zero score absences',function(){
    $service=app(ScholarshipLegacyImport::class);$path=scholarshipLegacyFile([scholarshipLegacyRow()]);
    $user=Mockery::mock(User::class)->makePartial();$user->id=2;$user->shouldReceive('isSuperAdmin')->andReturnTrue();
    try{
        $plan=$service->plan([$path]);expect(ScholarshipExam::count())->toBe(0);
        $result=$service->import($user,[$path],$plan['hash']);$exam=ScholarshipExam::first();$candidate=$exam->candidates()->with('scores')->first();$cert=ScholarshipCertificate::first();
        expect($result[0]['records'])->toBe(1)->and($candidate->student_id)->toBe(1)->and($candidate->absent)->toBeFalse()
            ->and($candidate->scores)->toHaveCount(4)->and($candidate->scores->where('is_absent',true))->toHaveCount(1)
            ->and($candidate->result_total)->toBe(240.0)->and($cert->certificate_number)->toBe('26-001')
            ->and($cert->snapshot['student_name'])->toBe('ORIGINAL CERTIFICATE NAME')
            ->and(DB::table('tb_student')->where('id',1)->value('full_name_en'))->toBe('CHEAN CHAMNUNY')
            ->and($exam->subjects()->whereIn('id',$candidate->scores->pluck('subject_id'))->pluck('name'))->not->toContain('Physics');
        $certificates=app(ScholarshipCertificates::class);$year=AcademicYear::find(1);
        expect($certificates->eligible($year)->first()['fingerprint'])->toBe($cert->snapshot['fingerprint']);
        $settings=$certificates->settings($year);$settings['issue_date']='2030-01-01';$settings['signers'][0]['name']='NEW PRESIDENT';
        $values=$certificates->values($cert->snapshot,$settings,$cert->certificate_number);
        expect($values['issue_date'])->toBe('August 14, 2026')->and($values['signer_1_name'])->toBe('ORIGINAL PRESIDENT');
        \Illuminate\Support\Facades\Auth::setUser($user);
        $request=\Illuminate\Http\Request::create('/students/western-scholarship','POST',['ids'=>[$cert->id],'format'=>'print','frame'=>false]);$request->setUserResolver(fn()=>$user);app()->instance('request',$request);
        $report=app(\App\Http\Controllers\ScholarshipCertificateController::class)->report($request,$year);
        expect($report->getData()['pages'][0]['values']['certificate_number'])->toBe('26-001')
            ->and($report->getData()['pages'][0]['values']['issue_date'])->toBe('August 14, 2026');
        expect(fn()=>$certificates->plan($year))->toThrow(ValidationException::class,'Historical certificate numbers');
        expect(fn()=>$service->plan([$path]))->toThrow(ValidationException::class,'already contains records');
        $snapshot=$candidate->snapshot;$snapshot['historical_certificate']['place']='3rd Place';$candidate->update(['snapshot'=>$snapshot]);
        expect($certificates->eligible($year)->first()['fingerprint'])->not->toBe($cert->snapshot['fingerprint']);
    }finally{unlink($path);}
});
it('uses Science in grades 1 to 6 and Physics in grades 7 to 11 and treats applicable zeros as absence',function(){
    $service=app(ScholarshipLegacyImport::class);
    $primary=$service->normalized(scholarshipLegacyRow(['score_khmer'=>'0','score_science'=>'0','score_math'=>'0','score_english'=>'0']));
    expect($primary['absent'])->toBeTrue()->and(array_keys($primary['scores']))->toBe(['Khmer','Science','Math','English']);
    $secondary=$service->normalized(scholarshipLegacyRow(['grade'=>'7A','grade_level'=>'7','score_physics'=>'95']));
    expect($secondary['absent'])->toBeFalse()->and($secondary['scores']['Physics'])->toBe(95.0)->and($secondary['scores'])->not->toHaveKey('Science');
    expect(fn()=>$service->normalized(scholarshipLegacyRow(['score_physics'=>'5'])))->toThrow(ValidationException::class,'unused subject');
    expect(fn()=>$service->normalized(scholarshipLegacyRow(['certificate_no'=>'260001'])))->toThrow(ValidationException::class,'original YY-NNN');
});
it('keeps historical certificate fingerprints stable when database JSON keys are reordered',function(){
    $original=['student_id'=>1,'historical_signers'=>[['name'=>'PRESIDENT','position'=>'Vice President']],'academic_year'=>'2025-2026'];
    $reordered=['academic_year'=>'2025-2026','historical_signers'=>[['position'=>'Vice President','name'=>'PRESIDENT']],'student_id'=>1];
    expect(ScholarshipCertificates::historicalFingerprint($original))->toBe(ScholarshipCertificates::historicalFingerprint($reordered));
});
it('rolls back the archive when subject configuration is incompatible',function(){
    $exam=ScholarshipExam::create(['academic_year_id'=>1,'name'=>'2025-2026','exam_date'=>'2026-07-21','campus_ids'=>[1],'eligible_ranks'=>[]]);
    $exam->subjects()->create(['name'=>'Khmer','grade_from'=>1,'grade_to'=>6,'max_score'=>50,'weight'=>1,'position'=>0]);
    $path=scholarshipLegacyFile([scholarshipLegacyRow()]);$service=app(ScholarshipLegacyImport::class);
    $user=Mockery::mock(User::class)->makePartial();$user->id=2;$user->shouldReceive('isSuperAdmin')->andReturnTrue();
    try{
        $plan=$service->plan([$path]);expect(fn()=>$service->import($user,[$path],$plan['hash']))->toThrow(ValidationException::class,'subject settings');
        expect(ScholarshipCertificate::count())->toBe(0)->and($exam->candidates()->count())->toBe(0)->and($exam->fresh()->isHistorical())->toBeFalse();
    }finally{unlink($path);}
});
