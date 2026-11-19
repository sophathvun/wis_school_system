<?php

use App\Http\Controllers\ProgramWorkspaceController;
use App\Http\Middleware\ResolveProgramWorkspace;
use App\Models\User;
use App\Support\ProgramWorkspace;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function(){
    $this->withoutMiddleware();
    Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->boolean('status')->default(true);$t->timestamp('last_seen_at')->nullable();$t->integer('department_id')->nullable();$t->integer('active_campus_id')->nullable();$t->boolean('is_global')->default(false);$t->timestamps();});
    Schema::create('access_permissions',function(Blueprint $t){$t->id();$t->string('code')->unique();$t->string('module');$t->string('action');$t->string('name');$t->timestamps();});
    Schema::create('access_roles',function(Blueprint $t){$t->id();$t->string('code');$t->boolean('status')->default(true);$t->boolean('is_global')->default(false);});
    Schema::create('access_role_permissions',function(Blueprint $t){$t->integer('role_id');$t->integer('permission_id');$t->timestamps();$t->unique(['role_id','permission_id']);});
    Schema::create('access_departments',function(Blueprint $t){$t->id();});
    Schema::create('access_department_permissions',function(Blueprint $t){$t->integer('department_id');$t->integer('permission_id');$t->timestamps();$t->unique(['department_id','permission_id']);});
    Schema::create('access_user_roles',function(Blueprint $t){$t->integer('user_id');$t->integer('role_id');$t->integer('campus_id')->nullable();});
    Schema::create('access_user_permission_overrides',function(Blueprint $t){$t->integer('user_id');$t->integer('permission_id');$t->boolean('allowed');$t->timestamps();$t->unique(['user_id','permission_id']);});
    Schema::create('access_user_campuses',function(Blueprint $t){$t->integer('user_id');$t->integer('campus_id');});
    Schema::create('tb_school_info',function(Blueprint $t){$t->id();$t->boolean('status')->default(true);$t->softDeletes();});
    DB::table('users')->insert(['id'=>1,'name'=>'Existing staff','last_seen_at'=>now()]);
    DB::table('access_roles')->insert([['id'=>1,'code'=>'registrar'],['id'=>2,'code'=>'central-office-admin']]);
    DB::table('access_user_roles')->insert(['user_id'=>1,'role_id'=>1]);
    $this->migration=require database_path('migrations/2026_11_19_000002_add_program_workspace_access.php');
    $this->migration->up();
    \App\Support\GepSettingsPermissions::registerMissing();
    $this->permissions=DB::table('access_permissions')->pluck('id','code');
    $this->give=function($programs){
        DB::table('access_role_permissions')->where('role_id',1)->delete();
        foreach(['program-workspaces.view',...array_map(fn($p)=>'program-workspaces.'.$p.'.view',$programs)] as $code)
            DB::table('access_role_permissions')->insert(['role_id'=>1,'permission_id'=>$this->permissions[$code]]);
        request()->attributes->remove(ProgramWorkspace::class.'.1');
        return User::findOrFail(1);
    };
    $this->workspaceRequest=function($path,$user,$program=null,$method='GET'){
        $request=Request::create($path,$method);
        $request->setLaravelSession(app('session')->driver());
        if($program)$request->session()->put('selected_program',$program);else $request->session()->forget('selected_program');
        app()->instance('request',$request);$request->setUserResolver(fn()=>$user);return $request;
    };
});

it('preserves existing WIS access and explicitly grants the central office both programs',function(){
    expect(ProgramWorkspace::available(User::find(1)))->toBe(['wis'=>'WIS']);
    $central=DB::table('access_role_permissions')->where('role_id',2)->pluck('permission_id');
    expect($central->all())->toContain($this->permissions['program-workspaces.gep.view']);
});

it('does not treat all-campus access as permission to access both programs',function(){
    $user=($this->give)(['gep']);$user->is_global=true;
    expect(ProgramWorkspace::available($user))->toBe(['gep'=>'GEP'])
        ->and(ProgramWorkspace::selected($user))->toBe('gep');
});

it('blocks WIS student pages and exports in GEP even through direct URLs',function($path){
    $user=($this->give)(['gep']);$request=($this->workspaceRequest)($path,$user,'gep');
    expect($request->user())->toBe($user)->and(ProgramWorkspace::selected($user))->toBe('gep');
    expect(fn()=>(new ResolveProgramWorkspace)->handle($request,fn()=>response('leaked')))->toThrow(HttpException::class);
})->with(['/student-enrollments/fetch','/reports','/families/options','/student-data-transfer/export/students']);

