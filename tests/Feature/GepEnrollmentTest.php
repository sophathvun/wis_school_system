<?php

use App\Http\Controllers\GepEnrollmentController;
use App\Models\{GepEnrollment,GepStudent,Student,User};
use App\Support\{GepEnrollmentPermissions,ProgramWorkspace};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function(){
    Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');});
    Schema::create('access_permissions',function(Blueprint $t){$t->id();$t->string('code')->unique();$t->string('module');$t->string('action');$t->string('name');$t->timestamps();});
    Schema::create('access_roles',function(Blueprint $t){$t->id();$t->string('code');});
    Schema::create('access_role_permissions',function(Blueprint $t){$t->integer('role_id');$t->integer('permission_id');$t->timestamps();$t->unique(['role_id','permission_id']);});
    Schema::create('access_department_permissions',fn(Blueprint $t)=>$t->integer('permission_id'));
    Schema::create('access_user_permission_overrides',fn(Blueprint $t)=>$t->integer('permission_id'));
    Schema::create('tb_school_info',function(Blueprint $t){$t->id();$t->string('campus_name_en');$t->boolean('status')->default(true);$t->softDeletes();});
    Schema::create('tb_student',function(Blueprint $t){$t->id();$t->string('student_no')->unique();$t->string('student_id')->nullable()->unique();foreach(['full_name_en','full_name_kh','gender','home_phone','email','photo_path','current_address_en','previous_school'] as $field)$t->string($field)->nullable();$t->date('date_of_birth')->nullable();$t->boolean('status')->default(true);$t->timestamps();});
    Schema::create('tb_student_number_sequence',function(Blueprint $t){$t->id();$t->integer('last_number');});
    Schema::create('tb_student_enrollment',function(Blueprint $t){$t->id();$t->integer('student_id');$t->integer('campus_id');$t->integer('academic_year_id')->default(1);$t->integer('class_id')->default(1);$t->integer('grade_id')->default(1);});
    Schema::create('tb_academic_year',function(Blueprint $t){$t->id();$t->string('academic_year');$t->string('period_type')->default('regular');});
    Schema::create('tb_grade',function(Blueprint $t){$t->id();$t->string('grade');$t->string('grade_short_name')->nullable();$t->integer('grade_order');});
    Schema::create('tb_class',function(Blueprint $t){$t->id();$t->integer('academic_year_id');$t->integer('grade_id');$t->string('class_name');});
    Schema::create('tb_country',function(Blueprint $t){$t->id();foreach(['country_name_en','country_name_kh','nationality_name_en','nationality_name_kh'] as $name)$t->string($name)->nullable();$t->boolean('status')->default(1);});
    Schema::create('tb_occupation',function(Blueprint $t){$t->id();$t->string('occupation_name_en');$t->string('occupation_name_kh')->nullable();$t->boolean('status')->default(1);$t->softDeletes();});
    foreach(['province'=>'country','district'=>'province','commune'=>'district','village'=>'commune'] as $child=>$parent)Schema::create('tb_'.$child,function(Blueprint $t)use($child,$parent){$t->id();$t->integer($parent.'_id');$t->string($child.'_name_en');$t->string($child.'_name_kh')->nullable();$t->boolean('status')->default(1);});
    DB::table('tb_country')->insert([['id'=>1,'country_name_en'=>'Cambodia','nationality_name_en'=>'Cambodian'],['id'=>2,'country_name_en'=>'Thailand','nationality_name_en'=>'Thai']]);
    DB::table('tb_occupation')->insert(['id'=>1,'occupation_name_en'=>'Teacher']);
    foreach(['province'=>'country','district'=>'province','commune'=>'district','village'=>'commune'] as $child=>$parent)DB::table('tb_'.$child)->insert(['id'=>1,$parent.'_id'=>1,$child.'_name_en'=>ucfirst($child).' One']);
    DB::table('tb_academic_year')->insert([['id'=>1,'academic_year'=>'2026-2027'],['id'=>2,'academic_year'=>'2025-2026']]);
    DB::table('tb_grade')->insert(['id'=>1,'grade'=>'1','grade_order'=>1]);
    DB::table('tb_class')->insert([['id'=>1,'academic_year_id'=>1,'grade_id'=>1,'class_name'=>'1A'],['id'=>2,'academic_year_id'=>2,'grade_id'=>1,'class_name'=>'1B']]);
    Schema::create('tb_gep_academic_year',function(Blueprint $t){$t->id();$t->string('academic_year');$t->boolean('status')->default(true);});
    Schema::create('tb_gep_term',function(Blueprint $t){$t->id();$t->string('term_name');$t->boolean('status')->default(true);});
    Schema::create('tb_gep_term_assignment',function(Blueprint $t){$t->id();$t->integer('academic_year_id');$t->integer('term_id');$t->integer('sort_order');$t->string('lifecycle_status');});
    foreach(['level'=>'level_name','class_type'=>'type_name','class'=>'class_name','group'=>'session_name'] as $table=>$name)Schema::create('tb_gep_'.$table,function(Blueprint $t)use($name,$table){$t->id();$t->string($name);$t->integer('sort_order')->default(1);$t->boolean('status')->default(true);if($table==='group')$t->string('group_code');});
    DB::table('users')->insert(['id'=>1,'name'=>'GEP Registrar']);DB::table('access_roles')->insert([['id'=>1,'code'=>'central-office-admin'],['id'=>2,'code'=>'campus']]);
    DB::table('tb_school_info')->insert([['id'=>1,'campus_name_en'=>'BKK'],['id'=>2,'campus_name_en'=>'STD']]);
    DB::table('tb_gep_academic_year')->insert(['id'=>1,'academic_year'=>'2026']);
    DB::table('tb_gep_term')->insert(['id'=>1,'term_name'=>'Term 1']);
    DB::table('tb_gep_term_assignment')->insert([['id'=>1,'academic_year_id'=>1,'term_id'=>1,'sort_order'=>1,'lifecycle_status'=>'started'],['id'=>2,'academic_year_id'=>1,'term_id'=>1,'sort_order'=>2,'lifecycle_status'=>'pending']]);
    foreach(['level'=>['level_name'=>'Level 1'],'class_type'=>['type_name'=>'Full-Time'],'class'=>['class_name'=>'A'],'group'=>['session_name'=>'Morning','group_code'=>'M']] as $table=>$values)DB::table('tb_gep_'.$table)->insert(['id'=>1,...$values]);
    DB::table('tb_student')->insert([['id'=>1,'student_no'=>'00000100','student_id'=>'WIS001','full_name_en'=>'EXISTING STUDENT','gender'=>'M','date_of_birth'=>'2010-01-01'],['id'=>2,'student_no'=>'00000101','student_id'=>'WIS002','full_name_en'=>'OTHER CAMPUS STUDENT','gender'=>'F','date_of_birth'=>'2011-01-01']]);
    DB::table('tb_student_number_sequence')->insert(['id'=>1,'last_number'=>101]);
    DB::table('tb_student_enrollment')->insert([['id'=>1,'student_id'=>1,'campus_id'=>1],['id'=>2,'student_id'=>2,'campus_id'=>2]]);
    (require database_path('migrations/2026_11_24_000001_create_gep_enrollments.php'))->up();
    (require database_path('migrations/2026_11_24_000002_add_gep_enrollment_photo_and_parents.php'))->up();
    (require database_path('migrations/2026_11_24_000003_separate_gep_student_profiles.php'))->up();
    $this->user=Mockery::mock(User::class)->makePartial();$this->user->id=1;$this->user->shouldReceive('isSuperAdmin')->andReturn(true);
    $this->request=function(array $data=[],string $method='POST'){$r=Request::create('/gep/students/enrollment',$method,$data);app()->instance('request',$r);$r->setUserResolver(fn()=>$this->user);return $r;};
    $this->payload=['source'=>'existing','student_id'=>1,'term_assignment_id'=>1,'campus_id'=>1,'level_id'=>1,'class_type_id'=>1,'class_id'=>1,'group_id'=>1,'enrollment_status'=>'active','enrolled_on'=>'2026-11-24'];
    $this->controller=app(GepEnrollmentController::class);
    $this->campusUser=function(array $denied=[]){
        $user=Mockery::mock(User::class)->makePartial();$user->id=1;$user->shouldReceive('isSuperAdmin')->andReturn(false);
        $user->shouldReceive('canAccessCampus')->andReturnUsing(fn($id)=>(int)$id===1);
        $user->shouldReceive('accessibleCampuses')->andReturnUsing(fn()=>App\Models\SchoolInfo::where('id',1));
        $user->shouldReceive('hasPermission')->andReturnUsing(fn($code,$campus)=>!in_array($code,$denied,true)&&(int)$campus===1);
        $this->user=$user;return $user;
    };
});

