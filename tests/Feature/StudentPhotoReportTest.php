<?php

use App\Http\Controllers\ReportsController;
use App\Models\User;
use App\Services\StudentPhotoReport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutVite();
    Schema::create('tb_academic_year', function (Blueprint $t) { $t->id(); $t->string('academic_year'); $t->string('period_type')->default('regular'); $t->integer('parent_academic_year_id')->nullable(); $t->softDeletes(); });
    Schema::create('tb_school_info', function (Blueprint $t) { $t->id(); $t->string('campus_name_en'); $t->integer('status')->default(1); $t->softDeletes(); });
    Schema::create('tb_grade', function (Blueprint $t) { $t->id(); $t->string('grade'); $t->string('grade_short_name'); $t->integer('grade_order'); $t->integer('status')->default(1); $t->softDeletes(); });
    Schema::create('tb_class', function (Blueprint $t) { $t->id(); $t->string('class_name'); $t->integer('class_order')->default(1); $t->softDeletes(); });
    Schema::create('tb_student', function (Blueprint $t) { $t->id(); $t->string('student_id'); $t->string('full_name_en'); $t->string('full_name_kh')->default(''); $t->string('photo_path')->nullable(); $t->integer('status')->default(1); });
    Schema::create('tb_student_enrollment', function (Blueprint $t) { $t->id(); foreach (['student_id','academic_year_id','campus_id','grade_id','class_id'] as $column) $t->integer($column); $t->integer('status')->default(1); $t->string('enrollment_status')->default('active'); });
    DB::table('tb_academic_year')->insert([['id'=>1,'academic_year'=>'2025-2026'],['id'=>2,'academic_year'=>'2024-2025']]);
    DB::table('tb_school_info')->insert([['id'=>1,'campus_name_en'=>'BCH'],['id'=>2,'campus_name_en'=>'BKK']]);
    DB::table('tb_grade')->insert([['id'=>1,'grade'=>'4','grade_short_name'=>'4','grade_order'=>4],['id'=>2,'grade'=>'5','grade_short_name'=>'5','grade_order'=>5]]);
    DB::table('tb_class')->insert([['id'=>1,'class_name'=>'A'],['id'=>2,'class_name'=>'B']]);
    Storage::fake('public');
    Storage::disk('public')->put('students/photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    for ($id=1; $id<=36; $id++) {
        DB::table('tb_student')->insert(['id'=>$id,'student_id'=>'ID-'.$id,'full_name_en'=>sprintf('STUDENT %02d',$id),'photo_path'=>$id===36?null:'students/photo.png']);
        DB::table('tb_student_enrollment')->insert(['id'=>$id,'student_id'=>$id,'academic_year_id'=>1,'campus_id'=>$id===35?2:1,'grade_id'=>$id===34?2:1,'class_id'=>1]);
    }
    $user=Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isSuperAdmin')->andReturn(true);
    $this->request=Request::create('/reports');
    $this->request->setUserResolver(fn()=>$user);
    $this->service=new StudentPhotoReport;
});

it('requires a year and filters campus, grade and student while keeping class options available', function () {
    expect($this->service->payload($this->request, [])['photoTotal'])->toBe(0);
    $payload=$this->service->payload($this->request,['academic_year_id'=>1,'campus_id'=>1,'grade_class'=>'1:1','photo_student_id'=>2]);
    expect($payload['photoStudents']->pluck('student_id')->all())->toBe([2])
        ->and($payload['photoStudentOptions'])->toHaveCount(34)
        ->and($payload['photoGradeClassOptions']->pluck('label')->all())->toBe(['4A','5A']);
    $other=$this->service->payload($this->request,['academic_year_id'=>2]);
    expect($other['photoTotal'])->toBe(0);
});

it('does not leak another campus through filters or student options', function () {
    $query=Mockery::mock();
    $query->shouldReceive('where','orderBy')->andReturnSelf();
    $query->shouldReceive('get')->andReturn(collect([(object)['id'=>1,'campus_name_en'=>'BCH']]));
    $user=Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('accessibleCampuses')->andReturn($query);
    $this->request->setUserResolver(fn()=>$user);
    $payload=$this->service->payload($this->request,['academic_year_id'=>1,'campus_id'=>2,'photo_student_id'=>35]);
    expect($payload['photoTotal'])->toBe(0)->and($payload['photoStudentOptions'])->toBeEmpty();
    $all=$this->service->payload($this->request,['academic_year_id'=>1]);
    expect($all['photoStudents']->pluck('campus_id')->unique()->all())->toBe([1]);
});

