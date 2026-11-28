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
    Schema::create('tb_country',function(Blueprint $t){$t->id();foreach(['country_name_en','country_name_kh','nationality_name_en','nationality_name_kh','flag_path'] as $name)$t->string($name)->nullable();$t->boolean('status')->default(1);});
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
    (require database_path('migrations/2026_11_26_000001_create_gep_student_id_sequence.php'))->up();
    (require database_path('migrations/2026_11_26_000002_add_gep_enrollment_student_type.php'))->up();
    (require database_path('migrations/2026_11_26_000004_create_gep_families.php'))->up();
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

it('promotes into a pending term while preserving identity family source status and WIS data',function(){
    $this->controller->save(($this->request)($this->payload));
    $row=GepEnrollment::firstOrFail();$profile=$row->gepStudent->toArray();$wis=DB::table('tb_student_enrollment')->get()->toJson();
    $data=[...$this->payload,'source_term'=>1,'enrollments'=>[$row->id],'term_assignment_id'=>2];
    app(App\Http\Controllers\GepPromotionController::class)->store(($this->request)($data));
    $new=GepEnrollment::orderByDesc('id')->first();
    expect($new->enrollment_status)->toBe('pending')->and($new->student_type)->toBe('old')->and($new->gep_student_id)->toBe($row->gep_student_id)
        ->and($row->fresh()->enrollment_status)->toBe('active')->and($row->gepStudent->fresh()->toArray())->toBe($profile)
        ->and(DB::table('tb_student_enrollment')->get()->toJson())->toBe($wis)
        ->and(DB::table('tb_gep_enrollment_history')->where('action','promoted')->count())->toBe(1);
});

it('promotes into a started term and completes the source without duplicating students',function(){
    $this->controller->save(($this->request)($this->payload));$row=GepEnrollment::firstOrFail();
    DB::table('tb_gep_term_assignment')->where('id',2)->update(['lifecycle_status'=>'started']);
    $data=[...$this->payload,'source_term'=>1,'enrollments'=>[$row->id],'term_assignment_id'=>2];
    $controller=app(App\Http\Controllers\GepPromotionController::class);$controller->store(($this->request)($data));
    expect($row->fresh()->enrollment_status)->toBe('completed')->and(GepEnrollment::orderByDesc('id')->value('enrollment_status'))->toBe('active')->and(GepStudent::count())->toBe(1);
    expect(fn()=>$controller->store(($this->request)($data)))->toThrow(ValidationException::class);
    expect(GepEnrollment::count())->toBe(2);
});

it('rejects invalid promotion destinations and ineligible source statuses',function(){
    $this->controller->save(($this->request)($this->payload));$row=GepEnrollment::firstOrFail();
    $controller=app(App\Http\Controllers\GepPromotionController::class);
    $data=[...$this->payload,'source_term'=>1,'enrollments'=>[$row->id]];
    expect(fn()=>$controller->store(($this->request)($data)))->toThrow(ValidationException::class);
    $data['term_assignment_id']=2;$row->enrollment_status='withdrawn';$row->save();
    expect(fn()=>$controller->store(($this->request)($data)))->toThrow(ValidationException::class);
    $row->enrollment_status='completed';$row->save();DB::table('tb_gep_level')->where('id',1)->update(['status'=>0]);
    expect(fn()=>$controller->store(($this->request)($data)))->toThrow(ValidationException::class);
    expect(GepEnrollment::count())->toBe(1);
});

it('rolls back the entire promotion when a later student is already enrolled',function(){
    $this->controller->save(($this->request)($this->payload));
    $this->controller->save(($this->request)([...$this->payload,'student_id'=>2]));
    $rows=GepEnrollment::orderBy('id')->get();
    $this->controller->save(($this->request)([...$this->payload,'student_id'=>2,'term_assignment_id'=>2,'enrollment_status'=>'pending']));
    $history=DB::table('tb_gep_enrollment_history')->count();
    expect(fn()=>app(App\Http\Controllers\GepPromotionController::class)->store(($this->request)([...$this->payload,'source_term'=>1,'term_assignment_id'=>2,'enrollments'=>$rows->pluck('id')->all()])))->toThrow(ValidationException::class);
    expect(GepEnrollment::count())->toBe(3)->and(DB::table('tb_gep_enrollment_history')->count())->toBe($history);
});

it('restricts promotion to permitted source and destination campuses',function(){
    $this->controller->save(($this->request)([...$this->payload,'student_id'=>2,'campus_id'=>2]));$row=GepEnrollment::firstOrFail();
    ($this->campusUser)();$data=[...$this->payload,'source_term'=>1,'term_assignment_id'=>2,'enrollments'=>[$row->id]];
    expect(fn()=>app(App\Http\Controllers\GepPromotionController::class)->store(($this->request)($data)))->toThrow(HttpException::class);
    $data['campus_id']=2;
    expect(fn()=>app(App\Http\Controllers\GepPromotionController::class)->store(($this->request)($data)))->toThrow(HttpException::class);
    expect(GepEnrollment::count())->toBe(1);
});

it('requires a dedicated campus promotion grant even when enrollment create and edit are allowed',function(){
    $this->controller->save(($this->request)($this->payload));$row=GepEnrollment::firstOrFail();
    ($this->campusUser)(['gep-students.promotion.promote']);
    $request=($this->request)([...$this->payload,'source_term'=>1,'term_assignment_id'=>2,'enrollments'=>[$row->id]]);
    $controller=app(App\Http\Controllers\GepPromotionController::class);
    expect($controller->index(($this->request)(['source_term'=>1],'GET'))->getData()['canPromote'])->toBeFalse();
    expect(fn()=>$controller->store($request))->toThrow(HttpException::class);
    expect(GepEnrollment::count())->toBe(1);
});

it('allows campus staff with promotion permission to promote without general enrollment edit or create grants',function(){
    $this->controller->save(($this->request)($this->payload));$row=GepEnrollment::firstOrFail();
    ($this->campusUser)(['gep-students.enrollment.create','gep-students.enrollment.edit']);
    app(App\Http\Controllers\GepPromotionController::class)->store(($this->request)([...$this->payload,'source_term'=>1,'term_assignment_id'=>2,'enrollments'=>[$row->id]]));
    expect(GepEnrollment::count())->toBe(2)->and(GepEnrollment::orderByDesc('id')->value('campus_id'))->toBe(1);
});

it('does not allow promotion to change a student campus even for staff with access to both campuses',function(){
    $this->controller->save(($this->request)($this->payload));$row=GepEnrollment::firstOrFail();
    expect(fn()=>app(App\Http\Controllers\GepPromotionController::class)->store(($this->request)([...$this->payload,'campus_id'=>2,'source_term'=>1,'term_assignment_id'=>2,'enrollments'=>[$row->id]])))->toThrow(ValidationException::class);
    expect(GepEnrollment::count())->toBe(1);
});