it('enrolls an existing student with a GEP identity and leaves every WIS field and enrollment unchanged',function(){
    expect($this->user->isSuperAdmin())->toBeTrue();
    expect(GepEnrollmentPermissions::allows($this->user,'create'))->toBeTrue();
    $before=DB::table('tb_student')->get()->toJson();$wis=DB::table('tb_student_enrollment')->get()->toJson();
    $request=($this->request)($this->payload);
    expect($request->user())->toBe($this->user);
    expect(GepEnrollmentPermissions::allows($request->user(),'create'))->toBeTrue();
    $response=$this->controller->save($request);
    expect($response->getStatusCode())->toBe(201)->and($response->getData(true)['data']['gep_student']['gep_code'])->toBe('GEP-000001')
        ->and(DB::table('tb_student')->get()->toJson())->toBe($before)->and(DB::table('tb_student_enrollment')->get()->toJson())->toBe($wis)
        ->and(DB::table('tb_gep_enrollment_history')->value('action'))->toBe('created');
});

it('creates a new student with optional guardian blank and no WIS enrollment',function(){
    $data=[...$this->payload,'source'=>'new','student_id'=>null,'full_name_en'=>'NEW GEP STUDENT','gender'=>'F','date_of_birth'=>'2012-06-15'];
    $this->controller->save(($this->request)($data));
    $student=GepStudent::firstOrFail();
    expect($student->profile['full_name_en'])->toBe('NEW GEP STUDENT')->and($student->student_id)->toBeNull()->and($student->student_identifier)->toBe('GEP-000001')->and(GepEnrollment::first()->guardian_name)->toBeNull()
        ->and(Student::count())->toBe(2)->and(DB::table('tb_student_enrollment')->count())->toBe(2)->and(DB::table('tb_student_number_sequence')->value('last_number'))->toBe(101);
});

