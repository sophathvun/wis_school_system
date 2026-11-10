<?php

use App\Models\User;
use App\Support\{HelpCenter,G9NationalExamPermissions};
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../Fixtures/G12NationalExam.php';

beforeEach(function(){
    $this->withoutVite();
    createG12NationalSchema();G9NationalExamPermissions::register();
    foreach(['students.search.view','students.enrollment.view','placement-tests.view','summer-school.view','families.view','students.promotion.view','students.transfer.view','students.graduation.view','student-reentry.view','student-id-card-qr.view','student-documents.view','student-skipping-grade.view','western-scholarship.view','student-data-transfer.view'] as $code)\App\Models\Permission::firstOrCreate(['code'=>$code],['module'=>explode('.',$code)[0],'action'=>substr($code,strpos($code,'.')+1),'name'=>$code]);
    foreach(\App\Support\AttendancePermissions::catalog() as $action=>$name)\App\Models\Permission::firstOrCreate(['code'=>'attendance.'.$action],['module'=>'attendance','action'=>$action,'name'=>$name]);
    foreach(['administrator.view','settings.manage','branding.view','database-backups.view','users.view'] as $code)\App\Models\Permission::firstOrCreate(['code'=>$code],['module'=>explode('.',$code)[0],'action'=>explode('.',$code)[1],'name'=>$code]);
    \Illuminate\Support\Facades\Schema::table('users',fn($table)=>$table->timestamp('last_seen_at')->nullable());
    app('view')->getFinder()->prependLocation(base_path('tests/Fixtures/help-views'));
});

it('requires authentication for help and individual articles',function(){
    $this->get('/help')->assertRedirect();
    $this->get('/help/g9-certificates')->assertRedirect();
});

it('serves reviewed articles and searchable Khmer content without changing records',function(){
    $this->actingAs(User::find(1));
    $before=DB::table('access_permissions')->count();
    $this->get('/help')->assertOk()->assertSee('How can we help?')->assertSee('Track certificates and student receipt');
    $this->get('/help?q=WebP&category=g9')->assertOk()->assertSee('Track certificates and student receipt')->assertDontSee('Assign the end-of-year average');
    $this->get('/help?q='.urlencode('ការិយាល័យសិក្សា'))->assertOk()->assertSee('Print and export G9 result reports');
    $this->get('/help?q=zzzz-no-match')->assertOk()->assertSee('No guides found');
    $this->get('/help/g9-settings')->assertOk()->assertSee('DoEYS Result Issue Date')->assertSee('August 12, 2026')->assertSee('Open page');
    $this->get('/help/not-a-guide')->assertNotFound();
    expect(DB::table('access_permissions')->count())->toBe($before);
});