it('routes the default dashboard to GEP and leaves shared profile access available',function(){
    $user=($this->give)(['gep']);
    $response=(new ResolveProgramWorkspace)->handle(($this->workspaceRequest)('/dashboard',$user),fn()=>response('WIS'));
    expect($response->getTargetUrl())->toBe(route('gep.dashboard'));
    $response=(new ResolveProgramWorkspace)->handle(($this->workspaceRequest)('/profile',$user),fn()=>response('profile'));
    expect($response->getContent())->toBe('profile');
});

it('persists an authorized switch and redirects to the selected dashboard',function(){
    $user=($this->give)(['wis','gep']);$this->actingAs($user);
    config(['session.driver'=>'array']);
    $this->withMiddleware()->post(route('program-workspace.switch'),['program'=>'gep'])->assertRedirect(route('gep.dashboard'))->assertSessionHas('selected_program','gep');
    expect(User::find(1)->preferred_program)->toBe('gep');
});

it('rejects an unauthorized switch without changing the preference',function(){
    $user=($this->give)(['wis']);$this->actingAs($user);
    $this->post(route('program-workspace.switch'),['program'=>'gep'])->assertForbidden();
    expect(User::find(1)->preferred_program)->toBeNull();
});

it('ignores a stale saved GEP selection after access is revoked',function(){
    $user=($this->give)(['wis']);$user->preferred_program='gep';
    ($this->workspaceRequest)('/dashboard',$user,'gep');
    expect(ProgramWorkspace::selected($user))->toBe('wis');
});

it('blocks GEP access for WIS-only staff and blocks stale WIS submissions after switching',function(){
    $user=($this->give)(['wis']);
    expect(fn()=>(new ResolveProgramWorkspace)->handle(($this->workspaceRequest)('/gep',$user),fn()=>response('GEP')))->toThrow(HttpException::class);
    $user=($this->give)(['wis','gep']);
    expect(fn()=>(new ResolveProgramWorkspace)->handle(($this->workspaceRequest)('/student-enrollments/save',$user,'gep','POST'),fn()=>response('saved')))->toThrow(HttpException::class);
});

it('enforces GEP isolation through the complete web middleware pipeline',function(){
    $user=($this->give)(['gep']);$user->forceFill(['preferred_program'=>'gep'])->save();
    $this->actingAs($user);config(['session.driver'=>'array']);$this->withMiddleware();
    $this->getJson('/student-enrollments/fetch')->assertForbidden();
    $this->get('/dashboard')->assertRedirect(route('gep.dashboard'));
});

it('requires explicit workspace access even when a user has no selected program',function(){
    $user=($this->give)([]);
    expect(fn()=>(new ResolveProgramWorkspace)->handle(($this->workspaceRequest)('/dashboard',$user),fn()=>response('WIS')))->toThrow(HttpException::class);
});

it('opens every authorized GEP setting section',function($section){
    $user=($this->give)(['gep']);
    foreach(['gep-settings.view','gep-settings.'.$section.'.view'] as $code)DB::table('access_role_permissions')->insert(['role_id'=>1,'permission_id'=>DB::table('access_permissions')->where('code',$code)->value('id')]);
    $request=($this->workspaceRequest)('/gep/settings/'.$section,$user,'gep');
    $view=(new \App\Http\Controllers\GepSettingsController)->index($request,$section);
    expect($view->name())->toBe(match($section){'academic-years'=>'gep.academic-years','terms'=>'gep.terms','levels'=>'gep.levels','classes'=>'gep.classes','groups'=>'gep.groups','class-types'=>'gep.class-types',default=>'gep.settings'});
    if(!in_array($section,['academic-years','terms','levels','classes','groups','class-types']))expect($view->getData()['current']['label'])->toBe(\App\Support\GepSettingsPermissions::SECTIONS[$section]['label']);
})->with(array_keys(\App\Support\GepSettingsPermissions::SECTIONS));

it('requires the settings parent grant and GEP program access as well as the submenu',function(){
    $user=($this->give)(['gep']);
    DB::table('access_role_permissions')->insert(['role_id'=>1,'permission_id'=>DB::table('access_permissions')->where('code','gep-settings.terms.view')->value('id')]);
    expect(\App\Support\GepSettingsPermissions::allows($user,'terms'))->toBeFalse();
    $user=($this->give)(['wis']);
    foreach(['gep-settings.view','gep-settings.terms.view'] as $code)DB::table('access_role_permissions')->insert(['role_id'=>1,'permission_id'=>DB::table('access_permissions')->where('code',$code)->value('id')]);
    expect(\App\Support\GepSettingsPermissions::allows($user,'terms'))->toBeFalse();
});

