<?php

use App\Http\Controllers\ReportsController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Schema::create('tb_academic_year', function (Blueprint $t) {
        $t->id(); $t->string('academic_year'); $t->string('period_type'); $t->softDeletes();
    });
    Schema::create('tb_school_info', function (Blueprint $t) {
        $t->id(); $t->softDeletes();
        foreach (['campus_name_en','campus_name_kh','school_name_en','school_name_kh','logo_path','address_province_id','address_district_id','address_commune_id','address_village_id','address_kh'] as $column) $t->string($column)->nullable();
    });
    Schema::create('tb_grade', function (Blueprint $t) {
        $t->id(); $t->string('grade'); $t->string('grade_short_name'); $t->integer('grade_order'); $t->softDeletes();
    });
    Schema::create('tb_class', function (Blueprint $t) {
        $t->id(); $t->string('class_name'); $t->integer('class_order'); $t->softDeletes();
    });
    Schema::create('tb_student', function (Blueprint $t) {
        $t->id(); $t->integer('status')->default(1);
        foreach (explode(',', 'student_no,student_id,photo_path,family_number,full_name_en,full_name_kh,gender,gender_kh,date_of_birth,nationality_country_id,birth_country_id,birth_province_id,birth_district_id,birth_commune_id,birth_village_id,address_country_id,address_province_id,address_district_id,address_commune_id,address_village_id,address_house_no_en,address_house_no_kh,address_street_en,address_street_kh,current_address_en,current_address_kh,home_phone,email,previous_school,experienced_english,test_result,tested_by,remarks') as $column) $t->string($column)->nullable();
    });
    Schema::create('tb_student_enrollment', function (Blueprint $t) {
        $t->id();
        foreach (['student_id','academic_year_id','campus_id','grade_id','class_id','session_id','academic_track_id'] as $column) $t->integer($column)->nullable();
        $t->integer('status')->default(1); $t->string('enrollment_status')->default('active');
        $t->date('enrolled_on')->nullable(); $t->date('ended_on')->nullable();
    });
    Schema::create('tb_family_member', function (Blueprint $t) {
        $t->id(); $t->softDeletes();
        foreach (['full_name_en','full_name_kh','relationship_type','phone','email','occupation','occupation_en','occupation_kh','workplace','nationality_en','nationality_kh'] as $column) $t->string($column)->nullable();
    });
    Schema::create('tb_student_family_member', function (Blueprint $t) {
        $t->integer('student_id'); $t->integer('family_member_id'); $t->string('relationship_type'); $t->boolean('is_primary_contact')->default(false); $t->timestamps();
    });
    DB::table('tb_academic_year')->insert(['id'=>1,'academic_year'=>'2025-2026','period_type'=>'regular']);
    DB::table('tb_school_info')->insert([['id'=>1,'campus_name_en'=>'BCH'],['id'=>2,'campus_name_en'=>'BKK']]);
    DB::table('tb_grade')->insert(['id'=>1,'grade'=>'4','grade_short_name'=>'4','grade_order'=>4]);
    DB::table('tb_class')->insert(['id'=>1,'class_name'=>'A','class_order'=>1]);
    for ($id=1; $id<=206; $id++) {
        DB::table('tb_student')->insert(['id'=>$id,'student_id'=>'S'.$id,'full_name_en'=>'Student '.$id,'full_name_kh'=>'សិស្ស','date_of_birth'=>$id===1?'2015-12-01':($id===205?'2010-01-20':'2012-06-10')]);
        DB::table('tb_student_enrollment')->insert(['id'=>$id,'student_id'=>$id,'academic_year_id'=>1,'campus_id'=>$id===206?2:1,'grade_id'=>1,'class_id'=>1]);
    }
    DB::table('tb_family_member')->insert([
        ['id'=>1,'full_name_en'=>'Z Parent','relationship_type'=>'mother','occupation_en'=>'Teacher'],
        ['id'=>2,'full_name_en'=>'A Parent','relationship_type'=>'mother','occupation_en'=>'Accountant'],
    ]);
    DB::table('tb_student_family_member')->insert([
        ['student_id'=>1,'family_member_id'=>1,'relationship_type'=>'mother'],
        ['student_id'=>205,'family_member_id'=>2,'relationship_type'=>'mother'],
    ]);
    $this->user=Mockery::mock(User::class)->makePartial();
    $this->user->shouldReceive('isSuperAdmin')->andReturn(true);
    $this->payload=function (array $filters=[], bool $preview=true) {
        $request=Request::create('/reports','GET',array_merge(['type'=>'moeys-id-number-book','academic_year_id'=>1,'campus_id'=>1],$filters));
        app()->instance('request',$request);
        $request->setUserResolver(fn()=>$this->user);
        return (new ReflectionMethod(ReportsController::class,'reportPayload'))->invoke(new ReportsController,$request,'moeys-id-number-book',$preview);
    };
});