it('rejects duplicate student term enrollment and permits another pending term without duplicating identity',function(){
    $this->controller->save(($this->request)($this->payload));
    expect(fn()=>$this->controller->save(($this->request)($this->payload)))->toThrow(ValidationException::class);
    $this->controller->save(($this->request)([...$this->payload,'term_assignment_id'=>2,'enrollment_status'=>'pending']));
    expect(GepStudent::count())->toBe(1)->and(GepEnrollment::count())->toBe(2);
});

it('rejects invalid settings or lifecycle without creating a student or audit entry',function($change,$updates){
    foreach($updates as [$table,$values])DB::table($table)->where('id',1)->update($values);
    expect(fn()=>$this->controller->save(($this->request)([...$this->payload,...$change])))->toThrow(ValidationException::class);
    expect(GepEnrollment::count())->toBe(0)->and(GepStudent::count())->toBe(0)->and(DB::table('tb_gep_enrollment_history')->count())->toBe(0);
})->with([
    'future active'=>[['term_assignment_id'=>2],[]], 'finished'=>[[],[['tb_gep_term_assignment',['lifecycle_status'=>'finished']]]],
    'inactive year'=>[[],[['tb_gep_academic_year',['status'=>false]]]], 'inactive term'=>[[],[['tb_gep_term',['status'=>false]]]],
    'inactive level'=>[[],[['tb_gep_level',['status'=>false]]]], 'unknown class'=>[['class_id'=>999],[]],
    'unknown campus'=>[['campus_id'=>999],[]], 'inactive campus'=>[[],[['tb_school_info',['status'=>false]]]],
]);

it('checks duplicates within GEP and leaves an independent WIS record unchanged',function(){
    $data=[...$this->payload,'source'=>'new','student_id'=>null,'full_name_en'=>' existing student ','date_of_birth'=>'2010-01-01','gender'=>'M'];
    $this->controller->save(($this->request)($data));
    expect(fn()=>$this->controller->save(($this->request)($data)))->toThrow(ValidationException::class);
    expect(Student::count())->toBe(2)->and(GepStudent::count())->toBe(1);
});

it('enforces campus grants on creation, student lookup, lists, edit and history',function(){
    $this->controller->save(($this->request)([...$this->payload,'student_id'=>2,'campus_id'=>2]));$other=GepEnrollment::firstOrFail();
    ($this->campusUser)();
    expect(fn()=>$this->controller->save(($this->request)([...$this->payload,'campus_id'=>2])))->toThrow(HttpException::class);
    expect(fn()=>$this->controller->save(($this->request)([...$this->payload,'student_id'=>2])))->toThrow(HttpException::class);
    expect(fn()=>$this->controller->save(($this->request)($this->payload,'PUT'),$other))->toThrow(HttpException::class);
    expect(fn()=>$this->controller->history(($this->request)([],'GET'),$other))->toThrow(HttpException::class);
    $search=$this->controller->students(($this->request)(['q'=>'STUDENT','campus_id'=>1],'GET'))->getData(true);
    expect(array_column($search['data'],'student_id'))->toBe(['WIS001']);
    $list=$this->controller->fetch(($this->request)([],'GET'))->getData(true);expect($list['summary']['total'])->toBe(0);
});