it('redirects to the first permitted GEP setting and blocks ungranted sections',function(){
    $user=($this->give)(['gep']);
    foreach(['gep-settings.view','gep-settings.terms.view'] as $code)DB::table('access_role_permissions')->insert(['role_id'=>1,'permission_id'=>DB::table('access_permissions')->where('code',$code)->value('id')]);
    $request=($this->workspaceRequest)('/gep/settings',$user,'gep');
    $controller=new \App\Http\Controllers\GepSettingsController;
    expect($controller->index($request)->getTargetUrl())->toBe(route('gep.settings.section',['section'=>'terms']));
    expect(fn()=>$controller->index($request,'classes'))->toThrow(HttpException::class);
});

it('registers the GEP settings menu and five submenus without granting them to campus roles automatically',function(){
    (require database_path('migrations/2026_11_19_000003_add_gep_settings_menu_permissions.php'))->up();
    $ids=DB::table('access_permissions')->where('module','like','gep-settings%')->pluck('id');
    expect($ids)->toHaveCount(31)
        ->and(DB::table('access_role_permissions')->where('role_id',1)->whereIn('permission_id',$ids)->count())->toBe(0)
        ->and(DB::table('access_role_permissions')->where('role_id',2)->whereIn('permission_id',$ids)->count())->toBe(31);
});

it('renders the actual GEP submenu breadcrumb with its parent link and icon',function($section){
    $user=($this->give)(['gep']);$this->actingAs($user);
    $request=($this->workspaceRequest)('/gep/settings/'.$section,$user,'gep');
    $route=app('router')->getRoutes()->match($request);$request->setRouteResolver(fn()=>$route);
    $html=view('layouts.partials.navbar')->render();
    expect($html)->toContain('GEP Settings',\App\Support\GepSettingsPermissions::SECTIONS[$section]['label'],route('gep.settings.index'),route('gep.settings.section',['section'=>$section]));
})->with(array_keys(\App\Support\GepSettingsPermissions::SECTIONS));

it('paginates levels and prevents deletion when referenced by other records',function(){
    gepLevelTestSetup($this);
    for($i=1;$i<=12;$i++)DB::table('tb_gep_level')->insert(['level_name'=>'Level '.$i,'short_name'=>'L'.$i,'status'=>true]);
    $this->getJson('/gep/settings/levels/data?perPage=10&page=2')->assertOk()->assertJsonPath('total',12)->assertJsonCount(2,'data');
    Schema::create('test_gep_level_links',function(Blueprint $t){$t->id();$t->foreignId('level_id')->constrained('tb_gep_level')->restrictOnDelete();});
    $id=DB::table('tb_gep_level')->value('id');
    DB::table('test_gep_level_links')->insert(['level_id'=>$id]);
    $this->deleteJson('/gep/settings/levels/'.$id)->assertUnprocessable()->assertJsonValidationErrors('level_name');
    expect(DB::table('tb_gep_level')->where('id',$id)->exists())->toBeTrue();
});

it('orders and paginates GEP classes and restricts deletion of linked classes',function(){
    gepClassTestSetup($this);$base='/gep/settings/classes';
    for($i=1;$i<=12;$i++)DB::table('tb_gep_class')->insert(['class_name'=>'Class '.$i,'short_name'=>'C'.$i,'sort_order'=>$i,'status'=>true]);
    $this->getJson($base.'/data?perPage=10&page=2')->assertOk()->assertJsonPath('total',12)->assertJsonCount(2,'data')->assertJsonPath('data.0.sort_order',11);
    $id=DB::table('tb_gep_class')->where('short_name','C12')->value('id');
    $this->putJson($base.'/'.$id,['class_name'=>'Class 12','short_name'=>'C12','sort_order'=>0])->assertOk();
    $this->getJson($base.'/data')->assertOk()->assertJsonPath('data.0.id',$id);
    $this->patchJson($base.'/'.$id.'/status',['status'=>false,'sort_order'=>100])->assertOk()->assertJsonPath('data.sort_order',0);
    foreach([-1,1.5,null,2147483648] as $invalid)$this->putJson($base.'/'.$id,['class_name'=>'Class 12','short_name'=>'C12','sort_order'=>$invalid])->assertUnprocessable()->assertJsonValidationErrors('sort_order');
    Schema::create('test_gep_class_links',function(Blueprint $t){$t->id();$t->foreignId('class_id')->constrained('tb_gep_class')->restrictOnDelete();});
    DB::table('test_gep_class_links')->insert(['class_id'=>$id]);
    $this->deleteJson($base.'/'.$id)->assertUnprocessable()->assertJsonValidationErrors('class_name');
    expect(DB::table('tb_gep_class')->where('id',$id)->exists())->toBeTrue();
});

