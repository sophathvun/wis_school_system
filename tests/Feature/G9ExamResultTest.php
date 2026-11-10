<?php

use App\Http\Controllers\G9ExamResultController;
use App\Models\{G9ExamCandidate,User};
use App\Support\{G9ExamFields,G9NationalExamPermissions as Access};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../Fixtures/G12NationalExam.php';

beforeEach(function(){
    createG12NationalSchema();
    Schema::create('tb_academic_year',function(Blueprint $table){$table->id();$table->string('academic_year');$table->softDeletes();});
    DB::table('tb_academic_year')->insert([['id'=>1,'academic_year'=>'2025-2026'],['id'=>2,'academic_year'=>'2024-2025']]);
    foreach(['2026_11_04_000002_create_g9_exam_candidates','2026_11_06_000002_add_g9_exam_attendance','2026_11_06_000003_add_g9_absence_reason','2026_11_06_000004_add_g9_result_tabs'] as $migration)(require database_path('migrations/'.$migration.'.php'))->up();
    $this->results=app(G9ExamResultController::class);
});

function g9ResultCandidate(string $code,float $total=300,array $data=[],array $attributes=[]): G9ExamCandidate
{
    return G9ExamCandidate::create(array_replace(['academic_year_id'=>1,'campus_id'=>1,'student_code'=>$code,'name_en'=>'Student '.$code,'name_kh'=>'សិស្ស '.$code,
        'data'=>array_replace(array_fill_keys(array_keys(G9ExamFields::GROUPS['National Examination Scores']),0),['eoy_average'=>100,'exam_language'=>'English','score_math'=>$total-100,'exam_center'=>'Center A','room_number'=>'1','seat_number'=>$code,'grade'=>'9A','gender'=>'Female'],$data)],$attributes));
}

it('reuses scoring formulas and grade boundaries while separating pending and absent candidates',function(){
    foreach([259.999,260,337.999,338,415.999,416] as $i=>$total)g9ResultCandidate((string)($i+1),$total);
    g9ResultCandidate('7',300,['score_math'=>null]);
    g9ResultCandidate('8',300,[],['attendance_status'=>'absent','attendance_reason'=>'Sick']);
    g9ResultCandidate('9',300,['exam_language'=>'Khmer','score_english'=>40]); // 315, adds only 15.
    g9ResultCandidate('10',300,['score_english'=>null]); // English exams exclude English.
    $data=$this->results->data(g12Request(User::find(1),['academic_year'=>1]))->getData(true);
    expect($data['summary'])->toMatchArray(['total'=>10,'passed'=>7,'failed'=>1,'pending'=>1,'absent'=>1,'completed'=>8,'pass_rate'=>87.5,'female'=>10]);
    $rows=collect($data['records']['data'])->keyBy('student_code');
    expect($rows['1'])->toMatchArray(['total'=>259.999,'outcome'=>'failed','letter_grade'=>'ធ្លាក់'])
        ->and($rows['2']['letter_grade'])->toBe('មធ្យម')->and($rows['3']['letter_grade'])->toBe('មធ្យម')
        ->and($rows['4']['letter_grade'])->toBe('ល្អបង្គួរ')->and($rows['5']['letter_grade'])->toBe('ល្អបង្គួរ')
        ->and($rows['6']['letter_grade'])->toBe('ល្អ')->and($rows['7']['total'])->toBeNull()
        ->and($rows['8'])->toMatchArray(['outcome'=>'absent','result'=>'អវត្តមាន','letter_grade'=>null,'attendance_reason'=>'Sick'])
        ->and($rows['9']['total'])->toBe(315)->and($rows['10']['outcome'])->toBe('passed');
    expect($data['summary']['grades'])->toBe([['label'=>'ធ្លាក់','total'=>1],['label'=>'មធ្យម','total'=>4],['label'=>'ល្អបង្គួរ','total'=>2],['label'=>'ល្អ','total'=>1]]);
});

it('returns letter grade counts and attendance percentages per filtered exam center',function(){
    g9ResultCandidate('1',416);g9ResultCandidate('2',338);g9ResultCandidate('3',300);g9ResultCandidate('4',200);
    g9ResultCandidate('5',300,[],['attendance_status'=>'absent']);g9ResultCandidate('6',300,['score_math'=>null]);
    g9ResultCandidate('7',416,['exam_center'=>'Center B'],['campus_id'=>2]);
    $data=$this->results->data(g12Request(User::find(1),['academic_year'=>1,'view'=>'graph']))->getData(true);
    expect($data['center_grades'])->toHaveCount(2)
        ->and($data['center_grades'][0])->toMatchArray(['label'=>'Center A','attended'=>5,'absent'=>1])
        ->and(array_column($data['center_grades'][0]['grades'],'total'))->toBe([1,1,1,1])
        ->and(array_column($data['center_grades'][0]['grades'],'percentage'))->toBe([20,20,20,20]);
    $filtered=$this->results->data(g12Request(User::find(1),['academic_year'=>1,'view'=>'graph','campus'=>2]))->getData(true);
    expect($filtered['center_grades'])->toHaveCount(1)->and($filtered['center_grades'][0]['label'])->toBe('Center B')
        ->and($filtered['center_grades'][0]['grades'][0]['percentage'])->toBe(100);
});