it('registers promotion permissions idempotently and grants no campus role automatically',function(){
    $migration=require database_path('migrations/2026_11_27_000002_add_gep_promotion_permission.php');$migration->up();$migration->up();
    $permission=DB::table('access_permissions')->where('code','gep-students.promotion.promote')->first();
    expect($permission->module)->toBe('gep-students.promotion')->and($permission->action)->toBe('promote');
    expect(DB::table('access_role_permissions')->where('permission_id',$permission->id)->pluck('role_id')->all())->toBe([1]);
    $tree=App\Support\PermissionHierarchy::tree(App\Models\Permission::all());
    expect(json_encode($tree))->toContain('gep-students.promotion.promote');
});

it('loads only eligible promotion candidates and requires an explicit source term',function(){
    $this->controller->save(($this->request)($this->payload));
    $controller=app(App\Http\Controllers\GepPromotionController::class);
    expect($controller->index(($this->request)([],'GET'))->getData()['records']->total())->toBe(0);
    expect($controller->index(($this->request)(['source_term'=>1],'GET'))->getData()['records']->total())->toBe(1);
    $viewData=$controller->index(($this->request)(['source_term'=>1],'GET'))->getData();
    $html=view('gep._promotion-content',$viewData)->with('errors',new Illuminate\Support\ViewErrorBag)->render();
    expect($html)->toContain('Destination enrollment','EXISTING STUDENT','Review promotion');
    GepEnrollment::first()->update(['enrollment_status'=>'withdrawn']);
    expect($controller->index(($this->request)(['source_term'=>1],'GET'))->getData()['records']->total())->toBe(0);
});

it('lists promotion source years newest first and terms in descending order within each year',function(){
    DB::table('tb_gep_academic_year')->insert([['id'=>2,'academic_year'=>'2027'],['id'=>3,'academic_year'=>'2025']]);
    DB::table('tb_gep_term_assignment')->insert([
        ['id'=>3,'academic_year_id'=>2,'term_id'=>1,'sort_order'=>1,'lifecycle_status'=>'pending'],
        ['id'=>4,'academic_year_id'=>3,'term_id'=>1,'sort_order'=>4,'lifecycle_status'=>'finished'],
        ['id'=>5,'academic_year_id'=>2,'term_id'=>1,'sort_order'=>4,'lifecycle_status'=>'pending'],
    ]);
    $data=app(App\Http\Controllers\GepPromotionController::class)->index(($this->request)([],'GET'))->getData();
    expect($data['terms']->pluck('id')->all())->toBe([5,3,2,1,4]);
});

it('returns live promotion search results and rows scoped to the term filters and campus permissions',function(){
    $this->controller->save(($this->request)($this->payload));
    $this->controller->save(($this->request)([...$this->payload,'student_id'=>2,'campus_id'=>2]));
    $controller=app(App\Http\Controllers\GepPromotionController::class);
    $query=function(array $params)use($controller){$request=($this->request)($params,'GET');$request->headers->set('Accept','application/json');return $controller->index($request)->getData(true);};
    $data=$query(['source_term'=>1,'search'=>'EXISTING']);
    expect($data['total'])->toBe(1)->and($data['students'][0]['name'])->toBe('EXISTING STUDENT')->and($data['rows'])->toContain('EXISTING STUDENT')->and(count($data['students']))->toBe(1);
    $id=$data['students'][0]['id'];
    expect($query(['source_term'=>1,'search'=>$id])['total'])->toBe(1);
    expect($query(['source_term'=>2,'search'=>'EXISTING'])['total'])->toBe(0);
    expect($query(['source_term'=>1,'search'=>'NOT A STUDENT'])['students'])->toBe([]);
    ($this->campusUser)();
    expect($query(['source_term'=>1,'search'=>'OTHER CAMPUS'])['total'])->toBe(0);
    $view=$controller->index(($this->request)(['source_term'=>1],'GET'))->getData();
    $html=view('gep._promotion-content',$view)->with('errors',new Illuminate\Support\ViewErrorBag)->render();
    expect($html)->toContain('promotionSearchResults','role="combobox"')->not->toContain('>Find students</button>');
});

it('cascades promotion source options by term campus level class and group using eligible enrollments',function(){
    foreach([2,3] as $id){
        DB::table('tb_gep_class')->insert(['id'=>$id,'class_name'=>'Class '.$id]);
        DB::table('tb_gep_level')->insert(['id'=>$id,'level_name'=>'Level '.$id]);
        DB::table('tb_gep_group')->insert(['id'=>$id,'session_name'=>'Group '.$id,'group_code'=>'G'.$id]);
    }
    foreach([
        ['campus_id'=>1,'class_id'=>1,'level_id'=>1,'group_id'=>1],
        ['campus_id'=>1,'class_id'=>1,'level_id'=>2,'group_id'=>2],
        ['campus_id'=>1,'class_id'=>2,'level_id'=>2,'group_id'=>1],
        ['campus_id'=>2,'class_id'=>2,'level_id'=>1,'group_id'=>2],
        ['term_assignment_id'=>2,'class_id'=>3,'level_id'=>3,'group_id'=>3,'enrollment_status'=>'pending'],
    ] as $i=>$values)$this->controller->save(($this->request)([...$this->payload,'source'=>'new','student_id'=>null,'full_name_en'=>'FILTER STUDENT '.$i,...$values]));
    $controller=app(App\Http\Controllers\GepPromotionController::class);
    $data=$controller->index(($this->request)(['source_term'=>1,'campus_id'=>1,'class_id'=>1,'level_id'=>2],'GET'))->getData();
    expect($data['sourceOptions']['campus_id']->pluck('id')->all())->toBe([1,2])
        ->and($data['sourceOptions']['class_id']->pluck('id')->all())->toBe([1,2])
        ->and($data['sourceOptions']['level_id']->pluck('id')->all())->toBe([1,2])
        ->and($data['sourceOptions']['group_id']->pluck('id')->all())->toBe([2])
        ->and($data['records']->total())->toBe(1);
    $data=$controller->index(($this->request)(['source_term'=>1,'campus_id'=>2],'GET'))->getData();
    expect($data['sourceOptions']['class_id']->pluck('id')->all())->toBe([2])
        ->and($data['sourceOptions']['level_id']->pluck('id')->all())->toBe([1]);
    $data=$controller->index(($this->request)(['source_term'=>1,'campus_id'=>1,'class_id'=>2,'level_id'=>1,'group_id'=>2],'GET'))->getData();
    expect($data['filters']['level_id'])->toBe(1)->and(isset($data['filters']['class_id']))->toBeFalse()->and(isset($data['filters']['group_id']))->toBeFalse()
        ->and($data['sourceOptions']['class_id']->pluck('id')->all())->toBe([1])
        ->and($data['sourceOptions']['group_id']->pluck('id')->all())->toBe([1])->and($data['records']->total())->toBe(1);
    ($this->campusUser)();
    $data=$controller->index(($this->request)(['source_term'=>1],'GET'))->getData();
    expect($data['sourceOptions']['campus_id']->pluck('id')->all())->toBe([1])->and($data['records']->total())->toBe(3);
    $data=$controller->index(($this->request)([],'GET'))->getData();
    foreach($data['sourceOptions'] as $options)expect($options->count())->toBe(0);
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
    expect($student->profile['family_number'])->toBe('GEP-F26190001');
    expect($student->profile['full_name_en'])->toBe('NEW GEP STUDENT')->and($student->student_id)->toBeNull()->and($student->student_identifier)->toBe('26190001')->and(GepEnrollment::first()->guardian_name)->toBeNull()
        ->and(Student::count())->toBe(2)->and(DB::table('tb_student_enrollment')->count())->toBe(2)->and(DB::table('tb_student_number_sequence')->value('last_number'))->toBe(101);
});