function gepGroupTestSetup($test,array $actions=['create','edit','delete','status']):void
{
    (require database_path('migrations/2026_11_19_000011_create_gep_groups.php'))->up();
    $test->withMiddleware();config(['session.driver'=>'array']);
    $user=($test->give)(['gep']);
    foreach(['gep-settings.view','gep-settings.groups.view',...array_map(fn($a)=>'gep-settings.groups.'.$a,$actions)] as $code)
        DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>1,'permission_id'=>DB::table('access_permissions')->where('code',$code)->value('id')]);
    $test->actingAs($user)->withSession(['selected_program'=>'gep']);
}

it('provides the three GEP session groups in session order and supports searching and pagination',function(){
    gepGroupTestSetup($this);$base='/gep/settings/groups';
    $this->getJson($base.'/data')->assertOk()->assertJsonPath('total',3)->assertJsonPath('data.0.session_name','Morning')->assertJsonPath('data.0.group_code','M')->assertJsonPath('data.1.group_code','A')->assertJsonPath('data.2.group_code','E');
    $this->getJson($base.'/data?search=Evening')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.group_code','E');
    $this->getJson($base.'/data?perPage=10&page=1')->assertOk()->assertJsonPath('per_page',10);
});

it('validates GEP sessions and recreates groups with the correct automatic code',function(){
    gepGroupTestSetup($this);$base='/gep/settings/groups';
    $id=DB::table('tb_gep_group')->where('group_code','M')->value('id');
    $this->postJson($base,['session_name'=>'Morning'])->assertUnprocessable()->assertJsonValidationErrors('session_name');
    $this->postJson($base,['session_name'=>'Night'])->assertUnprocessable()->assertJsonValidationErrors('session_name');
    $this->putJson($base.'/'.$id,['session_name'=>'Morning','sort_order'=>5,'group_code'=>'X','status'=>false])->assertOk()->assertJsonPath('data.group_code','M')->assertJsonPath('data.sort_order',5)->assertJsonPath('data.status',true);
    $this->putJson($base.'/'.$id,['session_name'=>'Morning','sort_order'=>-1])->assertUnprocessable()->assertJsonValidationErrors('sort_order');
    $this->patchJson($base.'/'.$id.'/status',['status'=>false,'sort_order'=>100])->assertOk()->assertJsonPath('data.status',false)->assertJsonPath('data.sort_order',5);
    $this->deleteJson($base.'/'.$id)->assertOk();
    $this->postJson($base,['session_name'=>'Morning','group_code'=>'X'])->assertCreated()->assertJsonPath('data.group_code','M')->assertJsonPath('data.status',true);
});

it('protects GEP group actions independently from view permission and blocks linked deletion',function(){
    gepGroupTestSetup($this,[]);$base='/gep/settings/groups';
    $id=DB::table('tb_gep_group')->value('id');
    $this->getJson($base.'/data')->assertOk();
    $this->postJson($base,['session_name'=>'Morning'])->assertForbidden();
    $this->putJson($base.'/'.$id,['session_name'=>'Morning'])->assertForbidden();
    $this->patchJson($base.'/'.$id.'/status',['status'=>false])->assertForbidden();
    $this->deleteJson($base.'/'.$id)->assertForbidden();
    DB::table('access_role_permissions')->insert(['role_id'=>1,'permission_id'=>DB::table('access_permissions')->where('code','gep-settings.groups.delete')->value('id')]);
    Schema::create('test_gep_group_links',function(Blueprint $t){$t->id();$t->foreignId('group_id')->constrained('tb_gep_group')->restrictOnDelete();});
    DB::table('test_gep_group_links')->insert(['group_id'=>$id]);
    $this->deleteJson($base.'/'.$id)->assertUnprocessable()->assertJsonValidationErrors('session_name');
    expect(DB::table('tb_gep_group')->where('id',$id)->exists())->toBeTrue();
});

function gepClassTypeTestSetup($test,array $actions=['create','edit','delete','status']):void
{
    (require database_path('migrations/2026_11_19_000012_create_gep_class_types.php'))->up();
    $test->withMiddleware();config(['session.driver'=>'array']);
    $user=($test->give)(['gep']);
    foreach(['gep-settings.view','gep-settings.class-types.view',...array_map(fn($a)=>'gep-settings.class-types.'.$a,$actions)] as $code)
        DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>1,'permission_id'=>DB::table('access_permissions')->where('code',$code)->value('id')]);
    $test->actingAs($user)->withSession(['selected_program'=>'gep']);
}