it('graphs subject pass marks using present entered scores and exam language eligibility',function(){
    g9ResultCandidate('1',300,['exam_language'=>'Khmer','score_chemistry'=>12.5]);
    g9ResultCandidate('2',300,['exam_language'=>'Khmer','score_chemistry'=>12.49]);
    g9ResultCandidate('3',300,['score_chemistry'=>null]);
    g9ResultCandidate('4',300,['score_chemistry'=>25],['attendance_status'=>'absent']);
    g9ResultCandidate('5',300,['exam_language'=>'English','score_english'=>50]);
    $data=$this->results->data(g12Request(User::find(1),['academic_year'=>1,'view'=>'graph']))->getData(true);
    $subjects=collect($data['subject_results'])->keyBy('field');
    expect($subjects)->toHaveCount(11)
        ->and($subjects['score_chemistry'])->toMatchArray(['total'=>3,'passed'=>1,'failed'=>2,'passed_percentage'=>33.33,'failed_percentage'=>66.67])
        ->and($subjects['score_english'])->toMatchArray(['total'=>2,'passed'=>0,'failed'=>2]);
    expect($subjects['score_chemistry']['languages'][0])->toMatchArray(['total'=>1,'passed'=>0,'failed'=>1,'failed_percentage'=>100])
        ->and($subjects['score_chemistry']['languages'][1])->toMatchArray(['total'=>2,'passed'=>1,'failed'=>1,'passed_percentage'=>50])
        ->and($subjects['score_english']['languages'][0]['applicable'])->toBeFalse();
    $filtered=$this->results->data(g12Request(User::find(1),['academic_year'=>1,'view'=>'graph','search'=>'Student 3']))->getData(true);
    expect(collect($filtered['subject_results'])->firstWhere('field','score_chemistry'))->toMatchArray(['total'=>0,'passed'=>0,'failed'=>0,'passed_percentage'=>null]);
});

it('counts passed English and Khmer candidates and females across all tabs and selected filters',function(){
    g9ResultCandidate('1',300,['exam_language'=>'English']);
    g9ResultCandidate('2',300,['exam_language'=>' EN ','gender'=>'Male']);
    g9ResultCandidate('3',300,['exam_language'=>'ខ្មែរ','gender'=>'ស្រី']);
    g9ResultCandidate('4',300,['exam_language'=>'kh','gender'=>'Male']);
    g9ResultCandidate('5',200,['exam_language'=>'English']);
    g9ResultCandidate('6',300,['exam_language'=>'Khmer'],['attendance_status'=>'absent']);
    g9ResultCandidate('7',300,['exam_language'=>'Khmer','score_math'=>null]);
    g9ResultCandidate('8',300,['exam_language'=>'Khmer'],['campus_id'=>2]);
    foreach(['results','graph','statistics'] as $view){
        $summary=$this->results->data(g12Request(User::find(1),['academic_year'=>1,'view'=>$view]))->getData(true)['summary'];
        expect($summary['candidate_languages'])->toBe(['english'=>['total'=>3,'percentage'=>37.5],'khmer'=>['total'=>5,'percentage'=>62.5]]);
        expect($summary['absent_languages'])->toBe(['english'=>0,'khmer'=>1]);
        expect($summary['passed_languages'])->toBe([
            'english'=>['total'=>2,'female'=>1,'candidates'=>3,'female_candidates'=>2,'percentage'=>66.67,'female_percentage'=>50],
            'khmer'=>['total'=>3,'female'=>2,'candidates'=>4,'female_candidates'=>3,'percentage'=>75,'female_percentage'=>66.67]])
            ->and($summary['passed_percentage'])->toBe(71.43)
            ->and($summary['passed'])->toBe(5)->and($summary['passed_female'])->toBe(3);
    }
    $filtered=$this->results->data(g12Request(User::find(1),['academic_year'=>1,'campus'=>1]))->getData(true)['summary'];
    expect($filtered['candidate_languages'])->toBe(['english'=>['total'=>3,'percentage'=>42.86],'khmer'=>['total'=>4,'percentage'=>57.14]]);
    expect($filtered['passed_languages'])->toBe([
        'english'=>['total'=>2,'female'=>1,'candidates'=>3,'female_candidates'=>2,'percentage'=>66.67,'female_percentage'=>50],
        'khmer'=>['total'=>2,'female'=>1,'candidates'=>3,'female_candidates'=>2,'percentage'=>66.67,'female_percentage'=>50]])
        ->and($filtered['passed_percentage'])->toBe(66.67);
    $failed=$this->results->data(g12Request(User::find(1),['academic_year'=>1,'result'=>'failed']))->getData(true)['summary'];
    expect($failed['passed_languages'])->toBe([
        'english'=>['total'=>0,'female'=>0,'candidates'=>1,'female_candidates'=>1,'percentage'=>0,'female_percentage'=>0],
        'khmer'=>['total'=>0,'female'=>0,'candidates'=>0,'female_candidates'=>0,'percentage'=>null,'female_percentage'=>null]])
        ->and($failed['passed_percentage'])->toBe(0);
    expect($failed['absent_languages'])->toBe(['english'=>0,'khmer'=>0]);
    $absent=$this->results->data(g12Request(User::find(1),['academic_year'=>1,'result'=>'absent']))->getData(true)['summary'];
    expect($absent['total'])->toBe(1)->and($absent['absent'])->toBe(1)->and($absent['passed_percentage'])->toBeNull()
        ->and($absent['candidate_languages'])->toBe(['english'=>['total'=>0,'percentage'=>0],'khmer'=>['total'=>1,'percentage'=>100]])
        ->and($absent['passed_languages']['khmer'])->toBe(['total'=>0,'female'=>0,'candidates'=>0,'female_candidates'=>0,'percentage'=>null,'female_percentage'=>null]);
});