it('rejects duplicate student term enrollment and permits another pending term without duplicating identity',function(){
    $this->controller->save(($this->request)($this->payload));
    expect(fn()=>$this->controller->save(($this->request)($this->payload)))->toThrow(ValidationException::class);
    $this->controller->save(($this->request)([...$this->payload,'term_assignment_id'=>2,'enrollment_status'=>'pending']));
    expect(GepStudent::count())->toBe(1)->and(GepEnrollment::count())->toBe(2);
    expect(GepEnrollment::orderBy('id')->pluck('student_type')->all())->toBe(['new','old']);
});

it('allows staff to correct student type and preserves it when editing without a type',function(){
    $this->controller->save(($this->request)([...$this->payload,'student_type'=>'old']));
    $record=GepEnrollment::firstOrFail();
    expect($record->student_type)->toBe('old');
    $this->controller->save(($this->request)($this->payload),$record);
    expect($record->fresh()->student_type)->toBe('old');
    $this->controller->save(($this->request)([...$this->payload,'student_type'=>'new']),$record);
    expect($record->fresh()->student_type)->toBe('new');
    expect(fn()=>$this->controller->save(($this->request)([...$this->payload,'student_type'=>'other']),$record))->toThrow(ValidationException::class);
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
    expect($list['summary'])->toBe(['total'=>1,'male'=>1,'female'=>0,'active'=>0,'pending'=>0,'completed'=>1,'withdrawn'=>0,'new'=>1,'new-male'=>1,'new-female'=>0])->and($list['records']['current_page'])->toBe(1)->and($list['records']['data'])->toHaveCount(1);
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

it('continues the six digit sequence across academic years after direct imports',function(){
    DB::table('tb_gep_student')->insert(['student_identifier'=>'26193510','source'=>'new','profile'=>json_encode(['full_name_en'=>'IMPORTED'])]);
    $allocator=app(\App\Services\GepStudentIdAllocator::class);
    DB::transaction(function()use($allocator){expect($allocator->allocate('2027'))->toBe('27193511');});
    DB::transaction(function()use($allocator){expect($allocator->allocate('2026'))->toBe('26193512');});
    expect(GepStudent::first()->student_identifier)->toBe('26193510');
    expect(fn()=>$allocator->allocate('2027'))->toThrow(LogicException::class);
});

it('advances the counter for imported models and never renumbers an existing GEP student',function(){
    $member=GepStudent::create(['student_identifier'=>'25194000','source'=>'new','profile'=>['full_name_en'=>'IMPORTED']]);
    expect($member->profile['family_number'])->toBe('GEP-F25194000');
    expect(DB::table('tb_gep_student_id_sequence')->value('last_number'))->toBe(194000);
    expect(fn()=>$member->update(['student_identifier'=>'26194001']))->toThrow(ValidationException::class);
    $member->refresh();
    DB::transaction(function(){expect(app(\App\Services\GepStudentIdAllocator::class)->allocate('2027'))->toBe('27194001');});
    DB::table('tb_gep_student_id_sequence')->update(['last_number'=>194100]);
    expect(app(\App\Services\GepStudentIdAllocator::class)->synchronize())->toBe(194100);
});

it('previews and imports GEP profiles enrollments and families separately without changing WIS',function(){
    $transfer=app(\App\Services\GepDataTransfer::class);$wis=DB::table('tb_student')->get()->toJson();
    $students=[['student_identifier'=>'26193510','full_name_en'=>'IMPORTED GEP','gender'=>'F','date_of_birth'=>'2010-01-01','home_phone'=>'012345678','nationality_en'=>'Cambodian']];
    DB::beginTransaction();$transfer->import('students',$students,$this->user);DB::rollBack();
    expect(GepStudent::count())->toBe(0)->and(DB::table('tb_gep_student_id_sequence')->value('last_number'))->toBe(190000);
    DB::transaction(fn()=>$transfer->import('students',$students,$this->user));
    expect(GepStudent::count())->toBe(1)->and(GepEnrollment::count())->toBe(0);
    DB::transaction(fn()=>$transfer->import('students',[['student_identifier'=>'26193510','full_name_en'=>'IMPORTED GEP','home_phone'=>'']],$this->user));
    expect(GepStudent::first()->profile['home_phone'])->toBe('+85512345678');
    $row=['student_identifier'=>'26193510','academic_year'=>'2026','term'=>'Term 1','campus'=>'Campus One','level'=>'Level 1','class'=>'A','class_type'=>'Full-Time','group'=>'M','enrollment_status'=>'completed','enrolled_on'=>'2026-01-01'];
    $row['campus']=DB::table('tb_school_info')->where('id',1)->value('campus_name_en');
    DB::table('tb_gep_term_assignment')->where('id',2)->delete();
    DB::transaction(fn()=>$transfer->import('enrollments',[$row],$this->user));
    $family=['student_identifier'=>'26193510','academic_year'=>'2026','term'=>'Term 1','relationship_type'=>'mother','full_name_en'=>'MOTHER NAME','phone'=>'012345678','nationality'=>'Cambodia','occupation'=>'Teacher'];
    DB::transaction(fn()=>$transfer->import('families',[$family],$this->user));
    expect(GepStudent::first()->familyInformation()['mother']['phone'])->toBe('+85512345678')
        ->and(GepEnrollment::first()->enrollment_status)->toBe('completed')
        ->and(DB::table('tb_student')->get()->toJson())->toBe($wis);
    foreach(['students','enrollments','families'] as $type){expect($transfer->samples($type))->toHaveCount(2);expect($transfer->export($type,$this->user))->toHaveCount(1);}
    expect(app(\App\Services\GepStudentIdAllocator::class)->synchronize())->toBe(193510);
});

it('preserves historical GEP IDs during import without advancing the new ID sequence',function(){
    $rows=[['student_identifier'=>'17L7B0010','full_name_en'=>'LEGACY ONE'],['student_identifier'=>'GEP-0020','full_name_en'=>'LEGACY TWO']];
    DB::transaction(fn()=>app(\App\Services\GepDataTransfer::class)->import('students',$rows,$this->user));
    expect(GepStudent::orderBy('id')->pluck('student_identifier')->all())->toBe(['17L7B0010','GEP-0020'])
        ->and(GepStudent::first()->profile['family_number'])->toBe('GEP-F17L7B0010')
        ->and(GepEnrollment::count())->toBe(0)
        ->and(DB::table('tb_gep_student_id_sequence')->value('last_number'))->toBe(190000);
});

it('imports family details before enrollment and preserves them across terms without duplicate exports',function(){
    $transfer=app(\App\Services\GepDataTransfer::class);
    DB::transaction(fn()=>$transfer->import('students',[['student_identifier'=>'26190001','full_name_en'=>'FAMILY PROFILE']],$this->user));
    $family=['student_identifier'=>'26190001','relationship_type'=>'mother','full_name_en'=>'MOTHER','phone'=>'012345678'];
    DB::beginTransaction();$transfer->import('families',[$family],$this->user);DB::rollBack();
    expect(GepStudent::first()->familyInformation())->toBe([]);
    DB::transaction(fn()=>$transfer->import('families',[$family],$this->user));
    expect(GepEnrollment::count())->toBe(0)->and($transfer->export('families',$this->user))->toHaveCount(1);
    $member=GepStudent::first();
    $base=['gep_student_id'=>$member->id,'campus_id'=>1,'level_id'=>1,'class_id'=>1,'class_type_id'=>1,'group_id'=>1,'enrollment_status'=>'pending','enrolled_on'=>'2026-01-01'];
    GepEnrollment::create([...$base,'term_assignment_id'=>1]);GepEnrollment::create([...$base,'term_assignment_id'=>2]);
    DB::transaction(fn()=>$transfer->import('families',[[...$family,'phone'=>'','full_name_en'=>'CORRECTED MOTHER']],$this->user));
    $export=$transfer->export('families',$this->user);
    expect($export)->toHaveCount(1)->and($export[0]['full_name_en'])->toBe('CORRECTED MOTHER')->and($export[0]['phone'])->toBe('+85512345678')
        ->and(array_key_exists('term',$export[0]))->toBeFalse()->and(array_key_exists('academic_year',$export[0]))->toBeFalse();
    expect($transfer->columns('families'))->not->toContain('term')->not->toContain('academic_year');
});

it('copies historical family details into the student profile without changing enrollment snapshots',function(){
    $this->controller->save(($this->request)([...$this->payload,'mother_name_en'=>'EARLIER MOTHER','mother_phone'=>'012345678']));
    $member=GepStudent::first();$profile=$member->profile;unset($profile['parent_information']);$member->update(['profile'=>$profile]);
    $snapshot=DB::table('tb_gep_enrollment')->get()->toJson();
    (require database_path('migrations/2026_11_26_000003_move_gep_family_information_to_students.php'))->up();
    (require database_path('migrations/2026_11_26_000004_create_gep_families.php'))->up();
    expect($member->fresh()->profile['parent_information']['mother']['name_en'])->toBe('EARLIER MOTHER')
        ->and(DB::table('tb_gep_enrollment')->get()->toJson())->toBe($snapshot);
    $this->controller->save(($this->request)([...$this->payload,'gep_student_id'=>$member->id,'student_id'=>null,'term_assignment_id'=>2,'enrollment_status'=>'pending']));
    expect(GepEnrollment::latest('id')->first()->parent_information['mother']['name_en'])->toBe('EARLIER MOTHER');
    $this->controller->save(($this->request)([...$this->payload,'term_assignment_id'=>2,'enrollment_status'=>'pending','mother_name_en'=>'CORRECTED MOTHER']),GepEnrollment::latest('id')->first());
    expect($member->fresh()->familyInformation()['mother']['name_en'])->toBe('CORRECTED MOTHER')
        ->and(GepEnrollment::orderBy('id')->first()->parent_information['mother']['name_en'])->toBe('EARLIER MOTHER');
    $records=$this->controller->fetch(($this->request)([],'GET'))->getData(true)['records']['data'];
    foreach($records as $record)expect($record['parent_information']['mother']['name_en'])->toBe('CORRECTED MOTHER');
});

it('links siblings by Family Number and exports shared parents once',function(){
    $transfer=app(\App\Services\GepDataTransfer::class);
    DB::transaction(fn()=>$transfer->import('students',[
        ['student_identifier'=>'26190001','family_number'=>'GEP-F26190001','full_name_en'=>'FIRST SIBLING'],
        ['student_identifier'=>'26190002','family_number'=>'GEP-F26190001','full_name_en'=>'SECOND SIBLING'],
        ['student_identifier'=>'26190003','family_number'=>'GEP-F26190003','full_name_en'=>'OTHER FAMILY'],
    ],$this->user));
    $mother=['family_number'=>'GEP-F26190001','relationship_type'=>'mother','full_name_en'=>'SHARED MOTHER','phone'=>'012345678'];
    DB::transaction(fn()=>$transfer->import('families',[$mother],$this->user));
    $members=GepStudent::orderBy('id')->get();
    foreach($members->take(2) as $member)expect($member->familyInformation()['mother']['name_en'])->toBe('SHARED MOTHER');
    expect($members[2]->familyInformation())->toBe([])->and(GepEnrollment::count())->toBe(0);
    $export=$transfer->export('families',$this->user);
    expect($export)->toHaveCount(1)->and($export[0]['family_number'])->toBe('GEP-F26190001')->and(array_key_exists('student_identifier',$export[0]))->toBeFalse();
    $parents=$members[1]->familyInformation();$parents['mother']['name_en']='CORRECTED MOTHER';
    DB::transaction(fn()=>$members[1]->saveFamilyInformation($parents,$this->user));
    expect($members[0]->fresh()->familyInformation()['mother']['name_en'])->toBe('CORRECTED MOTHER');
    expect(fn()=>DB::transaction(fn()=>$transfer->import('families',[$mother,$mother],$this->user)))->toThrow(ValidationException::class);
    expect($members[0]->fresh()->familyInformation()['mother']['name_en'])->toBe('CORRECTED MOTHER');
    expect(fn()=>DB::transaction(fn()=>$transfer->import('families',[[...$mother,'family_number'=>'UNKNOWN']],$this->user)))->toThrow(ValidationException::class);
});

it('migrates sibling family details into one record while retaining the original profile snapshots',function(){
    GepStudent::create(['source'=>'new','student_identifier'=>'26190001','profile'=>['family_number'=>'GEP-FSHARED','full_name_en'=>'FIRST','parent_information'=>['mother'=>['name_en'=>'EARLIER MOTHER','phone'=>'+85512345678']]]]);
    GepStudent::create(['source'=>'new','student_identifier'=>'26190002','profile'=>['family_number'=>'GEP-FSHARED','full_name_en'=>'SECOND','parent_information'=>['mother'=>['name_en'=>'LATEST MOTHER']]]]);
    $before=DB::table('tb_gep_student')->orderBy('id')->get()->toJson();
    (require database_path('migrations/2026_11_26_000004_create_gep_families.php'))->up();
    expect(\App\Models\GepFamily::count())->toBe(1)
        ->and(GepStudent::first()->familyInformation()['mother']['name_en'])->toBe('LATEST MOTHER')
        ->and(GepStudent::first()->familyInformation()['mother']['phone'])->toBe('+85512345678')
        ->and(DB::table('tb_gep_student')->orderBy('id')->get()->toJson())->toBe($before);
});

it('previews and applies Family Number CSV uploads without Student ID or term columns',function(){
    $transfer=app(\App\Services\GepDataTransfer::class);
    DB::transaction(fn()=>$transfer->import('students',[['student_identifier'=>'26190001','full_name_en'=>'STUDENT']],$this->user));
    $controller=app(\App\Http\Controllers\GepDataTransferController::class);
    $make=function(bool $apply=false){$r=($this->request)(['apply'=>$apply?'1':'0']);$r->files->set('file',\Illuminate\Http\UploadedFile::fake()->createWithContent('families.csv',"family_number,relationship_type,full_name_en\nGEP-F26190001,mother,MOTHER\n"));$r->setLaravelSession(app('session')->driver());return $r;};
    $controller->upload($make(),'families',$transfer);
    expect(GepStudent::first()->familyInformation())->toBe([]);
    $controller->upload($make(true),'families',$transfer);
    expect(GepStudent::first()->familyInformation()['mother']['name_en'])->toBe('MOTHER');
});

it('blocks changes to a shared family when a sibling is outside permitted campuses',function(){
    $this->controller->save(($this->request)([...$this->payload,'family_number'=>'GEP-FSHARED']));
    $this->controller->save(($this->request)([...$this->payload,'student_id'=>2,'campus_id'=>2,'family_number'=>'GEP-FSHARED']));
    ($this->campusUser)();
    $row=['family_number'=>'GEP-FSHARED','relationship_type'=>'mother','full_name_en'=>'UNAUTHORIZED'];
    expect(fn()=>DB::transaction(fn()=>app(\App\Services\GepDataTransfer::class)->import('families',[$row],$this->user)))->toThrow(HttpException::class);
    expect(fn()=>$this->controller->save(($this->request)([...$this->payload,'mother_name_en'=>'UNAUTHORIZED']),GepEnrollment::orderBy('id')->first()))->toThrow(HttpException::class);
    expect(GepStudent::first()->familyInformation())->toBe([]);
});

it('rejects duplicate upload records and rolls back the entire GEP import',function(){
    $transfer=app(\App\Services\GepDataTransfer::class);
    $rows=[['student_identifier'=>'26193510','full_name_en'=>'FIRST'],['student_identifier'=>'26193510','full_name_en'=>'SECOND']];
    expect(fn()=>DB::transaction(fn()=>$transfer->import('students',$rows,$this->user)))->toThrow(ValidationException::class);
    expect(GepStudent::count())->toBe(0)->and(DB::table('tb_gep_student_id_sequence')->value('last_number'))->toBe(190000);
});

it('transfers full location names in both languages without any internal ID columns',function(){
    $transfer=app(\App\Services\GepDataTransfer::class);
    foreach(['students','enrollments','families'] as $type)
        expect(collect($transfer->columns($type))->contains(fn($field)=>str_ends_with($field,'_id')))->toBeFalse();
    DB::table('tb_village')->where('id',1)->update(['village_name_en'=>'123','village_name_kh'=>'ភូមិមួយ']);
    $row=['student_identifier'=>'26193510','full_name_en'=>'NAMED LOCATIONS','nationality_en'=>'Cambodian','birth_country_en'=>' cambodia ','birth_province_en'=>'Province One','birth_district_en'=>'District One','birth_commune_en'=>'Commune One','birth_village_en'=>'123','birth_village_kh'=>'ភូមិមួយ','address_house_no_en'=>'168B','address_house_no_kh'=>'១៦៨ប','address_street_en'=>'Group 1','current_address_en'=>'Original complete address'];
    $result=DB::transaction(fn()=>$transfer->import('students',[$row],$this->user));
    $profile=GepStudent::first()->profile;
    expect($profile['nationality_country_id'])->toBe(1)->and($profile['birth_village_id'])->toBe(1)->and($result['unresolved_names'])->toBe(0);
    $export=$transfer->export('students',$this->user)[0];
    foreach($row as $field=>$value)expect($export[$field])->toBe($value);
    expect(array_key_exists('birth_village_id',$export))->toBeFalse();
    DB::transaction(fn()=>$transfer->import('students',[$export],$this->user));
    expect(GepStudent::first()->profile['birth_village_id'])->toBe(1);
});

it('retains misspelled and ambiguous addresses without guessing a location or losing text',function(){
    $transfer=app(\App\Services\GepDataTransfer::class);
    DB::table('tb_commune')->insert(['id'=>2,'district_id'=>1,'commune_name_en'=>'Commune One']);
    $row=['student_identifier'=>'26193510','full_name_en'=>'HISTORICAL ADDRESS','address_country_en'=>'Cambodia','address_province_en'=>'Province One','address_district_en'=>'District One','address_commune_en'=>'Commune One','address_village_en'=>'Old vilage spelling','birth_country_kh'=>'កម្ពុជា','birth_province_en'=>'Historical province spelling','birth_village_kh'=>'ភូមិចាស់','current_address_kh'=>'អាសយដ្ឋានដើម'];
    $result=DB::transaction(fn()=>$transfer->import('students',[$row],$this->user));
    $profile=GepStudent::first()->profile;
    expect($profile['address_district_id'])->toBe(1)->and($profile['address_commune_id'])->toBeNull()->and($profile['address_village_id'])->toBeNull()->and($result['unresolved_names'])->toBeGreaterThan(0);
    $export=$transfer->export('students',$this->user)[0];
    foreach($row as $field=>$value)expect($export[$field])->toBe($value);
    DB::transaction(fn()=>$transfer->import('students',[['student_identifier'=>$row['student_identifier'],'full_name_en'=>$row['full_name_en'],'address_village_en'=>'']],$this->user));
    expect($transfer->export('students',$this->user)[0]['address_village_en'])->toBe('Old vilage spelling');
});

it('clears stale location links when an imported parent name changes and preserves the old child text',function(){
    $transfer=app(\App\Services\GepDataTransfer::class);
    $row=['student_identifier'=>'26193510','full_name_en'=>'CHANGED ADDRESS','address_country_en'=>'Cambodia','address_province_en'=>'Province One','address_district_en'=>'District One','address_commune_en'=>'Commune One','address_village_en'=>'Village One'];
    DB::transaction(fn()=>$transfer->import('students',[$row],$this->user));
    DB::transaction(fn()=>$transfer->import('students',[['student_identifier'=>$row['student_identifier'],'full_name_en'=>$row['full_name_en'],'address_country_en'=>'Thailand']],$this->user));
    $profile=GepStudent::first()->profile;
    expect($profile['address_country_id'])->toBe(2)->and($profile['address_province_id'])->toBeNull()->and($profile['address_village_id'])->toBeNull();
    expect($transfer->export('students',$this->user)[0]['address_village_en'])->toBe('Village One');
});

it('imports and exports partial parents with blank English names without losing details',function(){
    $transfer=app(\App\Services\GepDataTransfer::class);
    DB::transaction(fn()=>$transfer->import('students',[['student_identifier'=>'26190001','full_name_en'=>'STUDENT']],$this->user));
    $rows=[['family_number'=>'GEP-F26190001','relationship_type'=>'mother','full_name_kh'=>'ម្តាយ','phone'=>'012345678'],
        ['family_number'=>'GEP-F26190001','relationship_type'=>'father','nationality'=>'Cambodian']];
    DB::transaction(fn()=>$transfer->import('families',$rows,$this->user));
    $export=$transfer->export('families',$this->user);
    expect($export)->toHaveCount(2)->and($export[0]['full_name_en'])->toBe('')->and($export[0]['full_name_kh'])->toBe('ម្តាយ')
        ->and($export[0]['phone'])->toBe('+85512345678')->and($export[1]['nationality'])->toBe('Cambodian');
    $update=DB::transaction(fn()=>$transfer->import('families',[['family_number'=>'GEP-F26190001','relationship_type'=>'mother','full_name_en'=>'','phone'=>'','workplace'=>'Test workplace']],$this->user));
    expect($update['updated'])->toBe(1)->and(GepStudent::first()->familyInformation()['mother']['phone'])->toBe('+85512345678');
    expect(fn()=>DB::transaction(fn()=>$transfer->import('families',[['family_number'=>'GEP-F26190001','relationship_type'=>'guardian','full_name_en'=>'']],$this->user)))->toThrow(ValidationException::class);
});

it('retains imported family spellings and unmatched profile names when editing the enrollment',function(){
    $this->controller->save(($this->request)([...$this->payload,'source'=>'new','student_id'=>null,'full_name_en'=>'IMPORTED FAMILY']));
    $member=GepStudent::first();$transfer=app(\App\Services\GepDataTransfer::class);
    DB::table('tb_gep_term_assignment')->where('id',2)->delete();
    DB::transaction(fn()=>$transfer->import('students',[['student_identifier'=>$member->student_identifier,'full_name_en'=>'IMPORTED FAMILY','address_country_en'=>'Cambodia','address_province_en'=>'Old provinc spelling','address_village_en'=>'Historic vilage']],$this->user));
    $row=['student_identifier'=>$member->student_identifier,'academic_year'=>'2026','term'=>'Term 1','relationship_type'=>'mother','full_name_en'=>'MOTHER NAME','nationality'=>'Historical nationality','occupation'=>'Historical occupaton'];
    $result=DB::transaction(fn()=>$transfer->import('families',[$row],$this->user));
    expect($result['unresolved_names'])->toBe(2);
    $enrollment=GepEnrollment::first();
    $this->controller->save(($this->request)([...$this->payload,'full_name_en'=>'IMPORTED FAMILY','mother_name_en'=>'MOTHER NAME','mother_occupation_id'=>null,'mother_nationality_country_id'=>null,'address_country_id'=>1,'address_province_id'=>null]),$enrollment);
    $export=$transfer->export('families',$this->user)[0];
    expect($export['occupation'])->toBe($row['occupation'])->and($export['nationality'])->toBe($row['nationality'])
        ->and($transfer->export('students',$this->user)[0]['address_village_en'])->toBe('Historic vilage');
    $before=$member->fresh()->profile;$after=\App\Support\GepTransferNames::clearChanged($before,array_replace($before,['address_province_id'=>1]));
    expect(isset($after['transfer_names']['address_village_en']))->toBeFalse();
});

it('writes true Excel workbooks with literal ID phone and formula-like values',function(){
    $path=tempnam(sys_get_temp_dir(),'gep-xlsx');
    try{
        \App\Support\GepTransferWorkbook::write($path,['student_identifier','name','phone'],[['student_identifier'=>'00190001','name'=>'=1+1','phone'=>'+85512345678']]);
        $zip=new ZipArchive;expect($zip->open($path))->toBeTrue();
        $xml=$zip->getFromName('xl/worksheets/sheet1.xml');$zip->close();
        expect($xml)->toContain('00190001')->toContain('+85512345678')->toContain('=1+1')->not->toContain('<f>');
        expect(simplexml_load_string($xml))->not->toBeFalse();
    }finally{@unlink($path);}
});

it('requires a preview of the same CSV before applying a GEP upload',function(){
    \Illuminate\Support\Facades\Storage::fake('local');
    $controller=app(\App\Http\Controllers\GepDataTransferController::class);$transfer=app(\App\Services\GepDataTransfer::class);
    $make=function(string $content,bool $apply=false){
        $r=($this->request)(['apply'=>$apply?'1':'0']);
        $r->files->set('file',\Illuminate\Http\UploadedFile::fake()->createWithContent('students.csv',$content));
        $r->setLaravelSession(app('session')->driver());return $r;
    };
    $csv="student_identifier,full_name_en\n26193510,IMPORTED STUDENT\n";
    expect(fn()=>$controller->upload($make($csv,true),'students',$transfer))->toThrow(ValidationException::class);
    $controller->upload($make($csv),'students',$transfer);
    expect(GepStudent::count())->toBe(0);
    $path=session('gep-transfer-preview-details');
    expect(\Illuminate\Support\Facades\Storage::disk('local')->exists($path))->toBeTrue();
    session()->forget('gep_transfer_result');
    $preview=$controller->index($make($csv))->getData()['preview'];
    expect($preview['result']['applied'])->toBeFalse()
        ->and($preview['rows'][0]['full_name_en'])->toBe('IMPORTED STUDENT');
    $html=view('gep._transfer-preview',['preview'=>$preview])->render();
    expect($html)->toContain('IMPORTED STUDENT','26193510','All 1 rows passed validation.','Apply previewed file');
    expect($controller->index($make($csv))->getData()['preview']['rows'])->toBe($preview['rows']);
    expect(fn()=>$controller->upload($make(str_replace('26193510','26193511',$csv),true),'students',$transfer))->toThrow(ValidationException::class);
    $controller->upload($make($csv,true),'students',$transfer);
    expect(GepStudent::count())->toBe(1)->and(GepEnrollment::count())->toBe(0);
    expect(session('gep-transfer-preview-details'))->toBeNull()
        ->and(\Illuminate\Support\Facades\Storage::disk('local')->exists($path))->toBeFalse();
});

it('shows enrollment preview rows with student names without saving enrollments',function(){
    \Illuminate\Support\Facades\Storage::fake('local');
    DB::table('tb_gep_term_assignment')->where('id',2)->delete();
    $student=GepStudent::create(['source'=>'new','student_identifier'=>'26190001','profile'=>['full_name_en'=>'<TEST STUDENT>']]);
    $csv="student_identifier,academic_year,term,campus,level,class,class_type,group,student_type,enrollment_status,enrolled_on,notes\n26190001,2026,Term 1,BKK,Level 1,A,Full-Time,M,new,active,2026-11-24,Review note\n";
    $request=($this->request)(['apply'=>'0']);$request->setLaravelSession(app('session')->driver());
    $request->files->set('file',\Illuminate\Http\UploadedFile::fake()->createWithContent('enrollments.csv',$csv));
    $controller=app(\App\Http\Controllers\GepDataTransferController::class);
    $response=$controller->upload($request,'enrollments',app(\App\Services\GepDataTransfer::class));
    expect($response->getTargetUrl())->toBe(route('gep.students.section',['studentSection'=>'import-export']));
    $preview=$controller->index($request)->getData()['preview'];
    expect($preview['rows'][0]['student_name'])->toBe('<TEST STUDENT>')
        ->and($preview['result']['created'])->toBe(1)
        ->and(GepEnrollment::count())->toBe(0)->and(DB::table('tb_gep_enrollment_history')->count())->toBe(0);
    $html=view('gep._transfer-preview',['preview'=>$preview])->render();
    expect($html)->toContain('Student ID','Student Name','&lt;TEST STUDENT&gt;','Level 1','2026-11-24','Review note')->not->toContain('<TEST STUDENT>');
    $apply=function(string $token){$r=($this->request)(['use_preview'=>'1','preview_token'=>$token]);$r->setLaravelSession(app('session')->driver());return $r;};
    expect(fn()=>$controller->upload($apply(str_repeat('0',32)),'enrollments',app(\App\Services\GepDataTransfer::class)))->toThrow(ValidationException::class);
    expect(fn()=>$controller->upload($apply($preview['preview_token']),'families',app(\App\Services\GepDataTransfer::class)))->toThrow(ValidationException::class);
    expect(GepEnrollment::count())->toBe(0);
    $controller->upload($apply($preview['preview_token']),'enrollments',app(\App\Services\GepDataTransfer::class));
    expect(GepEnrollment::count())->toBe(1)->and(GepEnrollment::first()->gep_student_id)->toBe($student->id);
    expect(fn()=>$controller->upload($apply($preview['preview_token']),'enrollments',app(\App\Services\GepDataTransfer::class)))->toThrow(ValidationException::class);
});

it('applies every saved CSV row without reuploading when the preview display is limited',function(){
    \Illuminate\Support\Facades\Storage::fake('local');
    $csv="student_identifier,full_name_en\n";foreach(range(1,201) as $i)$csv.=(26190000+$i).",STUDENT {$i}\n";
    $request=($this->request)(['apply'=>'0']);$request->setLaravelSession(app('session')->driver());
    $request->files->set('file',\Illuminate\Http\UploadedFile::fake()->createWithContent('students.csv',$csv));
    $controller=app(\App\Http\Controllers\GepDataTransferController::class);$transfer=app(\App\Services\GepDataTransfer::class);
    $controller->upload($request,'students',$transfer);$preview=$controller->index($request)->getData()['preview'];
    expect($preview['rows'])->toHaveCount(200)->and($preview['all_rows'])->toHaveCount(201)->and(GepStudent::count())->toBe(0);
    $apply=($this->request)(['use_preview'=>'1','preview_token'=>$preview['preview_token']]);$apply->setLaravelSession(app('session')->driver());
    $controller->upload($apply,'students',$transfer);
    expect(GepStudent::count())->toBe(201)->and(GepStudent::where('student_identifier','26190201')->exists())->toBeTrue()
        ->and(session('gep-transfer-preview-details'))->toBeNull();
});

it('streams row progress and confirms only completed preview and apply transactions',function(){
    \Illuminate\Support\Facades\Storage::fake('local');
    $controller=app(\App\Http\Controllers\GepDataTransferController::class);$transfer=app(\App\Services\GepDataTransfer::class);
    $make=function(array $data){$r=($this->request)($data);$r->setLaravelSession(app('session')->driver());$r->headers->set('X-GEP-Progress','1');return $r;};
    $stream=function($response){$content='';ob_start(function($chunk)use(&$content){$content.=$chunk;return '';});try{$response->sendContent();}finally{ob_end_flush();}return array_map(fn($line)=>json_decode($line,true,512,JSON_THROW_ON_ERROR),array_filter(explode("\n",trim($content))));};
    $request=$make(['apply'=>'0']);$request->files->set('file',\Illuminate\Http\UploadedFile::fake()->createWithContent('students.csv',"student_identifier,full_name_en\n26190001,TEST ONE\n26190002,TEST TWO\n"));
    $messages=$stream($controller->upload($request,'students',$transfer));
    expect($messages[0])->toMatchArray(['event'=>'progress','processed'=>0,'total'=>2,'percent'=>0])
        ->and($messages[2])->toMatchArray(['processed'=>2,'percent'=>100])->and(end($messages)['event'])->toBe('complete')
        ->and(end($messages)['result']['applied'])->toBeFalse()->and(GepStudent::count())->toBe(0);
    $preview=$controller->index($request)->getData()['preview'];
    $apply=$make(['use_preview'=>'1','preview_token'=>$preview['preview_token']]);
    $messages=$stream($controller->upload($apply,'students',$transfer));
    expect(end($messages)['result']['applied'])->toBeTrue()->and(GepStudent::count())->toBe(2);
    $invalid=$make(['apply'=>'0']);$invalid->files->set('file',\Illuminate\Http\UploadedFile::fake()->createWithContent('students.csv',"student_identifier,full_name_en\n26190003,VALID\n26190004,\n"));
    $messages=$stream($controller->upload($invalid,'students',$transfer));
    expect(end($messages)['event'])->toBe('error')->and(GepStudent::count())->toBe(2)
        ->and(collect($messages)->where('event','complete'))->toHaveCount(0);
});

it('adds every selected term to GEP dashboard cards and charts',function(){
    $this->controller->save(($this->request)($this->payload));
    $first=GepEnrollment::first();$first->update(['enrollment_status'=>'completed']);
    DB::table('tb_gep_term_assignment')->where('id',1)->update(['lifecycle_status'=>'finished']);
    DB::table('tb_gep_term_assignment')->where('id',2)->update(['lifecycle_status'=>'started']);
    $return=$first->replicate();$return->term_assignment_id=2;$return->enrollment_status='active';$return->student_type='old';$return->save();
    $service=app(\App\Services\GepDashboard::class);
    $data=$service->data(($this->request)(['academic_year_id'=>1,'term_assignment_id'=>''],'GET'));
    expect($data['metrics'])->toMatchArray(['enrollments'=>2,'students'=>2,'male'=>2,'female'=>0,'active'=>1,'completed'=>1,'new'=>1])
        ->and($data['metrics']['students'])->toBe($data['metrics']['active']+$data['metrics']['completed']+$data['metrics']['withdrawn'])
        ->and($data['genderMetrics']['students'])->toBe(2)->and($data['genderMetrics']['male'])->toBe(2)
        ->and($data['levelRows']->sum('total'))->toBe(2)->and($data['campusRows']->sum('total'))->toBe(2)
        ->and($data['trend']->pluck('total')->all())->toBe([1,1]);
    $data=$service->data(($this->request)([],'GET'));
    expect($data['termId'])->toBe(2)->and($data['metrics']['enrollments'])->toBe(1)->and($data['metrics']['new'])->toBe(0);
    $completed=$service->data(($this->request)(['academic_year_id'=>1,'term_assignment_id'=>1],'GET'));
    expect($completed['metrics']['completed'])->toBe(1)->and($completed['campusRows'][0]['completed'])->toBe(1);
    $html=view('gep._dashboard-content',$completed)->render();
    expect($html)->toContain('Completed students','Students by term','Gender overview','EXISTING STUDENT');
});

it('excludes withdrawn enrollments from dashboard charts while preserving summary cards',function(){
    $this->controller->save(($this->request)($this->payload));
    $this->controller->save(($this->request)([...$this->payload,'student_id'=>2]));
    GepEnrollment::where('gep_student_id',2)->update(['enrollment_status'=>'withdrawn']);
    $data=app(\App\Services\GepDashboard::class)->data(($this->request)([],'GET'));
    expect($data['metrics'])->toMatchArray(['students'=>2,'withdrawn'=>1,'male'=>1,'female'=>1])
        ->and($data['genderMetrics'])->toBe(['students'=>1,'male'=>1,'female'=>0,'unknown_gender'=>0])
        ->and($data['levelRows']->sum('total'))->toBe(1)->and($data['trend']->sum('total'))->toBe(1);
    $html=view('gep._dashboard-content',$data)->render();
    expect($html)->toContain('Male 1, female 0, unspecified 0','Excludes withdrawn enrollments.');
});

it('renders an empty GEP dashboard without inventing enrollment data and rejects mismatched terms',function(){
    $service=app(\App\Services\GepDashboard::class);
    $data=$service->data(($this->request)([],'GET'));
    expect($data['metrics'])->toMatchArray(['students'=>0,'enrollments'=>0,'new'=>0,'active'=>0,'male'=>0,'female'=>0])
        ->and($data['recent'])->toHaveCount(0);
    expect(view('gep._dashboard-content',$data)->render())->toContain('No enrollment data for this selection.','No campus enrollments to display.');
    DB::table('tb_gep_academic_year')->insert(['id'=>2,'academic_year'=>'2025']);
    expect(fn()=>$service->data(($this->request)(['academic_year_id'=>2,'term_assignment_id'=>1],'GET')))->toThrow(HttpException::class);
});

it('scopes GEP dashboard charts and recent students to permitted campuses',function(){
    $this->controller->save(($this->request)($this->payload));
    $this->controller->save(($this->request)([...$this->payload,'student_id'=>2,'campus_id'=>2]));
    ($this->campusUser)();$service=app(\App\Services\GepDashboard::class);
    $data=$service->data(($this->request)([],'GET'));
    expect($data['metrics']['students'])->toBe(1)->and($data['campusRows']->pluck('label')->all())->toBe(['BKK'])
        ->and($data['recent']->pluck('campus_id')->all())->toBe([1]);
    expect(fn()=>$service->data(($this->request)(['campus_id'=>2],'GET')))->toThrow(HttpException::class);
    ($this->campusUser)(['gep-students.enrollment.view']);
    expect($service->data(($this->request)([],'GET')))->toBe(['canView'=>false]);
});

it('provides two-row CSV templates and a usable GEP transfer page',function(){
    $controller=app(\App\Http\Controllers\GepDataTransferController::class);$transfer=app(\App\Services\GepDataTransfer::class);
    $request=($this->request)([],'GET');$request->setLaravelSession(app('session')->driver());
    $view=$controller->index($request);expect($view->name())->toBe('gep.data-transfer');
    foreach(['students','enrollments','families'] as $type){
        $response=$controller->download($request,$type,'template',$transfer);
        ob_start();$response->sendContent();$content=ob_get_clean();
        $file=fopen('php://temp','w+');fwrite($file,$content);rewind($file);$rows=[];while(($row=fgetcsv($file,escape:''))!==false)$rows[]=$row;fclose($file);
        expect($rows)->toHaveCount(3)->and($rows[1][0])->toBe($type==='families'?'GEP-F26190001':'26190001')->and($rows[2][0])->toBe($type==='families'?'GEP-F26190001':'26190002');
    }
});

it('restricts GEP transfer exports and imports to permitted campuses',function(){
    $this->controller->save(($this->request)($this->payload));
    $row=GepEnrollment::first();$row->campus_id=2;$row->save();
    $user=($this->campusUser)();$transfer=app(\App\Services\GepDataTransfer::class);
    expect($transfer->export('students',$user))->toBe([])->and($transfer->export('enrollments',$user))->toBe([]);
    expect(fn()=>DB::transaction(fn()=>$transfer->import('students',[['student_identifier'=>$row->gepStudent->student_identifier,'full_name_en'=>'REPLACE']],$user)))->toThrow(HttpException::class);
    expect(GepStudent::first()->profile['full_name_en'])->toBe('EXISTING STUDENT');
});

it('keeps a generated GEP ID when the same student enrolls in the next academic year',function(){
    $this->controller->save(($this->request)($this->payload));$member=GepStudent::first();
    DB::table('tb_gep_academic_year')->insert(['id'=>2,'academic_year'=>'2027','status'=>true]);
    DB::table('tb_gep_term_assignment')->insert(['id'=>3,'academic_year_id'=>2,'term_id'=>1,'sort_order'=>1,'lifecycle_status'=>'started']);
    $this->controller->save(($this->request)([...$this->payload,'student_id'=>null,'gep_student_id'=>$member->id,'term_assignment_id'=>3]));
    expect($member->fresh()->student_identifier)->toBe('26190001')->and(GepStudent::count())->toBe(1)->and(GepEnrollment::count())->toBe(2);
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