it('seeds Full-Time and Part-Time and supports class type CRUD search order and status',function(){
    gepClassTypeTestSetup($this);$base='/gep/settings/class-types';
    $this->getJson($base.'/data')->assertOk()->assertJsonPath('total',2)->assertJsonPath('data.0.type_name','Full-Time')->assertJsonPath('data.1.type_name','Part-Time');
    $id=$this->postJson($base,['type_name'=>' Weekend ','sort_order'=>0,'status'=>false])->assertCreated()->assertJsonPath('data.type_name','Weekend')->assertJsonPath('data.status',true)->json('data.id');
    $this->postJson($base,['type_name'=>'Full-Time'])->assertUnprocessable()->assertJsonValidationErrors('type_name');
    $this->postJson($base,['type_name'=>' '])->assertUnprocessable();
    $this->putJson($base.'/'.$id,['type_name'=>'Weekend Course','sort_order'=>1])->assertOk();
    $this->getJson($base.'/data?search=Weekend&perPage=10')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.type_name','Weekend Course')->assertJsonPath('per_page',10);
    $this->patchJson($base.'/'.$id.'/status',['status'=>false,'sort_order'=>999])->assertOk()->assertJsonPath('data.status',false)->assertJsonPath('data.sort_order',1);
    $this->putJson($base.'/'.$id,['type_name'=>'Weekend Course','status'=>true])->assertOk()->assertJsonPath('data.status',false);
    $this->putJson($base.'/'.$id,['type_name'=>'Weekend Course','sort_order'=>-1])->assertUnprocessable()->assertJsonValidationErrors('sort_order');
    $this->deleteJson($base.'/'.$id)->assertOk();
    expect(DB::table('tb_gep_class_type')->count())->toBe(2);
});

it('enforces class type action permissions and blocks deletion of linked types',function(){
    gepClassTypeTestSetup($this,[]);$base='/gep/settings/class-types';$id=DB::table('tb_gep_class_type')->value('id');
    $this->getJson($base.'/data')->assertOk();
    $this->postJson($base,['type_name'=>'Other'])->assertForbidden();
    $this->putJson($base.'/'.$id,['type_name'=>'Changed'])->assertForbidden();
    $this->patchJson($base.'/'.$id.'/status',['status'=>false])->assertForbidden();
    $this->deleteJson($base.'/'.$id)->assertForbidden();
    DB::table('access_role_permissions')->insert(['role_id'=>1,'permission_id'=>DB::table('access_permissions')->where('code','gep-settings.class-types.delete')->value('id')]);
    Schema::create('test_gep_class_type_links',function(Blueprint $t){$t->id();$t->foreignId('class_type_id')->constrained('tb_gep_class_type')->restrictOnDelete();});
    DB::table('test_gep_class_type_links')->insert(['class_type_id'=>$id]);
    $this->deleteJson($base.'/'.$id)->assertUnprocessable()->assertJsonValidationErrors('type_name');
    expect(DB::table('tb_gep_class_type')->where('id',$id)->exists())->toBeTrue();
});

function gepYearTestSetup($test,array $actions=['create','edit','delete','start']):void
{
    $test->withMiddleware();
    config(['session.driver'=>'array']);
    (require database_path('migrations/2026_11_19_000004_create_gep_academic_years.php'))->up();
    $user=($test->give)(['gep']);
    foreach(['gep-settings.view','gep-settings.academic-years.view',...array_map(fn($action)=>'gep-settings.academic-years.'.$action,$actions)] as $code)
        DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>1,'permission_id'=>DB::table('access_permissions')->where('code',$code)->value('id')]);
    $test->actingAs($user);
    $test->withSession(['selected_program'=>'gep']);
}

function gepTermTestSetup($test,array $actions=['create','edit','delete','status']):void
{
    (require database_path('migrations/2026_11_19_000005_create_gep_terms.php'))->up();
    $test->withMiddleware();config(['session.driver'=>'array']);
    $user=($test->give)(['gep']);
    foreach(['gep-settings.view','gep-settings.terms.view',...array_map(fn($a)=>'gep-settings.terms.'.$a,$actions)] as $code)
        DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>1,'permission_id'=>DB::table('access_permissions')->where('code',$code)->value('id')]);
    $test->actingAs($user)->withSession(['selected_program'=>'gep']);
}

it('creates edits searches and deletes GEP terms with unique names and abbreviations',function(){
    gepTermTestSetup($this);$base='/gep/settings/terms';
    $id=$this->postJson($base,['term_name'=>'Term 1','short_name'=>'T1','status'=>false])->assertCreated()->assertJsonPath('data.status',true)->json('data.id');
    $this->postJson($base,['term_name'=>'Term 1','short_name'=>'T2'])->assertUnprocessable()->assertJsonValidationErrors('term_name');
    $this->postJson($base,['term_name'=>'Term 2','short_name'=>'T1'])->assertUnprocessable()->assertJsonValidationErrors('short_name');
    $this->postJson($base,['term_name'=>' ','short_name'=>''])->assertUnprocessable();
    $this->putJson($base.'/'.$id,['term_name'=>'Term One','short_name'=>'T1'])->assertOk();
    $this->getJson($base.'/data?search=T1')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.term_name','Term One');
    $this->deleteJson($base.'/'.$id)->assertOk();
    expect(DB::table('tb_gep_term')->count())->toBe(0);
});