it('shows filtered failed totals and language percentages without counting absent candidates',function(){
    g9ResultCandidate('1',200,['exam_language'=>'English']);
    g9ResultCandidate('2',200,['exam_language'=>'Khmer'],['campus_id'=>2]);
    g9ResultCandidate('3',300,['exam_language'=>'English']);
    g9ResultCandidate('4',200,['exam_language'=>'Khmer'],['attendance_status'=>'absent']);
    g9ResultCandidate('5',200,['exam_language'=>'Khmer','score_math'=>null]);
    foreach(['results','graph','statistics'] as $view){
        $summary=$this->results->data(g12Request(User::find(1),['academic_year'=>1,'view'=>$view]))->getData(true)['summary'];
        expect($summary['failed'])->toBe(2)->and($summary['failed_percentage'])->toBe(50)
            ->and($summary['failed_languages'])->toBe(['english'=>['total'=>1,'percentage'=>50],'khmer'=>['total'=>1,'percentage'=>50]]);
    }
    $filtered=$this->results->data(g12Request(User::find(1),['academic_year'=>1,'campus'=>1]))->getData(true)['summary'];
    expect($filtered['failed_percentage'])->toBe(33.33)->and($filtered['failed_languages'])->toBe(['english'=>['total'=>1,'percentage'=>50],'khmer'=>['total'=>0,'percentage'=>0]]);
    $absent=$this->results->data(g12Request(User::find(1),['academic_year'=>1,'result'=>'absent']))->getData(true)['summary'];
    expect($absent['failed_percentage'])->toBeNull()->and($absent['failed_languages'])->toBe(['english'=>['total'=>0,'percentage'=>null],'khmer'=>['total'=>0,'percentage'=>null]]);
});

it('aggregates all filtered records across pages and sorts rooms and seats naturally without changing data',function(){
    for($i=1;$i<=30;$i++)g9ResultCandidate((string)$i,300,['room_number'=>$i<=15?'2':'10']);
    g9ResultCandidate('other',200,['exam_center'=>'Center B'],['campus_id'=>2]);
    $before=G9ExamCandidate::all()->toJson();
    $first=$this->results->data(g12Request(User::find(1),['academic_year'=>1,'per_page'=>25]))->getData(true);
    $second=$this->results->data(g12Request(User::find(1),['academic_year'=>1,'per_page'=>25,'page'=>2]))->getData(true);
    expect($first['records']['data'])->toHaveCount(25)->and($second['records']['data'])->toHaveCount(6)
        ->and($first['summary']['total'])->toBe(31)->and($second['summary'])->toBe($first['summary'])
        ->and(array_column(array_slice($first['records']['data'],0,3),'seat'))->toBe(['1','2','3']);
    foreach(['graph','statistics'] as $view){
        $stats=$this->results->data(g12Request(User::find(1),['academic_year'=>1,'view'=>$view]))->getData(true);
        expect($stats['summary'])->toBe($first['summary'])->and($stats['campuses'][0])->toMatchArray(['label'=>'BCH','total'=>30,'passed'=>30,'pass_rate'=>100])
            ->and($stats['campuses'][1])->toMatchArray(['label'=>'BKK','total'=>1,'failed'=>1,'pass_rate'=>0])->and($stats)->not->toHaveKey('records');
    }
    expect(G9ExamCandidate::all()->toJson())->toBe($before)->and(DB::table('tb_g9_exam_event')->count())->toBe(0);
});