it('protects individual actions when a user has only view permission',function(){
    $this->controller->save(($this->request)($this->payload));$row=GepEnrollment::firstOrFail();
    ($this->campusUser)(array_map(fn($a)=>'gep-students.enrollment.'.$a,array_keys(GepEnrollmentPermissions::ACTIONS)));
    expect($this->controller->fetch(($this->request)([],'GET'))->getData(true)['records']['data'][0]['permissions'])->toBe(['edit'=>false,'delete'=>false,'history'=>false]);
    expect(fn()=>$this->controller->save(($this->request)($this->payload)))->toThrow(HttpException::class);
    expect(fn()=>$this->controller->save(($this->request)($this->payload,'PUT'),$row))->toThrow(HttpException::class);
    expect(fn()=>$this->controller->delete(($this->request)([],'DELETE'),$row))->toThrow(HttpException::class);
});

it('updates only GEP information, records changes and retains deleted activity and student identity',function(){
    $this->controller->save(($this->request)($this->payload));$row=GepEnrollment::firstOrFail();$original=Student::find(1)->toJson();
    $this->controller->save(($this->request)([...$this->payload,'notes'=>'Updated GEP note','full_name_en'=>'MUST NOT CHANGE'],'PUT'),$row);
    expect($row->fresh()->notes)->toBe('Updated GEP note')->and(Student::find(1)->toJson())->toBe($original);
    $history=$this->controller->history(($this->request)([],'GET'),$row)->getData(true)['data'];
    expect(array_column($history,'action'))->toBe(['updated','created']);
    $this->controller->delete(($this->request)([],'DELETE'),$row);
    expect(GepEnrollment::count())->toBe(0)->and(GepEnrollment::withTrashed()->count())->toBe(1)->and(GepStudent::count())->toBe(1)->and(Student::count())->toBe(2);
    expect(DB::table('tb_gep_enrollment_history')->count())->toBe(3);
    $this->controller->save(($this->request)($this->payload));expect(GepEnrollment::count())->toBe(1)->and(GepStudent::count())->toBe(1);
});

it('paginates and summarizes the filtered GEP records and keeps completed historical students visible',function(){
    $this->controller->save(($this->request)($this->payload));
    $this->controller->save(($this->request)([...$this->payload,'student_id'=>2]));
    DB::table('tb_gep_enrollment')->where('id',1)->update(['enrollment_status'=>'completed']);
    $list=$this->controller->fetch(($this->request)(['enrollment_status'=>'completed','perPage'=>'all','page'=>99],'GET'))->getData(true);
    expect($list['summary'])->toBe(['total'=>1,'active'=>0,'pending'=>0,'completed'=>1])->and($list['records']['current_page'])->toBe(1)->and($list['records']['data'])->toHaveCount(1);
    $filtered=$this->controller->fetch(($this->request)(['search'=>'GEP-000002'],'GET'))->getData(true);expect($filtered['records']['data'])->toHaveCount(1);
    $this->controller->fetch(($this->request)(['campus_id'=>2],'GET'))->getData(true);expect(GepEnrollment::count())->toBe(2);
});

it('blocks changes and deletion after the term has finished',function(){
    $this->controller->save(($this->request)($this->payload));$row=GepEnrollment::firstOrFail();
    DB::table('tb_gep_term_assignment')->where('id',1)->update(['lifecycle_status'=>'finished']);
    expect(fn()=>$this->controller->save(($this->request)($this->payload,'PUT'),$row))->toThrow(ValidationException::class);
    expect(fn()=>$this->controller->delete(($this->request)([],'DELETE'),$row))->toThrow(ValidationException::class);
    expect(DB::table('tb_gep_enrollment_history')->count())->toBe(1);
});

it('renders enrollment page data with campus scoped permissions',function(){
    ($this->campusUser)();$view=$this->controller->index(($this->request)([],'GET'));
    expect($view->name())->toBe('gep.enrollments')->and($view->getData()['options']['campuses']->pluck('id')->all())->toBe([1]);
});