it('turns GEP term status on and off without edits overriding status',function(){
    gepTermTestSetup($this);$base='/gep/settings/terms';
    $id=$this->postJson($base,['term_name'=>'Term 1','short_name'=>'T1'])->json('data.id');
    $this->patchJson($base.'/'.$id.'/status',['status'=>false])->assertOk()->assertJsonPath('data.status',false);
    $this->putJson($base.'/'.$id,['term_name'=>'Term 1','short_name'=>'T1','status'=>true])->assertOk()->assertJsonPath('data.status',false);
    $this->patchJson($base.'/'.$id.'/status',['status'=>true])->assertOk()->assertJsonPath('data.status',true);
    $this->patchJson($base.'/'.$id.'/status',['status'=>'invalid'])->assertUnprocessable();
});

it('protects every GEP term mutation separately from view access',function(){
    gepTermTestSetup($this,[]);$base='/gep/settings/terms';
    $id=DB::table('tb_gep_term')->insertGetId(['term_name'=>'Term 1','short_name'=>'T1','status'=>true]);
    $this->getJson($base.'/data')->assertOk();
    $this->postJson($base,['term_name'=>'Term 2','short_name'=>'T2'])->assertForbidden();
    $this->putJson($base.'/'.$id,['term_name'=>'New','short_name'=>'N'])->assertForbidden();
    $this->patchJson($base.'/'.$id.'/status',['status'=>false])->assertForbidden();
    $this->deleteJson($base.'/'.$id)->assertForbidden();
});

function gepLevelTestSetup($test,array $actions=['create','edit','delete','status']):void
{
    (require database_path('migrations/2026_11_19_000006_create_gep_levels.php'))->up();
    (require database_path('migrations/2026_11_19_000007_add_type_and_program_to_gep_levels.php'))->up();
    (require database_path('migrations/2026_11_19_000008_add_sort_order_to_gep_levels.php'))->up();
    $test->withMiddleware();config(['session.driver'=>'array']);
    $user=($test->give)(['gep']);
    foreach(['gep-settings.view','gep-settings.levels.view',...array_map(fn($a)=>'gep-settings.levels.'.$a,$actions)] as $code)
        DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>1,'permission_id'=>DB::table('access_permissions')->where('code',$code)->value('id')]);
    $test->actingAs($user)->withSession(['selected_program'=>'gep']);
}

it('creates edits searches and deletes GEP levels with unique names and abbreviations',function(){
    gepLevelTestSetup($this);$base='/gep/settings/levels';
    $id=$this->postJson($base,['level_name'=>'Level 1','short_name'=>'L1','status'=>false])->assertCreated()->assertJsonPath('data.status',true)->json('data.id');
    $this->postJson($base,['level_name'=>'Level 1','short_name'=>'L2'])->assertUnprocessable()->assertJsonValidationErrors('level_name');
    $this->postJson($base,['level_name'=>'Level 2','short_name'=>'L1'])->assertUnprocessable()->assertJsonValidationErrors('short_name');
    $this->postJson($base,['level_name'=>' ','short_name'=>''])->assertUnprocessable();
    $this->putJson($base.'/'.$id,['level_name'=>'Level One','short_name'=>'L1'])->assertOk();
    $this->getJson($base.'/data?search=L1')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.level_name','Level One');
    $this->deleteJson($base.'/'.$id)->assertOk();
    expect(DB::table('tb_gep_level')->count())->toBe(0);
});

it('turns GEP level status on and off without edits overriding status',function(){
    gepLevelTestSetup($this);$base='/gep/settings/levels';
    $id=$this->postJson($base,['level_name'=>'Level 1','short_name'=>'L1'])->json('data.id');
    $this->patchJson($base.'/'.$id.'/status',['status'=>false])->assertOk()->assertJsonPath('data.status',false);
    $this->putJson($base.'/'.$id,['level_name'=>'Level 1','short_name'=>'L1','status'=>true])->assertOk()->assertJsonPath('data.status',false);
    $this->patchJson($base.'/'.$id.'/status',['status'=>true])->assertOk()->assertJsonPath('data.status',true);
    $this->patchJson($base.'/'.$id.'/status',['status'=>'invalid'])->assertUnprocessable();
});

