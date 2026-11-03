<?php

use App\Http\Controllers\G12GradeACertificateController;
use App\Models\User;
use App\Services\G12NationalExams;
use App\Support\G12GradeACertificate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../Fixtures/G12NationalExam.php';
beforeEach(fn()=>createG12NationalSchema());

it('assigns GA12 ending-year certificate numbers by descending score rank across campuses',function(){
    $exams=app(G12NationalExams::class);$admin=User::find(1);
    $low=$exams->save($admin,g12ExamData(['student_id'=>'low','g12_score_rank'=>'98.125']));
    $high=$exams->save($admin,g12ExamData(['student_id'=>'high','campus_id'=>2,'g12_score_rank'=>'99.900']));
    $failed=$exams->save($admin,g12ExamData(['student_id'=>'failed','g12_letter_grade'=>'F']));
    expect(G12GradeACertificate::assignNumbers('2025-2026',[1,2]))->toBe(2)
        ->and($high->fresh()->data['g12_lettera_certnumber'])->toBe('GA1226-001')
        ->and($low->fresh()->data['g12_lettera_certnumber'])->toBe('GA1226-002')
        ->and($failed->fresh()->data['g12_lettera_certnumber']??'')->toBe('')
        ->and(G12GradeACertificate::assignNumbers('2025-2026',[1,2]))->toBe(0);
});

it('opens with only an academic year and shares its saved layout across campuses',function(){
    $admin=User::find(1);$exams=app(G12NationalExams::class);
    $exams->save($admin,g12ExamData(['g12_exam_date'=>'១០ សីហា ២០២៦']));
    $exams->save($admin,g12ExamData(['student_id'=>'failed','g12_letter_grade'=>'F']));
    $controller=app(G12GradeACertificateController::class);
    $scope=['academic_year'=>'2025-2026','campus_id'=>1];
    $result=$controller->show(g12Request($admin,$scope))->getData(true);
    expect($result['students'])->toHaveCount(1)->and($result['students'][0]['values']['student_name'])->toBe('NATIONAL EXAM STUDENT')
        ->and($result['students'][0]['values']['exam_date'])->toBe('10 August 2026');
    $layout=$result['layout'];$layout['fields']['title']['color']='#123456';$layout['fields']['title']['y']=101;
    $layout['issue_date']='2026-11-15';$layout['fields']['signer_1']['text']="New Signer\nPrincipal";
    $controller->save(g12Request($admin,$scope+['layout'=>$layout]));
    expect($controller->show(g12Request($admin,$scope))->getData(true)['layout']['fields']['title']['color'])->toBe('#123456')
        ->and($controller->show(g12Request($admin,['academic_year'=>'2025-2026']))->getData(true)['layout']['fields']['title']['color'])->toBe('#123456')
        ->and($controller->show(g12Request($admin,['academic_year'=>'2025-2026','campus_id'=>2]))->getData(true)['layout']['fields']['title']['color'])->toBe('#123456')
        ->and($controller->show(g12Request($admin,['academic_year'=>'2025-2026']))->getData(true)['layout']['issue_date'])->toBe('2026-11-15')
        ->and($controller->show(g12Request($admin,['academic_year'=>'2025-2026']))->getData(true)['layout']['fields']['signer_1']['text'])->toBe("New Signer\nPrincipal")
        ->and($controller->show(g12Request($admin,['academic_year'=>'2024-2025','campus_id'=>1]))->getData(true)['layout']['fields']['title']['color'])->toBe('#ed1c24');
});

it('exports the edited certificate and rejects failed students and out of bounds fields',function(){
    $admin=User::find(1);$exams=app(G12NationalExams::class);
    $a=$exams->save($admin,g12ExamData());
    $failed=$exams->save($admin,g12ExamData(['student_id'=>'failed','g12_letter_grade'=>'F']));
    $controller=app(G12GradeACertificateController::class);
    $input=['academic_year'=>'2025-2026','campus_id'=>1,'layout'=>G12GradeACertificate::defaults(),'student_ids'=>[$a->id],'with_frame'=>true];
    $pdf=$controller->pdf(g12Request($admin,$input));
    expect(substr($pdf->getContent(),0,4))->toBe('%PDF');
    $input['student_ids']=[$failed->id];
    expect(fn()=>$controller->pdf(g12Request($admin,$input)))->toThrow(HttpException::class);
    $input['layout']['fields']['title']['y']=296;
    expect(fn()=>$controller->save(g12Request($admin,$input)))->toThrow(ValidationException::class);
});

it('prevents a report reader from saving and denies inaccessible campuses',function(){
    $reader=g12ExamUser(['view','reports']);$controller=app(G12GradeACertificateController::class);
    $scope=['academic_year'=>'2025-2026','campus_id'=>1];
    expect($controller->show(g12Request($reader,$scope))->getData(true)['can_save'])->toBeFalse();
    expect(fn()=>$controller->save(g12Request($reader,$scope+['layout'=>G12GradeACertificate::defaults()])))->toThrow(HttpException::class);
    expect(fn()=>$controller->show(g12Request($reader,['academic_year'=>'2025-2026','campus_id'=>2])))->toThrow(HttpException::class);
});