it('filters student lookup by year campus and class on the same permitted enrollment',function(){
    DB::table('tb_student_enrollment')->where('student_id',2)->update(['academic_year_id'=>2,'class_id'=>2]);
    $lookup=fn($filters)=>$this->controller->students(($this->request)($filters,'GET'))->getData(true)['data'];
    expect(array_column($lookup(['academic_year_id'=>1,'campus_id'=>1,'class_id'=>1]),'id'))->toBe([1])
        ->and($lookup(['academic_year_id'=>2,'campus_id'=>1]))->toBe([])
        ->and(array_column($lookup(['academic_year_id'=>2,'class_id'=>2]),'id'))->toBe([2]);
    DB::table('tb_student_enrollment')->insert(['student_id'=>2,'campus_id'=>1,'academic_year_id'=>1,'class_id'=>1]);
    // Separate historical rows must not satisfy different filters together.
    expect($lookup(['academic_year_id'=>2,'campus_id'=>1,'class_id'=>2]))->toBe([]);
    DB::table('tb_student_enrollment')->where('student_id',2)->where('campus_id',1)->delete();
    ($this->campusUser)();
    expect(array_column($lookup([]),'id'))->toBe([1]);
    $options=$this->controller->index(($this->request)([],'GET'))->getData()['options'];
    expect($options['lookupYears']->pluck('id')->all())->toBe([1])->and($options['lookupClasses']->pluck('id')->all())->toBe([1]);
});

it('saves optional mother father and guardian details without changing shared WIS information',function(){
    $wis=DB::table('tb_student')->get()->toJson();
    $this->controller->save(($this->request)([...$this->payload,'mother_name_en'=>'MOTHER NAME','mother_name_kh'=>'Mother Khmer',
        'mother_phone'=>'012 345 678','mother_occupation'=>'Teacher','mother_nationality'=>'Cambodian','mother_workplace'=>'Phnom Penh',
        'father_name_en'=>'FATHER NAME','father_phone'=>'098 765 432','guardian_name_en'=>'','guardian_phone'=>'']));
    $row=GepEnrollment::firstOrFail();
    expect($row->parent_information['mother']['name_en'])->toBe('MOTHER NAME')
        ->and($row->parent_information['mother']['occupation'])->toBe('Teacher')
        ->and($row->parent_information['father']['name_en'])->toBe('FATHER NAME')
        ->and($row->parent_information['guardian']['name_en'])->toBeNull()
        ->and(DB::table('tb_student')->get()->toJson())->toBe($wis);
    $this->controller->save(($this->request)([...$this->payload,'mother_name_en'=>'UPDATED MOTHER'],'PUT'),$row);
    expect($row->fresh()->parent_information['mother']['name_en'])->toBe('UPDATED MOTHER');
    $audit=json_decode(DB::table('tb_gep_enrollment_history')->orderByDesc('id')->value('after_values'),true);
    expect($audit['parent_information']['mother']['name_en'])->toBe('UPDATED MOTHER');
});

it('accepts an entirely blank optional parent section',function(){
    $empty=[];
    foreach(['mother','father','guardian'] as $parent)foreach(['name_en','name_kh','occupation','nationality','phone','workplace'] as $field)$empty[$parent.'_'.$field]='';
    $this->controller->save(($this->request)([...$this->payload,...$empty]));
    expect(GepEnrollment::firstOrFail()->parent_information['guardian']['name_en'])->toBeNull();
});

it('stores a cropped GEP photo as exactly 600 by 800 WebP under 40 KB and preserves the WIS photo',function(){
    \Illuminate\Support\Facades\Storage::fake('public');
    DB::table('tb_student')->where('id',1)->update(['photo_path'=>'student_photos/wis-original.webp']);
    $request=($this->request)($this->payload);
    $request->files->set('photo',\Illuminate\Http\UploadedFile::fake()->image('crop.png',600,800));
    $this->controller->save($request);
    $member=GepStudent::firstOrFail();$bytes=\Illuminate\Support\Facades\Storage::disk('public')->get($member->photo_path);
    $size=getimagesizefromstring($bytes);
    expect($size[0])->toBe(600)->and($size[1])->toBe(800)->and($size['mime'])->toBe('image/webp')
        ->and(strlen($bytes))->toBeLessThan(40*1024)->and(Student::find(1)->photo_path)->toBe('student_photos/wis-original.webp');
    expect($this->controller->fetch(($this->request)([],'GET'))->getData(true)['records']['data'][0]['gep_student']['photo_url'])->toContain($member->photo_path);
    $row=GepEnrollment::firstOrFail();$request=($this->request)($this->payload,'PUT');
    $request->files->set('photo',\Illuminate\Http\UploadedFile::fake()->image('second.png',600,800));
    $this->controller->save($request,$row);
    \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($member->photo_path);
    \Illuminate\Support\Facades\Storage::disk('public')->assertExists($member->fresh()->photo_path);
});

