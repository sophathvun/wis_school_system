<?php

// Synthetic records only. This fixture never reads the application's live database.
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','session.driver'=>'array']);\Illuminate\Support\Facades\DB::purge('sqlite');
require_once __DIR__.'/../Fixtures/ScholarshipSchema.php';createScholarshipSchema();
require_once __DIR__.'/../Fixtures/ScholarshipSeatReports.php';require_once __DIR__.'/../Fixtures/ScholarshipResultReports.php';
$mode=$argv[1]??'campus';parse_str($argv[2]??'',$filters);
if($mode==='permissions'){
    \App\Models\Permission::updateOrCreate(['code'=>'students.view'],['module'=>'students','action'=>'view','name'=>'View Students']);
    foreach(\App\Support\ScholarshipPermissions::catalog() as $action=>$name)\App\Models\Permission::updateOrCreate(['code'=>'western-scholarship.'.$action],['module'=>'western-scholarship','action'=>$action,'name'=>$name]);
    $permissions=\App\Models\Permission::all();
    $assigned=$permissions->whereIn('code',['students.view','western-scholarship.view','western-scholarship.tab-scoring','western-scholarship.scoring']);
    echo '<form data-permission-form>'.view('partials.permission-tree',[
        'permissionHierarchy'=>\App\Support\PermissionHierarchy::tree($permissions),'assignedPermissions'=>$assigned,'permissionPrefix'=>'fixture',
    ])->render().'</form>';exit;
}
$exam=scholarshipResultReportExam(10);$exam->update(['published_at'=>now()]);
foreach($exam->candidates()->get() as $candidate)$candidate->update(['snapshot'=>$candidate->snapshot+['eoy_average'=>'CONFIDENTIAL_EOY','private_note'=>'PRIVATE_SNAPSHOT']]);
$user=Mockery::mock(\App\Models\User::class)->makePartial();$user->id=2;$user->is_global=$mode==='central';
$user->setRelation('roles',collect());$user->setRelation('campuses',\App\Models\SchoolInfo::whereKey(1)->get());$user->shouldReceive('isSuperAdmin')->andReturnFalse();
$actions=$mode==='readonly'?['view','tab-scholarship-result']:array_keys(\App\Support\ScholarshipPermissions::catalog());
$user->shouldReceive('hasPermission')->andReturnUsing(fn($code)=>$code==='students.view'||in_array($code,array_map(fn($action)=>'western-scholarship.'.$action,$actions),true));
\Illuminate\Support\Facades\Auth::setUser($user);
$request=\Illuminate\Http\Request::create('http://localhost/students/western-scholarship','GET',['tab'=>'scholarship-result']+$filters);
$request->setUserResolver(fn()=>$user);$request->setLaravelSession(app('session')->driver());$app->instance('request',$request);
$data=app(\App\Http\Controllers\ScholarshipController::class)->index($request)->getData()+['errors'=>new \Illuminate\Support\ViewErrorBag()];
$template=file_get_contents(resource_path('views/western-scholarship/published-results.blade.php'));
preg_match("/@section\('content'\)(.*)@endsection/s",$template,$matches);
echo \Illuminate\Support\Facades\Blade::render(preg_replace('/^@vite.*$/m','',$matches[1]),$data);