it('prints each student once and omits inactive, withdrawn and missing photos', function () {
    DB::table('tb_student_enrollment')->insert(['id'=>100,'student_id'=>1,'academic_year_id'=>1,'campus_id'=>1,'grade_id'=>2,'class_id'=>2]);
    DB::table('tb_student')->where('id',2)->update(['status'=>0]);
    DB::table('tb_student_enrollment')->where('student_id',3)->update(['enrollment_status'=>'withdrawn']);
    DB::table('tb_student')->where('id',4)->update(['photo_path'=>'students/deleted.png']);
    $payload=$this->service->payload($this->request,['academic_year_id'=>1]);
    expect($payload['photoTotal'])->toBe(34)->and($payload['photoMissingCount'])->toBe(2);
    $printed=$payload['photoPages']->flatten(1);
    expect($printed)->toHaveCount(32)->and($printed->where('student_id',1))->toHaveCount(1)
        ->and($printed->firstWhere('student_id',1)->grade_label)->toBe('5B');
});

it('splits large campus reports into sheets without mixing grades or campuses for both sizes', function () {
    foreach (['4x6'=>16,'3x4'=>30] as $size=>$capacity) {
        $payload=$this->service->payload($this->request,['academic_year_id'=>1,'photo_size'=>$size]);
        expect($payload['photoPages'])->toHaveCount($size==='4x6'?5:4);
        foreach ($payload['photoPages'] as $page) {
            expect($page->count())->toBeLessThanOrEqual($capacity)
                ->and($page->map(fn($row)=>$row->campus_id.':'.$row->grade_id.':'.$row->class_id)->unique())->toHaveCount(1);
        }
        $document=new DOMDocument;
        @$document->loadHTML(view('reports.student-photo-print',$payload)->render());
        $xpath=new DOMXPath($document);
        expect($xpath->query('//section[@class="student-photo-sheet"]')->length)->toBe($payload['photoPages']->count())
            ->and($xpath->query('//img[@class="student-photo-print-image"]')->length)->toBe(35)
            ->and($xpath->query('//section[@class="student-photo-sheet"]/h1')->item(0)->textContent)->toContain('4A', 'Photo size: '.$size.'cm')
            ->and($xpath->query('//body')->item(0)->getAttribute('class'))->toContain('student-photo-size-'.$size);
    }
});

it('adds the tab after profile labels and renders searchable filters', function () {
    $request=Request::create('/reports','GET',['type'=>'student-photo','academic_year_id'=>1,'campus_id'=>1]);
    $request->setUserResolver($this->request->getUserResolver());
    $data=(new ReportsController)->index($request)->getData();
    $tabs=array_keys($data['reportTypes']);
    expect($tabs[array_search('student-profile-label',$tabs)+1])->toBe('student-photo');
    $html=view('reports._student-photo-filters',$data)->render();
    expect($html)->toContain('reportCampusValue','reportGradeClassValue','reportPhotoStudentValue','STUDENT 02','4A');
});

it('validates print dimensions and grade selections', function () {
    foreach (['photo_size'=>'5x7','grade_class'=>'invalid'] as $key=>$value) {
        $request=Request::create('/reports/student-photo','GET',[$key=>$value]);
        $request->setUserResolver($this->request->getUserResolver());
        expect(fn()=>(new ReportsController)->show($request,'student-photo'))->toThrow(ValidationException::class);
    }
});

it('paginates only the preview and prints all matching students', function () {
    $filters=['academic_year_id'=>1,'campus_id'=>1,'preview_page_size'=>'25','preview_page'=>2];
    $preview=$this->service->payload($this->request,$filters,true);
    expect($preview['photoStudents'])->toHaveCount(10)->and($preview['photoTotal'])->toBe(35)
        ->and($preview['photoPagination']['from'])->toBe(26)->and($preview['photoPagination']['to'])->toBe(35);
    $print=$this->service->payload($this->request,$filters);
    expect($print['photoStudents'])->toHaveCount(35)->and($print['photoPages']->flatten(1))->toHaveCount(34);
});

it('keeps the preview empty until a campus is selected and clears it when campus is removed', function () {
    $waiting=$this->service->payload($this->request,['academic_year_id'=>1],true);
    expect($waiting['hasDataFilter'])->toBeFalse()->and($waiting['photoStudents'])->toBeEmpty()
        ->and($waiting['photoStudentOptions'])->toBeEmpty()->and($waiting['photoGradeClassOptions'])->toBeEmpty()
        ->and($waiting['photoPagination'])->toBeNull()->and($waiting['photoCampuses'])->toHaveCount(2);
    expect(view('reports._student-photo-preview',$waiting)->render())->toContain('Select an Academic Year and Campus')->not->toContain('<img');
    $selected=$this->service->payload($this->request,['academic_year_id'=>1,'campus_id'=>1],true);
    expect($selected['hasDataFilter'])->toBeTrue()->and($selected['photoTotal'])->toBe(35)
        ->and($selected['photoStudents']->pluck('campus_id')->unique()->all())->toBe([1]);
    $cleared=$this->service->payload($this->request,['academic_year_id'=>1,'campus_id'=>'','photo_student_id'=>2],true);
    expect($cleared['hasDataFilter'])->toBeFalse()->and($cleared['photoStudents'])->toBeEmpty();
});