it('rejects uncropped photos before any enrollment or file is created',function(){
    \Illuminate\Support\Facades\Storage::fake('public');
    $request=($this->request)($this->payload);
    $request->files->set('photo',\Illuminate\Http\UploadedFile::fake()->image('original.png',1200,1600));
    expect(fn()=>$this->controller->save($request))->toThrow(ValidationException::class);
    expect(GepEnrollment::count())->toBe(0)->and(\Illuminate\Support\Facades\Storage::disk('public')->allFiles())->toBe([]);
});

it('rolls back identity enrollment and photo when audit storage fails',function(){
    \Illuminate\Support\Facades\Storage::fake('public');
    DB::statement("CREATE TRIGGER reject_gep_audit BEFORE INSERT ON tb_gep_enrollment_history BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
    $request=($this->request)([...$this->payload,'source'=>'new','full_name_en'=>'ROLLBACK STUDENT','gender'=>'F','date_of_birth'=>'2012-06-15']);
    $request->files->set('photo',\Illuminate\Http\UploadedFile::fake()->image('crop.png',600,800));
    expect(fn()=>$this->controller->save($request))->toThrow(\Illuminate\Database\QueryException::class);
    expect(Student::count())->toBe(2)->and(GepStudent::count())->toBe(0)->and(GepEnrollment::count())->toBe(0)
        ->and(\Illuminate\Support\Facades\Storage::disk('public')->allFiles())->toBe([]);
});

it('measures the exact saved portrait and protects photo processing from view only users',function(){
    $request=($this->request)([]);
    $request->files->set('photo',\Illuminate\Http\UploadedFile::fake()->image('crop.png',600,800));
    $result=$this->controller->photoSize($request,app(\App\Services\StudentPhotoProcessor::class))->getData(true);
    expect($result['width'])->toBe(600)->and($result['height'])->toBe(800)->and($result['bytes'])->toBeLessThan(40*1024);
    ($this->campusUser)(['gep-students.enrollment.create','gep-students.enrollment.edit']);
    expect(fn()=>$this->controller->photoSize(($this->request)([]),app(\App\Services\StudentPhotoProcessor::class)))->toThrow(HttpException::class);
});

it('stores all WIS style fields in a separate editable GEP profile',function(){
    $wis=DB::table('tb_student')->get()->toJson();
    $fields=['full_name_en'=>'GEP COPY NAME','full_name_kh'=>'ឈ្មោះសិស្ស','gender'=>'F','date_of_birth'=>'2012-02-03',
        'nationality_country_id'=>1,'family_number'=>'GEP-F001','home_phone'=>'012 345 678','email'=>'gep@example.test','remarks'=>'GEP only remarks',
        'birth_country_id'=>1,'birth_province_id'=>1,'birth_district_id'=>1,'birth_commune_id'=>1,'birth_village_id'=>1,
        'address_country_id'=>1,'address_province_id'=>1,'address_district_id'=>1,'address_commune_id'=>1,'address_village_id'=>1,
        'address_house_no_en'=>'10','address_house_no_kh'=>'១០','address_street_en'=>'12','address_street_kh'=>'១២',
        'current_address_en'=>'GEP home address','current_address_kh'=>'អាសយដ្ឋាន','previous_school'=>'Another School',
        'experienced_english'=>'Two years','test_result'=>'Level 3','tested_by'=>'GEP Teacher'];
    $this->controller->save(($this->request)([...$this->payload,...$fields,'student_identifier'=>'WIS001','mother_occupation_id'=>1,'mother_nationality_country_id'=>1]));
    $row=GepEnrollment::firstOrFail();$profile=$row->gepStudent->profile;
    foreach($fields as $key=>$value)expect((string)$profile[$key])->toBe((string)($key==='home_phone'?'+85512345678':$value));
    expect($profile['gender_kh'])->toBe('ស្រី')->and($row->gepStudent->student_identifier)->toBe('WIS001')
        ->and(DB::table('tb_student')->get()->toJson())->toBe($wis);
    $this->controller->save(($this->request)([...$this->payload,'full_name_en'=>'UPDATED GEP NAME','current_address_en'=>'Updated GEP address'],'PUT'),$row);
    expect($row->fresh()->gepStudent->profile['full_name_en'])->toBe('UPDATED GEP NAME')
        ->and($row->fresh()->gepStudent->profile['birth_village_id'])->toBe(1)
        ->and(DB::table('tb_student')->get()->toJson())->toBe($wis);
    $list=$this->controller->fetch(($this->request)(['search'=>'UPDATED GEP NAME'],'GET'))->getData(true);
    expect($list['summary']['total'])->toBe(1)->and($list['records']['data'][0]['gep_student'])->not->toHaveKey('student');
});

it('keeps copied WIS details as a snapshot when the WIS profile changes',function(){
    $preview=$this->controller->profile(($this->request)(['student_id'=>1],'GET'))->getData(true);
    expect($preview['profile']['date_of_birth'])->toBe('2010-01-01');
    $this->controller->save(($this->request)($this->payload));
    DB::table('tb_student')->where('id',1)->update(['full_name_en'=>'CHANGED IN WIS']);
    $member=GepStudent::firstOrFail();
    expect($member->profile['full_name_en'])->toBe('EXISTING STUDENT')->and($member->profile['date_of_birth'])->toBe('2010-01-01');
    $preview=$this->controller->profile(($this->request)(['student_id'=>1],'GET'))->getData(true);
    expect($preview['profile']['full_name_en'])->toBe('EXISTING STUDENT');
});

it('can find and enroll a GEP only student again without creating any WIS identity',function(){
    $this->controller->save(($this->request)([...$this->payload,'source'=>'new','student_id'=>null,'full_name_en'=>'GEP ONLY STUDENT','date_of_birth'=>null,'student_identifier'=>'GEP-ONLY-100']));
    $member=GepStudent::firstOrFail();
    $matches=$this->controller->students(($this->request)(['q'=>'GEP ONLY','campus_id'=>1],'GET'))->getData(true)['data'];
    expect($matches[0]['gep_student_id'])->toBe($member->id);
    $profile=$this->controller->profile(($this->request)(['gep_student_id'=>$member->id],'GET'))->getData(true);
    expect($profile['profile']['full_name_en'])->toBe('GEP ONLY STUDENT');
    $this->controller->save(($this->request)([...$this->payload,'student_id'=>null,'gep_student_id'=>$member->id,'term_assignment_id'=>2,'enrollment_status'=>'pending']));
    expect(GepStudent::count())->toBe(1)->and(GepEnrollment::count())->toBe(2)->and(Student::count())->toBe(2);
});

it('validates location hierarchy and returns only children of the selected location',function(){
    $this->controller->save(($this->request)([...$this->payload,'birth_country_id'=>1,'birth_province_id'=>1,'birth_district_id'=>1]));
    $list=$this->controller->locations(($this->request)(['type'=>'province','parent_id'=>1],'GET'))->getData(true);
    expect(array_column($list['data'],'id'))->toBe([1]);
    expect($this->controller->locations(($this->request)(['type'=>'province','parent_id'=>2],'GET'))->getData(true)['data'])->toBe([]);
    expect(fn()=>$this->controller->save(($this->request)([...$this->payload,'student_id'=>2,'birth_country_id'=>2,'birth_province_id'=>1])))->toThrow(ValidationException::class);
    expect(fn()=>$this->controller->save(($this->request)([...$this->payload,'student_id'=>2,'address_district_id'=>1])))->toThrow(ValidationException::class);
    ($this->campusUser)(['gep-students.enrollment.create','gep-students.enrollment.edit']);
    expect(fn()=>$this->controller->locations(($this->request)(['type'=>'province','parent_id'=>1],'GET')))->toThrow(HttpException::class);
});

it('links only permitted GEP families and protects selected profiles by campus',function(){
    $this->controller->save(($this->request)([...$this->payload,'family_number'=>'GEP-FAMILY','mother_name_en'=>'GEP MOTHER']));
    $first=GepStudent::firstOrFail();
    $this->controller->save(($this->request)([...$this->payload,'source'=>'new','student_id'=>null,'full_name_en'=>'GEP SIBLING','existing_family_id'=>$first->id]));
    expect(GepStudent::latest('id')->first()->profile['family_number'])->toBe('GEP-FAMILY');
    $this->controller->save(($this->request)([...$this->payload,'student_id'=>2,'campus_id'=>2,'family_number'=>'OTHER FAMILY']));
    $other=GepStudent::latest('id')->first();
    ($this->campusUser)();
    expect(fn()=>$this->controller->profile(($this->request)(['gep_student_id'=>$other->id],'GET')))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    expect(fn()=>$this->controller->profile(($this->request)(['student_id'=>2],'GET')))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    expect(fn()=>$this->controller->save(($this->request)([...$this->payload,'source'=>'new','student_id'=>null,'full_name_en'=>'CAMPUS CHILD','existing_family_id'=>$other->id])))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    expect(GepStudent::count())->toBe(3);
});

it('rejects duplicate GEP student IDs and unknown country and parent settings',function(){
    $this->controller->save(($this->request)([...$this->payload,'student_identifier'=>'GEP100']));
    foreach([['student_identifier'=>'GEP100'],['nationality_country_id'=>999],['mother_occupation_id'=>999]] as $invalid){
        expect(fn()=>$this->controller->save(($this->request)([...$this->payload,'source'=>'new','student_id'=>null,'full_name_en'=>'ANOTHER GEP',...$invalid])))->toThrow(ValidationException::class);
    }
    expect(GepStudent::count())->toBe(1);
});

it('backfills legacy GEP profiles without changing WIS and normalizes gender for the form',function(){
    DB::table('tb_student')->where('id',1)->update(['gender'=>'Male']);
    $this->controller->save(($this->request)($this->payload));
    $wis=DB::table('tb_student')->get()->toJson();
    $migration=require database_path('migrations/2026_11_24_000003_separate_gep_student_profiles.php');
    $migration->down();$migration->up();
    $member=GepStudent::firstOrFail();
    expect($member->profile['full_name_en'])->toBe('EXISTING STUDENT')->and($member->profile['gender'])->toBe('M')
        ->and($member->profile['date_of_birth'])->toBe('2010-01-01')->and($member->student_identifier)->toBe('GEP-000001')
        ->and(DB::table('tb_student')->get()->toJson())->toBe($wis);
});

it('retains international GEP contact numbers and reserves automatic GEP IDs',function(){
    $this->controller->save(($this->request)([...$this->payload,'home_phone'=>'+66 81 234 5678','mother_phone'=>'0066 89 123 4567','mother_name_en'=>'MOTHER']));
    expect(GepStudent::firstOrFail()->profile['home_phone'])->toBe('+66812345678')
        ->and(GepEnrollment::firstOrFail()->parent_information['mother']['phone'])->toBe('+66891234567');
    expect(fn()=>$this->controller->save(($this->request)([...$this->payload,'source'=>'new','student_id'=>null,'full_name_en'=>'SECOND GEP STUDENT','student_identifier'=>'GEP-000020'])))->toThrow(ValidationException::class);
});

it('limits Western lookup to regular years and uses enrollment year and campus for grade options',function(){
    DB::table('tb_academic_year')->insert(['id'=>3,'academic_year'=>'Summer 2026','period_type'=>'summer']);
    DB::table('tb_class')->where('id',1)->update(['academic_year_id'=>2]);
    DB::table('tb_class')->insert(['id'=>3,'academic_year_id'=>3,'grade_id'=>1,'class_name'=>'SUMMER']);
    DB::table('tb_student_enrollment')->where('student_id',2)->update(['academic_year_id'=>3,'class_id'=>3]);
    $options=$this->controller->index(($this->request)([],'GET'))->getData()['options'];
    expect($options['lookupYears']->pluck('id')->all())->toBe([1]);
    expect($options['lookupClasses']->count())->toBe(1)
        ->and($options['lookupClasses'][0]->academic_year_id)->toBe(1)
        ->and($options['lookupClasses'][0]->campus_id)->toBe(1)
        ->and($options['lookupClasses'][0]->id)->toBe(1);
    $matches=$this->controller->students(($this->request)(['academic_year_id'=>1,'campus_id'=>1,'grade_id'=>1,'class_id'=>1],'GET'))->getData(true)['data'];
    expect(array_column($matches,'id'))->toBe([1]);
    expect($this->controller->students(($this->request)(['campus_id'=>2],'GET'))->getData(true)['data'])->toBe([]);
    expect(fn()=>$this->controller->students(($this->request)(['academic_year_id'=>3],'GET')))->toThrow(ValidationException::class);
});

it('distinguishes grades sharing a class and returns only the selected campus enrollments',function(){
    DB::table('tb_grade')->insert(['id'=>2,'grade'=>'2','grade_order'=>2]);
    DB::table('tb_student_enrollment')->where('student_id',2)->update(['grade_id'=>2]);
    $options=$this->controller->index(($this->request)([],'GET'))->getData()['options'];
    expect($options['lookupClasses']->where('campus_id',1)->pluck('grade_id')->all())->toBe([1])
        ->and($options['lookupClasses']->where('campus_id',2)->pluck('grade_id')->all())->toBe([2]);
    expect($this->controller->students(($this->request)(['academic_year_id'=>1,'campus_id'=>2,'grade_id'=>1,'class_id'=>1],'GET'))->getData(true)['data'])->toBe([]);
    expect(array_column($this->controller->students(($this->request)(['academic_year_id'=>1,'campus_id'=>2,'grade_id'=>2,'class_id'=>1],'GET'))->getData(true)['data'],'id'))->toBe([2]);
});