it('cascades year center campus room grade and student choices and applies result and search filters',function(){
    $a=g9ResultCandidate('001',300);g9ResultCandidate('002',200,['exam_center'=>'Center B','grade'=>'9B'],['campus_id'=>2]);
    g9ResultCandidate('003',400,['exam_center'=>'Old Center'],['academic_year_id'=>2]);
    $options=$this->results->options(g12Request(User::find(1),['academic_year'=>1,'exam_center'=>'Center A']))->getData(true);
    expect($options['exam_center'])->toBe(['Center A','Center B'])->and(array_column($options['campus'],'label'))->toBe(['BCH'])
        ->and($options['room'])->toBe(['1'])->and($options['grade'])->toBe(['9A'])->and(array_column($options['student'],'id'))->toBe([$a->id]);
    foreach([['campus'=>1],['student'=>$a->id],['search'=>'001'],['result'=>'passed'],['grade'=>'9A'],['exam_center'=>'Center A','room'=>'1']] as $filter){
        $data=$this->results->data(g12Request(User::find(1),$filter+['academic_year'=>1]))->getData(true);
        expect($data['summary']['total'])->toBe(1)->and($data['records']['data'][0]['id'])->toBe($a->id);
    }
    $empty=$this->results->data(g12Request(User::find(1),['academic_year'=>1,'search'=>'does not exist']))->getData(true);
    expect($empty['summary']['pass_rate'])->toBeNull()->and($empty['records']['data'])->toBe([]);
    foreach([[],['academic_year'=>999],['academic_year'=>1,'result'=>'fake'],['academic_year'=>1,'per_page'=>500],['academic_year'=>1,'view'=>'secret']] as $input)
        expect(fn()=>$this->results->data(g12Request(User::find(1),$input)))->toThrow(ValidationException::class);
});

it('enforces result and tab permissions and prevents cross campus data exposure',function(){
    g9ResultCandidate('1');g9ResultCandidate('2',200,['exam_center'=>'Private Center'],['campus_id'=>2]);
    $user=User::find(2);
    expect(fn()=>$this->results->data(g12Request($user,['academic_year'=>1])))->toThrow(HttpException::class);
    foreach(['view','results'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    $data=$this->results->data(g12Request($user,['academic_year'=>1]))->getData(true);
    expect($data['summary']['total'])->toBe(1)->and($data['records']['data'][0]['campus'])->toBe('BCH');
    expect($this->results->data(g12Request($user,['academic_year'=>1,'campus'=>2]))->getData(true)['summary']['total'])->toBe(0);
    expect($this->results->options(g12Request($user,['academic_year'=>1]))->getData(true)['exam_center'])->toBe(['Center A']);
    foreach(['graph','statistics'] as $tab)expect(fn()=>$this->results->data(g12Request($user,['academic_year'=>1,'view'=>$tab])))->toThrow(HttpException::class);
    expect($this->results->index(g12Request($user))->getData()['tabs'])->toBe(['results'=>true,'graph'=>false,'statistics'=>false]);
});

it('preserves existing result grants and explicit tab denials when registering the new tabs',function(){
    $results=DB::table('access_permissions')->where('code','g9-national-exams.results')->value('id');
    $graph=DB::table('access_permissions')->where('code','g9-national-exams.results-graph')->value('id');
    DB::table('access_user_permission_overrides')->insert([['user_id'=>2,'permission_id'=>$results,'allowed'=>true],['user_id'=>2,'permission_id'=>$graph,'allowed'=>false]]);
    DB::table('access_role_permissions')->insert(['role_id'=>2,'permission_id'=>$results]);
    $migration=require database_path('migrations/2026_11_06_000004_add_g9_result_tabs.php');$migration->up();$migration->up();
    expect(DB::table('access_user_permission_overrides')->where('user_id',2)->where('permission_id',$graph)->value('allowed'))->toBe(0)
        ->and(DB::table('access_role_permissions')->where('role_id',2)->count())->toBe(3)
        ->and(Access::parent('results-statistics'))->toBe('results');
});