it('protects every GEP level mutation separately from view access',function(){
    gepLevelTestSetup($this,[]);$base='/gep/settings/levels';
    $id=DB::table('tb_gep_level')->insertGetId(['level_name'=>'Level 1','short_name'=>'L1','status'=>true]);
    $this->getJson($base.'/data')->assertOk();
    $this->postJson($base,['level_name'=>'Level 2','short_name'=>'L2'])->assertForbidden();
    $this->putJson($base.'/'.$id,['level_name'=>'New','short_name'=>'N'])->assertForbidden();
    $this->patchJson($base.'/'.$id.'/status',['status'=>false])->assertForbidden();
    $this->deleteJson($base.'/'.$id)->assertForbidden();
});

it('creates edits and paginates GEP academic years with duplicate and format validation',function(){
    gepYearTestSetup($this);
    $base='/gep/settings/academic-years';
    $id=$this->postJson($base,['academic_year'=>'2026-2027','lifecycle_status'=>'pending'])->assertCreated()->json('data.id');
    $this->postJson($base,['academic_year'=>'2026-2027','lifecycle_status'=>'pending'])->assertUnprocessable()->assertJsonValidationErrors('academic_year');
    $this->postJson($base,['academic_year'=>'2026-2029','lifecycle_status'=>'pending'])->assertUnprocessable();
    $this->putJson($base.'/'.$id,['academic_year'=>'2027-2028','lifecycle_status'=>'finished'])->assertOk();
    $this->getJson($base.'/data?search=2027&perPage=10')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.academic_year','2027-2028');
    $this->deleteJson($base.'/'.$id)->assertOk();
    expect(DB::table('tb_gep_academic_year')->count())->toBe(0);
});

it('starts only one GEP year without touching WIS academic years and blocks deleting the started year',function(){
    gepYearTestSetup($this);
    Schema::create('tb_academic_year',fn(Blueprint $t)=>[$t->id(),$t->string('lifecycle_status')]);
    DB::table('tb_academic_year')->insert(['id'=>1,'lifecycle_status'=>'started']);
    $base='/gep/settings/academic-years';
    $first=$this->postJson($base,['academic_year'=>'2025-2026','lifecycle_status'=>'started'])->assertCreated()->json('data.id');
    $second=$this->postJson($base,['academic_year'=>'2026-2027','lifecycle_status'=>'pending'])->assertCreated()->json('data.id');
    $this->postJson($base.'/'.$second.'/start')->assertOk();
    $this->assertDatabaseHas('tb_gep_academic_year',['id'=>$first,'lifecycle_status'=>'finished','started_slot'=>null]);
    $this->assertDatabaseHas('tb_gep_academic_year',['id'=>$second,'lifecycle_status'=>'started','started_slot'=>1]);
    $this->deleteJson($base.'/'.$second)->assertUnprocessable();
    expect(DB::table('tb_academic_year')->value('lifecycle_status'))->toBe('started');
});

it('enforces GEP year action permissions independently of view access',function(){
    gepYearTestSetup($this,[]);
    $base='/gep/settings/academic-years';
    $id=DB::table('tb_gep_academic_year')->insertGetId(['academic_year'=>'2026-2027','lifecycle_status'=>'pending']);
    $this->getJson($base.'/data')->assertOk();
    $this->postJson($base,['academic_year'=>'2027-2028','lifecycle_status'=>'pending'])->assertForbidden();
    $this->putJson($base.'/'.$id,['academic_year'=>'2026-2027','lifecycle_status'=>'finished'])->assertForbidden();
    $this->postJson($base.'/'.$id.'/start')->assertForbidden();
    $this->deleteJson($base.'/'.$id)->assertForbidden();
});

it('saves updates and searches GEP level type and program',function(){
    gepLevelTestSetup($this);$base='/gep/settings/levels';
    $id=$this->postJson($base,['type'=>'Children','level_name'=>'Level 1','short_name'=>'L1','program'=>'General English'])->assertCreated()->assertJsonPath('data.type','Children')->assertJsonPath('data.program','General English')->json('data.id');
    $this->getJson($base.'/data?search=Children')->assertOk()->assertJsonPath('total',1);
    $this->getJson($base.'/data?search=General')->assertOk()->assertJsonPath('total',1);
    $this->putJson($base.'/'.$id,['type'=>'Adults','level_name'=>'Level 1','short_name'=>'L1','program'=>'Evening'])->assertOk()->assertJsonPath('data.type','Adults')->assertJsonPath('data.program','Evening');
    $this->putJson($base.'/'.$id,['type'=>null,'level_name'=>'Level 1','short_name'=>'L1','program'=>null])->assertOk()->assertJsonPath('data.type',null)->assertJsonPath('data.program',null);
    $this->postJson($base,['type'=>str_repeat('a',101),'level_name'=>'Level 2','short_name'=>'L2','program'=>str_repeat('b',101)])->assertUnprocessable()->assertJsonValidationErrors(['type','program']);
});