it('sorts the full filtered list naturally before pagination across loading batches', function () {
    $first=($this->payload)(['sort_by'=>'full_name_en','sort_dir'=>'asc']);
    $second=($this->payload)(['sort_by'=>'full_name_en','sort_dir'=>'asc','preview_page'=>2]);
    $last=($this->payload)(['sort_by'=>'full_name_en','sort_dir'=>'asc','preview_page'=>9]);
    $descending=($this->payload)(['sort_by'=>'full_name_en','sort_dir'=>'desc']);
    expect($first['enrollments']->pluck('student_id')->all())->toBe(range(1,25))
        ->and($second['enrollments']->pluck('student_id')->all())->toBe(range(26,50))
        ->and($last['enrollments']->pluck('student_id')->all())->toBe(range(201,205))
        ->and($descending['enrollments']->pluck('student_id')->all())->toBe(range(205,181))
        ->and($first['studentListPagination']['total'])->toBe(205);
});

it('sorts dates chronologically in both languages', function (string $column) {
    $data=($this->payload)(['selected_columns'=>[$column],'sort_by'=>$column,'preview_page_size'=>'all']);
    expect($data['enrollments']->first()->student_id)->toBe(205)
        ->and($data['enrollments']->last()->student_id)->toBe(1);
})->with(['date_of_birth','date_of_birth_kh']);

it('sorts family columns and preserves the same order for export', function () {
    $filters=['selected_columns'=>['mother_name_en','mother_occupation'],'sort_by'=>'mother_name_en','sort_dir'=>'desc','preview_page_size'=>'all'];
    $preview=($this->payload)($filters);
    $export=($this->payload)($filters,false);
    expect($preview['enrollments']->take(2)->pluck('student_id')->all())->toBe([1,205])
        ->and($export['enrollments']->pluck('id')->all())->toBe($preview['enrollments']->pluck('id')->all());
    $occupations=($this->payload)(array_replace($filters,['sort_by'=>'mother_occupation']));
    expect($occupations['enrollments']->take(2)->pluck('student_id')->all())->toBe([1,205]);
});

it('renders sortable headers with matching arrow states and toggles while resetting the page', function () {
    $data=($this->payload)(['sort_by'=>'full_name_en','sort_dir'=>'asc','preview_page'=>2]);
    $document=new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.view('reports._get-student-list',$data)->render());
    $xpath=new DOMXPath($document);
    $headers=$xpath->query('//thead/tr/th');
    expect($headers->length)->toBe(count($data['selectedColumns'])+1);
    foreach ($headers as $header) {
        $link=$xpath->query('a',$header)->item(0);
        expect($link)->not->toBeNull();
        parse_str(parse_url($link->getAttribute('href'),PHP_URL_QUERY),$query);
        expect($query['preview_page'])->toBe('1')->and($query['campus_id'])->toBe('1');
        if ($query['sort_by']==='full_name_en') {
            expect($query['sort_dir'])->toBe('desc')->and($header->getAttribute('aria-sort'))->toBe('ascending')
                ->and($link->textContent)->toContain('↑');
        } else {
            expect($query['sort_dir'])->toBe('asc')->and($header->getAttribute('aria-sort'))->toBe('none')
                ->and($link->textContent)->toContain('↕');
        }
    }
});

it('reverses the numbering column across pages with descending numbers', function () {
    $data=($this->payload)(['sort_by'=>'row_no','sort_dir'=>'desc','preview_page'=>2]);
    expect($data['enrollments']->pluck('student_id')->all())->toBe(range(180,156));
    $html=view('reports._get-student-list',$data)->render();
    expect($html)->toContain('<td>180</td>','<td>156</td>','descending');
});

it('validates sorting and clears a sort when its column is deselected', function () {
    foreach (['sort_by'=>'not_a_column','sort_dir'=>'invalid'] as $key=>$value) {
        expect(fn()=>($this->payload)([$key=>$value]))->toThrow(ValidationException::class);
    }
    $data=($this->payload)(['selected_columns'=>['student_id'],'sort_by'=>'full_name_en']);
    expect($data['filters']['sort_by'])->toBe('')->and($data['enrollments']->pluck('id')->all())->toBe(range(1,25));
});

it('keeps campus access restrictions when sorting', function () {
    $query=Mockery::mock();
    $query->shouldReceive('pluck')->with('tb_school_info.id')->andReturn(collect([1]));
    $this->user=Mockery::mock(User::class)->makePartial();
    $this->user->shouldReceive('isSuperAdmin')->andReturn(false);
    $this->user->shouldReceive('accessibleCampuses')->andReturn($query);
    $data=($this->payload)(['campus_id'=>'','sort_by'=>'full_name_en','sort_dir'=>'desc']);
    expect($data['studentListPagination']['total'])->toBe(205)
        ->and($data['enrollments']->pluck('campus_id')->unique()->all())->toBe([1]);
});