it('limits the catalog search and direct links to the users existing module permissions',function(){
    $this->actingAs(User::find(2));
    $this->get('/help')->assertOk()->assertSee('Get started with the school system')->assertDontSee('G9 examination workflow');
    $this->get('/help/g9-certificates')->assertNotFound();
    foreach(['view','results'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g9-national-exams.'.$action)->value('id'),'allowed'=>true]);
    $this->get('/help?category=g9')->assertOk()->assertSee('Explore results, graphs, and statistics')->assertDontSee('Generate certificate numbers safely');
    $this->get('/help/g9-results')->assertOk();
    $this->get('/help/g9-certificate-numbers')->assertNotFound();
});

it('validates filters and renders all catalog documents with safe markdown',function(){
    $this->actingAs(User::find(1));
    $controller=app(\App\Http\Controllers\HelpCenterController::class);
    expect(fn()=>$controller->index(g12Request(User::find(1),['category'=>'invalid']),app(HelpCenter::class)))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn()=>$controller->index(g12Request(User::find(1),['q'=>str_repeat('x',201)]),app(HelpCenter::class)))->toThrow(\Illuminate\Validation\ValidationException::class);
    $help=app(HelpCenter::class);
    foreach($help->articles(User::find(1)) as $article){
        expect($article['body'])->not->toBeEmpty();
        $this->get('/help/'.$article['slug'])->assertOk()->assertSee($article['title']);
        if(getenv('HELP_CENTER_QA')){
            $folder=storage_path('app/testing/help-center');if(!is_dir($folder))mkdir($folder,0755,true);
            file_put_contents($folder.'/'.$article['slug'].'.html',$this->get('/help/'.$article['slug'])->getContent());
        }
    }
    expect($help->render('<script>alert(1)</script> [unsafe](javascript:alert(1))'))->not->toContain('<script','href="javascript:');
    if(getenv('HELP_CENTER_QA'))file_put_contents(storage_path('app/testing/help-center/index.html'),$this->get('/help')->getContent());
});

it('keeps G12 help separate from G9 permissions and links to the correct module',function(){
    $this->actingAs(User::find(1));
    $this->get('/help?category=g12')->assertOk()->assertSee('G12 national examination workflow')->assertDontSee('G9 examination workflow');
    $this->get('/help/g12-certificates')->assertOk()->assertSee(route('g12-national-exams.certificates'),false)->assertSee('Student-Signed Receipt');
    $this->get('/help?q=OCR&category=g12')->assertOk()->assertSee('Scan and review MoEYS result images');
    $this->actingAs(User::find(2));
    $this->get('/help/g12-certificates')->assertNotFound();
    foreach(['view','certificates'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','g12-national-exams.'.$action)->value('id'),'allowed'=>true]);
    $this->get('/help?category=g12')->assertOk()->assertSee('Track G12 certificates and collection')->assertDontSee('Scan and review MoEYS result images');
    $this->get('/help/g12-certificates')->assertOk();
    $this->get('/help/g9-certificates')->assertNotFound();
    $this->get('/help/g12-ocr')->assertNotFound();
});

it('filters Attendance guides by the appropriate workspace and report tab permissions',function(){
    $this->actingAs(User::find(1));
    $this->get('/help?category=attendance')->assertOk()->assertSee('Record admin daily attendance')->assertDontSee('G12 national examination workflow');
    $this->get('/help/attendance-daily')->assertOk()->assertSee(route('attendance.admin-daily'),false)->assertSee('P — Apply to Blank');
    $this->get('/help?q='.urlencode('ច្បាប់').'&category=attendance')->assertOk()->assertSee('Record and manage approved student leave');
    $this->actingAs(User::find(2));
    $this->get('/help/attendance-daily')->assertNotFound();
    foreach(['view','daily'] as $action)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code','attendance.'.$action)->value('id'),'allowed'=>true]);
    $this->get('/help?category=attendance')->assertOk()->assertSee('Record admin daily attendance')->assertDontSee('Configure school days and public holidays');
    $this->get('/help/attendance-daily')->assertOk();
    $this->get('/help/attendance-statistics')->assertNotFound();
    $this->get('/help/g12-records')->assertNotFound();
});

it('protects administrator guides and keeps super administrator tools restricted',function(){
    $this->actingAs(User::find(1));
    $this->get('/help?category=administrator')->assertOk()->assertSee('Create and maintain user accounts')->assertDontSee('Record admin daily attendance');
    $this->get('/help/admin-role-permissions')->assertOk()->assertSee(route('access-management.index'),false)->assertSee('Save Role Permissions');
    $this->get('/help?q=SQL&category=administrator')->assertOk()->assertSee('Create and download database backups');
    $this->actingAs(User::find(2));
    $this->get('/help/admin-role-permissions')->assertNotFound();
    foreach(['administrator.view','settings.manage','users.view'] as $code)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code',$code)->value('id'),'allowed'=>true]);
    $this->get('/help/admin-role-permissions')->assertOk();
    $this->get('/help/admin-backups')->assertOk();
    $this->get('/help/admin-users')->assertNotFound();
    $this->get('/help/admin-roles')->assertNotFound();
    $this->get('/help/admin-dashboards')->assertNotFound();
    $this->get('/help/admin-branding')->assertNotFound();
});

it('covers every Student submenu and restricts its guides to workspace permissions',function(){
    $this->actingAs(User::find(1));
    $articles=app(HelpCenter::class)->articles(User::find(1));
    expect(collect($articles)->where('category','students'))->toHaveCount(16);
    $this->get('/help?category=students')->assertOk()->assertSee('Find the right Student workspace')->assertSee('Import and export student CSV data')->assertDontSee('G12 national examination workflow');
    $this->get('/help/students-enrollment')->assertOk()->assertSee(route('studentEnrollment.index'),false);
    $this->get('/help?q=CSV&category=students')->assertOk()->assertSee('Import and export student CSV data');
    $this->actingAs(User::find(2));
    $this->get('/help/students-enrollment')->assertNotFound();
    foreach(['students.view','students.enrollment.view','summer-school.view','student-skipping-grade.view'] as $code)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>DB::table('access_permissions')->where('code',$code)->value('id'),'allowed'=>true]);
    $this->get('/help?category=students')->assertOk()->assertSee('Register and manage student enrollment')->assertSee('Register Summer School students')->assertDontSee('Run a student placement test');
    $this->get('/help/students-enrollment')->assertOk();
    $this->get('/help/students-summer')->assertOk();
    $this->get('/help/students-skipping')->assertOk();
    $this->get('/help/students-placement')->assertNotFound();
    $this->get('/help/students-data-transfer')->assertNotFound();
});