it('orders GEP levels numerically and validates order updates',function(){
    gepLevelTestSetup($this);$base='/gep/settings/levels';
    $id=$this->postJson($base,['level_name'=>'Level A','short_name'=>'A','sort_order'=>10])->assertCreated()->assertJsonPath('data.sort_order',10)->json('data.id');
    $this->postJson($base,['level_name'=>'Level Z','short_name'=>'Z','sort_order'=>2])->assertCreated();
    $this->getJson($base.'/data')->assertOk()->assertJsonPath('data.0.level_name','Level Z');
    $this->putJson($base.'/'.$id,['level_name'=>'Level A','short_name'=>'A','sort_order'=>1])->assertOk()->assertJsonPath('data.sort_order',1);
    $this->getJson($base.'/data')->assertOk()->assertJsonPath('data.0.level_name','Level A');
    foreach([-1,1.5,'abc',null,2147483648] as $invalid)$this->putJson($base.'/'.$id,['level_name'=>'Level A','short_name'=>'A','sort_order'=>$invalid])->assertUnprocessable()->assertJsonValidationErrors('sort_order');
    $this->putJson($base.'/'.$id,['level_name'=>'Level A','short_name'=>'A'])->assertOk()->assertJsonPath('data.sort_order',1);
});
function gepClassTestSetup($test,array $actions=['create','edit','delete','status']):void
{
    (require database_path('migrations/2026_11_19_000009_create_gep_classes.php'))->up();
    (require database_path('migrations/2026_11_19_000010_make_gep_class_abbreviation_optional.php'))->up();
    $test->withMiddleware();config(['session.driver'=>'array']);
    $user=($test->give)(['gep']);
    foreach(['gep-settings.view','gep-settings.classes.view',...array_map(fn($a)=>'gep-settings.classes.'.$a,$actions)] as $code)
        DB::table('access_role_permissions')->insertOrIgnore(['role_id'=>1,'permission_id'=>DB::table('access_permissions')->where('code',$code)->value('id')]);
    $test->actingAs($user)->withSession(['selected_program'=>'gep']);
}

it('creates edits searches and deletes GEP classes using only class names',function(){
    gepClassTestSetup($this);$base='/gep/settings/classes';
    $id=$this->postJson($base,['class_name'=>' A ','status'=>false])->assertCreated()->assertJsonPath('data.class_name','A')->assertJsonPath('data.status',true)->json('data.id');
    $this->postJson($base,['class_name'=>'A'])->assertUnprocessable()->assertJsonValidationErrors('class_name');
    $this->postJson($base,['class_name'=>'B'])->assertCreated();
    $this->postJson($base,['class_name'=>' '])->assertUnprocessable();
    $this->putJson($base.'/'.$id,['class_name'=>'C'])->assertOk();
    $this->getJson($base.'/data?search=C')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.class_name','C');
    $this->deleteJson($base.'/'.$id)->assertOk();
    expect(DB::table('tb_gep_class')->where('id',$id)->exists())->toBeFalse();
});

it('turns GEP class status on and off without edits overriding status',function(){
    gepClassTestSetup($this);$base='/gep/settings/classes';
    $id=$this->postJson($base,['class_name'=>'Term 1'])->json('data.id');
    $this->patchJson($base.'/'.$id.'/status',['status'=>false])->assertOk()->assertJsonPath('data.status',false);
    $this->putJson($base.'/'.$id,['class_name'=>'Term 1','status'=>true])->assertOk()->assertJsonPath('data.status',false);
    $this->patchJson($base.'/'.$id.'/status',['status'=>true])->assertOk()->assertJsonPath('data.status',true);
    $this->patchJson($base.'/'.$id.'/status',['status'=>'invalid'])->assertUnprocessable();
});

it('protects every GEP class mutation separately from view access',function(){
    gepClassTestSetup($this,[]);$base='/gep/settings/classes';
    $id=DB::table('tb_gep_class')->insertGetId(['class_name'=>'Term 1','status'=>true]);
    $this->getJson($base.'/data')->assertOk();
    $this->postJson($base,['class_name'=>'Term 2'])->assertForbidden();
    $this->putJson($base.'/'.$id,['class_name'=>'New'])->assertForbidden();
    $this->patchJson($base.'/'.$id.'/status',['status'=>false])->assertForbidden();
    $this->deleteJson($base.'/'.$id)->assertForbidden();
});

