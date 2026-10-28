<?php
use App\Models\{User,G12ExamRecord,G12ExamImport,G12ExamCenter,G12ExamFile,G12ExamCertificate};
use App\Services\{G12NationalExams,G12NationalExamFiles};
use App\Support\{G12NationalExamPermissions as Access,G12NationalExamWorkbook,PermissionHierarchy};
use App\Http\Controllers\G12NationalExamController;
use Illuminate\Support\Facades\{DB,Storage,Blade};
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
require_once __DIR__.'/../Fixtures/G12NationalExam.php';
beforeEach(fn()=>createG12NationalSchema());

it('stores unique student-year exam snapshots without changing school profiles and keeps folders stable',function(){
    $service=app(G12NationalExams::class);$user=User::find(1);$record=$service->save($user,g12ExamData());$folder=$record->document_folder;
    expect($record->student_code)->toBe('00123')->and($record->student_id)->toBe(1)->and($record->certificates()->count())->toBe(2)->and(DB::table('tb_student')->value('full_name_en'))->toBe('Existing school name');
    $service->save($user,g12ExamData(['student_name_en'=>'Corrected Name','version'=>1]),$record);
    expect($record->fresh()->document_folder)->toBe($folder)->and($record->fresh()->version)->toBe(2);
    expect(fn()=>$service->save($user,g12ExamData()))->toThrow(ValidationException::class,'already has');
    expect(fn()=>$service->save($user,g12ExamData(['version'=>1]),$record))->toThrow(ValidationException::class,'another window');
    $next=$service->save($user,g12ExamData(['academic_year'=>'2026-2027']));expect($next->id)->not->toBe($record->id);
});
it('returns the student birth date and parent details required by the exam record list',function(){
    $record=app(G12NationalExams::class)->save(User::find(1),g12ExamData([
        'dob'=>'2020-09-14','mother_name'=>'Mother Name','mother_occupation'=>'Teacher',
        'father_name'=>'Father Name','father_occupation'=>'Engineer',
        'g9_exam_center'=>'G9 Center','g9_exam_date'=>'2023-11-06','g9_exam_room'=>'12','g9_exam_seat'=>'34',
        'language_exam'=>'English','class_sellected'=>'វិទ្យាសាស្ត្រ','g12_exam_date'=>'2026-11-03',
    ]));
    $list=app(G12NationalExamController::class)->list(g12Request(User::find(1),['tab'=>'records']))->getData(true);
    expect($list['data'][0])->toMatchArray([
        'id'=>$record->id,'dob'=>'2020-09-14','mother_name'=>'Mother Name','mother_occupation'=>'Teacher',
        'father_name'=>'Father Name','father_occupation'=>'Engineer','campus'=>'BCH','grade'=>'12A',
        'g9_exam_center'=>'G9 Center','g9_exam_date'=>'2023-11-06','g9_exam_room'=>'12','g9_exam_seat'=>'34',
        'language_exam'=>'English','class_selected'=>'វិទ្យាសាស្ត្រ','g12_exam_date'=>'2026-11-03',
    ]);
});
it('renders the filtered A4 candidate print report with Branding School Logo 1',function(){
    Storage::fake('public');
    \Illuminate\Support\Facades\Schema::create('tb_branding_setting',function(\Illuminate\Database\Schema\Blueprint $table){
        $table->id();$table->string('report_logo_1_path')->nullable();$table->string('footer_text')->nullable();$table->timestamps();
    });
    Storage::disk('public')->put('branding/g12-report-logo.jpg',g12PhotoBytes());
    DB::table('tb_branding_setting')->insert(['report_logo_1_path'=>'branding/g12-report-logo.jpg','footer_text'=>'School','created_at'=>now(),'updated_at'=>now()]);
    app(G12NationalExams::class)->save(User::find(1),g12ExamData());
    $response=app(G12NationalExamController::class)->report(g12Request(User::find(1),['format'=>'print','academic_year'=>'2025-2026']));
    $html=$response->render();
    $pdf=app(G12NationalExamController::class)->report(g12Request(User::find(1),['format'=>'pdf','academic_year'=>'2025-2026']));
    expect($html)->toContain('List of Candidates','School Logo 1','data:image/jpeg;base64','@page{size:A4 landscape')
        ->and($pdf->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($pdf->headers->get('Content-Disposition'))->toContain('g12-list-of-candidates.pdf')
        ->and(substr($pdf->getContent(),0,4))->toBe('%PDF');
});
it('filters student exam records by exam language and selected class and returns searchable options',function(){
    $user=User::find(1);$service=app(G12NationalExams::class);$controller=app(G12NationalExamController::class);
    $service->save($user,g12ExamData(['language_exam'=>'English','class_sellected'=>'Science']));
    $service->save($user,g12ExamData(['student_id'=>'00999','student_name_en'=>'SECOND STUDENT','language_exam'=>'French','class_sellected'=>'Social Science']));
    $filtered=$controller->list(g12Request($user,['tab'=>'records','language_exam'=>'French','class_selected'=>'Social Science']))->getData(true);
    $meta=$controller->meta(g12Request($user,['tab'=>'records']))->getData(true);
    expect($filtered['total'])->toBe(1)->and($filtered['data'][0]['student_code'])->toBe('00999')
        ->and($meta['exam_languages'])->toBe(['English','French'])
        ->and($meta['selected_classes'])->toBe(['Science','Social Science']);
});
it('returns filtered student totals by gender exam group and campus',function(){
    $user=User::find(1);$service=app(G12NationalExams::class);
    $rows=[
        ['student_id'=>'1001','gender'=>'ប','class_sellected'=>'Science','language_exam'=>'Khmer','campus_id'=>1,'campus'=>'BCH'],
        ['student_id'=>'1002','gender'=>'ស','class_sellected'=>'Science','language_exam'=>'English','campus_id'=>1,'campus'=>'BCH'],
        ['student_id'=>'1003','gender'=>'ស្រី','class_sellected'=>'Social Science','language_exam'=>'ខ្មែរ','campus_id'=>2,'campus'=>'BKK'],
        ['student_id'=>'1004','gender'=>'ប្រុស','class_sellected'=>'វិទ្យាសាស្ត្រសង្គម','language_exam'=>'អង់គ្លេស','campus_id'=>2,'campus'=>'BKK'],
    ];
    foreach($rows as $index=>$row)$service->save($user,g12ExamData($row+['student_name_en'=>'STUDENT '.($index+1)]));
    $list=app(G12NationalExamController::class)->list(g12Request($user,['tab'=>'records','academic_year'=>'2025-2026']))->getData(true);
    expect($list['summary'])->toMatchArray([
        'total'=>4,'male'=>2,'female'=>2,
        'groups'=>['science_khmer'=>1,'science_english'=>1,'social_khmer'=>1,'social_english'=>1],
        'campuses'=>['BCH'=>2,'BKK'=>2],
    ]);
});
it('inherits permissions from roles, departments and user grants while enforcing campus boundaries',function($source){
    $user=g12ExamUser(['view','records','create'],$source);expect(Access::allows($user,'create',1))->toBeTrue()->and(Access::allows($user,'create',2))->toBeFalse();
    app(G12NationalExams::class)->save($user,g12ExamData());
    expect(fn()=>app(G12NationalExams::class)->save($user,g12ExamData(['student_id'=>'999','campus_id'=>2])))->toThrow(HttpException::class);
})->with(['role','department','user']);
it('groups every action under the independent G12 main menu and enforces submenu dependencies',function(){
    $catalog=App\Models\Permission::all();$tree=PermissionHierarchy::tree($catalog);
    expect($tree['g12-national-exams']['modules'])->toHaveCount(8)
        ->and($tree['students']['modules'])->not->toHaveKey('g12-national-exams');
    foreach(Access::submenus() as $key=>$section){
        $module=$tree['g12-national-exams']['modules']['g12-national-exams.'.$key];
        expect($module['permission']->code)->toBe('g12-national-exams.'.$key)
            ->and($module['actions']->pluck('action')->all())->toBe($section[1]);
    }
    $nationalIds=$catalog->where('module','g12-national-exams')->pluck('id')->all();
    expect(array_diff($nationalIds,PermissionHierarchy::visibleIds($catalog)->all()))->toBe([])
        ->and(PermissionHierarchy::normalizeIds($nationalIds,$catalog))->toEqualCanonicalizing($nationalIds);
    $selected=$catalog->whereIn('code',['students.view','g12-national-exams.view','g12-national-exams.collection'])->pluck('id')->all();
    expect(PermissionHierarchy::normalizeIds($selected,$catalog))->not->toContain($catalog->firstWhere('code','g12-national-exams.collection')->id);
    $withoutMain=$catalog->where('module','g12-national-exams')->where('action','!=','view')->pluck('id')->all();
    expect(PermissionHierarchy::normalizeIds($withoutMain,$catalog))->toBe([]);
});
it('resolves all eight submenu URLs and blocks direct access to ungranted sections',function(){
    $user=g12ExamUser(['view','records']);$controller=app(G12NationalExamController::class);
    foreach(Access::PATHS as $section=>$path){
        $request=g12Request($user,[], '/g12-national-exams/'.$path);
        $route=app('router')->getRoutes()->match($request);$request->setRouteResolver(fn()=>$route);
        expect($route->getName())->toBe('g12-national-exams.'.$section)->and($route->parameter('section'))->toBe($section)
            ->and(route('g12-national-exams.'.$section,[],false))->toBe('/g12-national-exams/'.$path);
        if($section==='records')expect($controller->index($request)->getData()['tab'])->toBe('records');
        else expect(fn()=>$controller->index($request))->toThrow(HttpException::class);
    }
});
it('places Assign Exam Center below Student Exam Records with its requested filters',function(){
    $user=g12ExamUser(['view','assign-centers']);$request=g12Request($user,[], '/g12-national-exams/assign-exam-center');
    $route=app('router')->getRoutes()->match($request);$request->setRouteResolver(fn()=>$route);
    $view=app(G12NationalExamController::class)->index($request);$template=file_get_contents(resource_path('views/g12-national-exams/index.blade.php'));
    expect(array_slice(array_keys(Access::submenus()),0,3))->toBe(['records','assign-centers','scoring'])
        ->and($view->getData()['tab'])->toBe('assign-centers')
        ->and($template)->toContain('Datas Filter','g12-assign-filter-fields','data-g12-filter-toggle')
        ->and($template)->toContain("'academic_year'=>'Academic Year','campus_id'=>'Campus','language_exam'=>'Exam Language','class_selected'=>'Select','grade'=>'Grade'",'name="search"','Exam Center List','Phone Number','Examinations','Added by','data-g12-records');
});
it('places Scoring below Assign Exam Center with searchable filter data',function(){
    app(G12NationalExams::class)->save(User::find(1),g12ExamData(['student_name_en'=>'SCORING STUDENT','language_exam'=>'English']));
    $user=g12ExamUser(['view','scoring']);$request=g12Request($user,[], '/g12-national-exams/scoring');
    $route=app('router')->getRoutes()->match($request);$request->setRouteResolver(fn()=>$route);
    $controller=app(G12NationalExamController::class);$view=$controller->index($request);
    $meta=$controller->meta(g12Request($user,['tab'=>'scoring']))->getData(true);
    $template=file_get_contents(resource_path('views/g12-national-exams/index.blade.php'));
    expect(array_slice(array_keys(Access::submenus()),0,3))->toBe(['records','assign-centers','scoring'])
        ->and($route->getName())->toBe('g12-national-exams.scoring')
        ->and($view->getData()['tab'])->toBe('scoring')
        ->and($template)->toContain('g12-scoring-filter-fields',"'academic_year'=>'Academic Year','campus_id'=>'Campus','language_exam'=>'Language Exam','center_id'=>'Exam Center','record_id'=>'Student Name'")
        ->and($meta['students'][0])->toMatchArray(['name_en'=>'SCORING STUDENT','student_code'=>'00123']);
});
it('lists students for exam center assignment and securely saves center room and seat',function(){
    $record=app(G12NationalExams::class)->save(User::find(1),g12ExamData(['phone_number'=>'012 345 678','gender'=>'ប']));
    $center=G12ExamCenter::create(['name'=>'Assignment Center','name_key'=>'assignment center','active'=>true]);
    $viewer=g12ExamUser(['view','assign-centers']);$controller=app(G12NationalExamController::class);
    $list=$controller->list(g12Request($viewer,['tab'=>'assign-centers','academic_year'=>'2025-2026']))->getData(true);
    expect($list['data'][0])->toMatchArray(['id'=>$record->id,'phone_number'=>'012 345 678','center_id'=>null,'room'=>'01','seat'=>'005','can_assign'=>false])
        ->and($list['summary'])->toMatchArray(['total'=>1,'male'=>1,'female'=>0,'centers'=>['assigned'=>0,'blank'=>1],'rooms'=>['assigned'=>1,'blank'=>0],'seats'=>['assigned'=>1,'blank'=>0]])
        ->and($list['data'][0]['added_by'])->toMatchArray(['name'=>'National Exam Admin','position'=>'—','campus'=>'BCH'])
        ->and($list['data'][0]['added_by']['date'])->not->toBeEmpty()
        ->and(route('g12-national-exams.assignment.update',$record,false))->toBe('/g12-national-exams/records/'.$record->id.'/exam-assignment');
    expect(fn()=>$controller->saveExamAssignment(g12Request($viewer,['center_id'=>$center->id,'room'=>'18','seat'=>'440','version'=>$record->version],'/g12-national-exams/records/'.$record->id.'/exam-assignment','POST'),$record))->toThrow(HttpException::class);
    $permission=App\Models\Permission::where('code','g12-national-exams.assign-centers-manage')->value('id');
    DB::table('access_user_permission_overrides')->insert(['user_id'=>$viewer->id,'permission_id'=>$permission,'allowed'=>true,'created_at'=>now(),'updated_at'=>now()]);
    $response=$controller->saveExamAssignment(g12Request($viewer->fresh(),['center_id'=>$center->id,'room'=>'18','seat'=>'440','version'=>$record->version],'/g12-national-exams/records/'.$record->id.'/exam-assignment','POST'),$record)->getData(true);
    $saved=$record->fresh();
    expect($response)->toMatchArray(['message'=>'Exam assignment saved.','version'=>2,'center'=>'Assignment Center','room'=>'18','seat'=>'440'])
        ->and($saved->center_id)->toBe($center->id)
        ->and($saved->data)->toMatchArray(['g12_exam_center'=>'Assignment Center','g12_exam_room'=>'18','g12_exam_seat'=>'440'])
        ->and(DB::table('tb_g12_exam_event')->where('record_id',$record->id)->where('action','exam-center-assigned')->exists())->toBeTrue();
});
it('orders exam center assignments by campus grade and Khmer student name',function(){
    DB::table('tb_school_info')->where('id',1)->update(['campus_name_en'=>'Zeta Campus']);
    DB::table('tb_school_info')->where('id',2)->update(['campus_name_en'=>'Alpha Campus']);
    $service=app(G12NationalExams::class);$user=User::find(1);
    $service->save($user,g12ExamData(['student_id'=>'1001','student_name_kh'=>'KHMER D','campus_id'=>1,'grade'=>'12A']));
    $service->save($user,g12ExamData(['student_id'=>'1002','student_name_kh'=>'KHMER C','campus_id'=>2,'grade'=>'12B']));
    $service->save($user,g12ExamData(['student_id'=>'1003','student_name_kh'=>'KHMER B','campus_id'=>2,'grade'=>'12A']));
    $service->save($user,g12ExamData(['student_id'=>'1004','student_name_kh'=>'KHMER A','campus_id'=>2,'grade'=>'12A']));
    $list=app(G12NationalExamController::class)->list(g12Request($user,['tab'=>'assign-centers','academic_year'=>'2025-2026']))->getData(true);
    expect(collect($list['data'])->pluck('name_kh')->all())->toBe(['KHMER A','KHMER B','KHMER C','KHMER D']);
});
it('shows only granted sidebar submenus and marks the current section active',function(){
    $user=g12ExamUser(['view','records']);$request=g12Request($user,[], '/g12-national-exams/student-records');
    $route=app('router')->getRoutes()->match($request);$request->setRouteResolver(fn()=>$route);auth()->setUser($user);
    $html=view('layouts.partials.g12-national-exams-menu',['moeysMenuIcon'=>'/icon.png'])->render();
    expect(substr_count($html,'class="dropdown-item'))->toBe(1)->and($html)->toContain('Student Exam Records','aria-current="page"')->not->toContain('Certificate Tracking','Excel Imports / History');
    DB::table('access_user_permission_overrides')->where('user_id',2)->where('permission_id',App\Models\Permission::where('code','g12-national-exams.view')->value('id'))->update(['allowed'=>false]);
    auth()->setUser($user->fresh());
    expect(trim(view('layouts.partials.g12-national-exams-menu',['moeysMenuIcon'=>'/icon.png'])->render()))->toBe('');
});
it('renames old tab labels without changing permission IDs or grants',function(){
    $user=g12ExamUser(['view','records','edit'],'role');
    DB::table('access_permissions')->where('code','g12-national-exams.records')->update(['name'=>'Tab: Student Exam Records']);
    DB::table('access_permissions')->where('code','g12-national-exams.view')->update(['name'=>'G12 National Exams: View submenu']);
    $grants=DB::table('access_role_permissions')->get()->toArray();
    (require database_path('migrations/2026_10_26_000002_move_g12_sections_to_submenus.php'))->up();
    expect(DB::table('access_permissions')->where('code','g12-national-exams.records')->value('name'))->toBe('Submenu: Student Exam Records')
        ->and(DB::table('access_permissions')->where('code','g12-national-exams.view')->value('name'))->toBe('G12 National Exams: Main menu')
        ->and(DB::table('access_role_permissions')->get()->toArray())->toEqual($grants)
        ->and(Access::allows($user->fresh(),'edit',1))->toBeTrue();
});
it('previews center mappings and imports Withdrew as collected without inventing receipt evidence',function(){
    $user=User::find(1);$service=app(G12NationalExams::class);$batch=g12Batch([g12ExamData(['_row'=>2,'received_soft_certificate'=>'Withdrew','received_soft_cert_date'=>'2025-10-21','received_soft_cert_by'=>'Student','image_soft_certreceived_name'=>'signed_receipt'])],[['excenter_id'=>'05','exam_center'=>'Canonical Center']]);
    $preview=$service->inspect($user,$batch);expect($preview['errors'])->toBe(1)->and($preview['missing_centers'])->toBe(['Test Center']);
    $maps=['centers'=>['Test Center'=>'@Canonical Center']];$preview=$service->inspect($user,$batch,$maps);
    $service->confirm($user,$batch,$maps,['2'=>null]);$record=G12ExamRecord::sole();$soft=$record->certificates()->where('type','soft')->sole();
    expect($record->center->name)->toBe('Canonical Center')->and($soft->status)->toBe('collected')->and($soft->collected_date)->toBe('2025-10-21')->and($soft->recipient)->toBe('Student')->and($record->files()->count())->toBe(0);
    expect(fn()=>$service->confirm($user,$batch,$maps,['2'=>null]))->toThrow(ValidationException::class,'already imported');
});
it('assigns center IDs automatically and keeps IDs stable when edited',function(){
    $user=g12ExamUser(['view','centers','centers-manage']);$controller=app(G12NationalExamController::class);
    $controller->saveCenter(g12Request($user,['name'=>'New Test Center','active'=>true,'source_id'=>'MANUAL-ID']));
    $first=G12ExamCenter::sole();$code=$first->source_id;
    expect($code)->toMatch('/^EC-\d{6,}$/')->not->toBe('MANUAL-ID');
    $controller->saveCenter(g12Request($user,['name'=>'Renamed Test Center','active'=>false,'source_id'=>'CHANGED-ID']),$first);
    expect($first->fresh()->source_id)->toBe($code)->and($first->fresh()->active)->toBeFalse();
    $controller->saveCenter(g12Request($user,['name'=>'Second Center','active'=>true]));
    expect(G12ExamCenter::where('name','Second Center')->value('source_id'))->not->toBe($code);
    $imported=G12ExamCenter::create(['name'=>'MoEYS Center','name_key'=>'moeys center','source_id'=>'00027']);
    $controller->saveCenter(g12Request($user,['name'=>'Corrected MoEYS Center','active'=>true]),$imported);
    expect($imported->fresh()->source_id)->toBe('00027');
    expect(fn()=>$controller->saveCenter(g12Request($user,['name'=>'Second Center','active'=>true])))->toThrow(ValidationException::class,'already exists');
});
it('toggles exam center status with manage permission and records the change',function(){
    $manager=g12ExamUser(['view','centers','centers-manage']);$controller=app(G12NationalExamController::class);
    $center=G12ExamCenter::create(['name'=>'Status Center','name_key'=>'status center','active'=>true]);
    $response=$controller->centerStatus(g12Request($manager,['active'=>false]),$center)->getData(true);
    expect($response['active'])->toBeFalse()->and($center->fresh()->active)->toBeFalse()
        ->and(DB::table('tb_g12_exam_event')->where('action','center-status-updated')->exists())->toBeTrue();
    DB::table('access_user_permission_overrides')->where('user_id',$manager->id)->where('permission_id',App\Models\Permission::where('code','g12-national-exams.centers-manage')->value('id'))->delete();
    $viewer=$manager->fresh();
    expect(fn()=>$controller->centerStatus(g12Request($viewer,['active'=>true]),$center))->toThrow(HttpException::class);
});
it('paginates and searches exam center settings like the student enrollment list',function(){
    $user=g12ExamUser(['view','centers']);$controller=app(G12NationalExamController::class);
    foreach(range(1,23) as $number)G12ExamCenter::create(['name'=>sprintf('Center %02d',$number),'name_key'=>sprintf('center %02d',$number),'active'=>$number%2===1]);
    $page=$controller->centers(g12Request($user,['page'=>2,'per_page'=>'10']))->getData(true);
    expect($page['current_page'])->toBe(2)->and($page['per_page'])->toBe(10)->and($page['total'])->toBe(23)->and($page['data'])->toHaveCount(10)
        ->and($page['data'][0]['name'])->toBe('Center 11')->and($page['data'][0]['active'])->toBeTrue();
    $byId=$controller->centers(g12Request($user,['sort_by'=>'source_id','sort_dir'=>'desc','per_page'=>'10']))->getData(true);
    expect($byId['data'][0]['source_id'])->toBe('EC-000023');
    $filtered=$controller->centers(g12Request($user,['search'=>'Center 2','per_page'=>'10']))->getData(true);
    expect($filtered['total'])->toBe(4)->and(array_column($filtered['data'],'name'))->toBe(['Center 20','Center 21','Center 22','Center 23']);
    DB::table('access_user_permission_overrides')->where('user_id',$user->id)->where('permission_id',App\Models\Permission::where('code','g12-national-exams.centers')->value('id'))->update(['allowed'=>false]);
    expect(fn()=>$controller->centers(g12Request($user->fresh(),['per_page'=>'10'])))->toThrow(HttpException::class);
});
it('blocks duplicate workbook rows, unassigned campuses and stale import previews',function(){
    $user=User::find(1);$service=app(G12NationalExams::class);$row=g12ExamData(['_row'=>2,'g12_exam_center'=>'']);$batch=g12Batch([$row,array_replace($row,['_row'=>3])]);
    expect($service->inspect($user,$batch)['errors'])->toBe(1);
    expect(fn()=>$service->confirm($user,$batch,[],['2'=>null,'3'=>null]))->toThrow(ValidationException::class,'Resolve');expect(G12ExamRecord::count())->toBe(0);
    $record=$service->save($user,$row);$batch=g12Batch([$row]);$preview=$service->inspect($user,$batch);$service->save($user,$row+['version'=>1],$record);
    expect(fn()=>$service->confirm($user,$batch,[],['2'=>$preview['rows'][0]['version']]))->toThrow(ValidationException::class,'changed after preview');
    $campusUser=g12ExamUser(['view','imports','import']);$denied=G12ExamImport::create(['user_id'=>2,'kind'=>'excel','filename'=>'denied.xlsx','payload'=>['records'=>[array_replace($row,['campus'=>'BKK'])],'centers'=>[]],'expires_at'=>now()->addHour()]);
    expect($service->inspect($campusUser,$denied,['campuses'=>['BKK'=>2]])['errors'])->toBe(1);
});
it('preserves managed certificate collections and all document versions when reimporting students',function(){
    $user=User::find(1);$service=app(G12NationalExams::class);$record=$service->save($user,g12ExamData());$files=app(G12NationalExamFiles::class);
    $receipt=$files->store($user,$record,'soft_certificate_received',g12PhotoBytes(),'receipt.jpg');
    $files->collection($user,$record,'soft',['status'=>'collected','collected_date'=>'2026-10-26','recipient'=>'Student','version'=>1]);
    $service->save($user,g12ExamData(['received_soft_certificate'=>'Not Yet']),$record,true);
    expect($record->certificates()->where('type','soft')->sole()->status)->toBe('collected')->and($record->files()->count())->toBe(1);Storage::disk('local')->assertExists($receipt->path);
});
it('requires a signed receipt, permission and reason for collection corrections and keeps other certificates unchanged',function(){
    $admin=User::find(1);$record=app(G12NationalExams::class)->save($admin,g12ExamData());$service=app(G12NationalExamFiles::class);
    $data=['status'=>'collected','collected_date'=>'2026-10-26','recipient'=>'Student','version'=>1];
    expect(fn()=>$service->collection($admin,$record,'soft',$data))->toThrow(ValidationException::class,'signed certificate');
    $service->store($admin,$record,'soft_certificate_received',g12PhotoBytes(),'receipt.jpg');$service->collection($admin,$record,'soft',$data);
    expect($record->certificates()->where('type','hard')->sole()->status)->toBe('awaiting');
    $user=g12ExamUser(['view','certificates','collection']);
    expect(fn()=>$service->collection($user,$record,'soft',array_replace($data,['version'=>2,'reason'=>'Correction requested'])))->toThrow(HttpException::class);
    expect(fn()=>$service->collection($admin,$record,'soft',array_replace($data,['version'=>2])))->toThrow(ValidationException::class,'reason');
    $service->collection($admin,$record,'soft',array_replace($data,['version'=>2,'recipient'=>'Corrected Recipient','reason'=>'Recipient spelling corrected']));
    expect($record->certificates()->where('type','soft')->sole()->recipient)->toBe('Corrected Recipient')->and(DB::table('tb_g12_exam_event')->where('action','certificate-updated')->count())->toBe(2);
});
it('stores portraits by year and documents by stable student folder, retaining replaced photo bytes',function(){
    $user=User::find(1);$record=app(G12NationalExams::class)->save($user,g12ExamData());$service=app(G12NationalExamFiles::class);
    $photo=$service->store($user,$record,'portrait',g12PhotoBytes(),'120804049559121000001.jpg');expect($photo->path)->toBe('stuimages_g12_national_exam/2025-2026/120804049559121000001.webp')
        ->and($photo->original_name)->toBe('120804049559121000001.jpg')->and($photo->mime)->toBe('image/webp')->and($photo->size)->toBeLessThan(40_000);
    $saved=Storage::disk('local')->get($photo->path);expect(getimagesizefromstring($saved)['mime'])->toBe('image/webp')
        ->and($photo->sha256)->toBe(hash('sha256',$saved))->and($photo->size)->toBe(strlen($saved));
    $response=app(G12NationalExamController::class)->file(g12Request($user,['download'=>1]),$photo);
    expect($response->headers->get('Content-Type'))->toBe('image/webp')->and($response->headers->get('Content-Disposition'))->toContain('120804049559121000001.webp');
    expect(fn()=>$service->store($user,$record->fresh(),'portrait',g12PhotoBytes(),'replacement.jpg'))->toThrow(ValidationException::class,'Confirm Replace');
    $bytes=g12PhotoBytes(180);$replacement=$service->store($user,$record->fresh(),'portrait',$bytes,'replacement.jpg',true);
    expect($record->fresh()->photo_path)->toBe($replacement->path)->and($replacement->path)->toEndWith('/replacement.webp');Storage::disk('local')->assertExists($photo->path);
    $doc=$service->store($user,$record,'hard_certificate',g12PhotoBytes(),'MoEYS Diploma.jpg');expect($doc->path)->toStartWith('g12_national_exam_documents/2025-2026/'.$record->document_folder.'/hard_certificate/');
    expect(fn()=>$service->store($user,$record,'other_documents','malicious code','file.jpg'))->toThrow(ValidationException::class,'Choose a JPG');
});
it('matches exact MoEYS photo filenames and student IDs, flags ambiguity, and permits manual unmatched assignment',function(){
    $user=User::find(1);$exam=app(G12NationalExams::class);$record=$exam->save($user,g12ExamData(['photo_filename'=>'120804049559121000001.jpg']));
    $service=app(G12NationalExamFiles::class);$file=UploadedFile::fake()->createWithContent('120804049559121000001.jpg',g12PhotoBytes());$batch=$service->preview($user,'2025-2026',[$file]);
    expect($batch->payload['entries'][0]['status'])->toBe('matched');$service->process($user,$batch,0,null,false);
    expect($record->fresh()->photo_path)->not->toBeNull();$service->process($user,$batch,0,null,false);expect($record->files()->count())->toBe(1);
    $unknown=$service->preview($user,'2025-2026',[UploadedFile::fake()->createWithContent('unknown.jpg',g12PhotoBytes())]);expect($unknown->payload['entries'][0]['status'])->toBe('unmatched');
    $service->process($user,$unknown,0,$record->id,true);expect($record->files()->count())->toBe(1); // identical image is retained once
    $next=$exam->save($user,g12ExamData(['student_id'=>'00999','academic_year'=>'2026-2027']));
    expect(fn()=>$service->process($user,$unknown,0,$next->id,true))->not->toThrow(Throwable::class); // retry is idempotent
    $wrongYear=$service->preview($user,'2025-2026',[UploadedFile::fake()->createWithContent('wrongyear.jpg',g12PhotoBytes())]);
    expect(fn()=>$service->process($user,$wrongYear,0,$next->id,false))->toThrow(ValidationException::class,'same academic year');
});
it('rejects ZIP traversal and previews safe nested photo entries without extracting paths',function(){
    $user=User::find(1);app(G12NationalExams::class)->save($user,g12ExamData());$service=app(G12NationalExamFiles::class);
    $path=tempnam(sys_get_temp_dir(),'g12zip');try{$zip=new ZipArchive();$zip->open($path,ZipArchive::OVERWRITE);$zip->addFromString('../escape.jpg',g12PhotoBytes());$zip->close();
        expect(fn()=>$service->preview($user,'2025-2026',[new UploadedFile($path,'photos.zip','application/zip',null,true)]))->toThrow(ValidationException::class,'unsafe paths');
        $zip=new ZipArchive();$zip->open($path,ZipArchive::OVERWRITE);$zip->addFromString('class/00123.jpg',g12PhotoBytes());$zip->close();
        $batch=$service->preview($user,'2025-2026',[new UploadedFile($path,'photos.zip','application/zip',null,true)]);expect($batch->payload['entries'][0]['status'])->toBe('matched');$service->process($user,$batch,0,null,false);
        $stored=G12ExamFile::where('kind','portrait')->sole();expect($stored->path)->toEndWith('/00123.webp')->and($stored->size)->toBeLessThan(40_000)
            ->and(getimagesizefromstring(Storage::disk('local')->get($stored->path))['mime'])->toBe('image/webp');
    }finally{@unlink($path);}
});
it('returns every ZIP photo as a preview row with a stable index and matching status',function(){
    $user=User::find(1);app(G12NationalExams::class)->save($user,g12ExamData(['photo_filename'=>'120804049559121000001.jpg']));
    $path=tempnam(sys_get_temp_dir(),'g12-preview-zip-');
    try{
        $zip=new ZipArchive();$zip->open($path,ZipArchive::OVERWRITE);
        $zip->addFromString('photo_2025/120804049559121000001.jpg',g12PhotoBytes());
        $zip->addFromString('photo_2025/unmatched.jpg',g12PhotoBytes());$zip->close();
        $request=g12Request($user,['academic_year'=>'2025-2026']);
        $request->files->set('files',[new UploadedFile($path,'photo_2025.zip','application/zip',null,true)]);
        $response=app(G12NationalExamController::class)->photoPreview($request)->getData(true);
        expect($response['year'])->toBe('2025-2026')->and(array_is_list($response['entries']))->toBeTrue()
            ->and($response['entries'])->toHaveCount(2)
            ->and(array_column($response['entries'],'index'))->toBe([0,1])
            ->and(array_column($response['entries'],'status'))->toBe(['matched','unmatched'])
            ->and($response['entries'][0]['record_id'])->toBe(G12ExamRecord::sole()->id);
    }finally{@unlink($path);}
});
it('returns JSON upload validation errors through the real G12 route instead of redirecting to an HTML page',function(){
    $this->withoutMiddleware()->actingAs(User::find(1));
    $this->postJson(route('g12-national-exams.photos.preview'),['academic_year'=>'2025-2026'])
        ->assertUnprocessable()->assertJsonValidationErrors('files');
    $response=$this->postJson(route('g12-national-exams.photos.preview'),['academic_year'=>'2025-2026','files'=>[new UploadedFile('', 'photo_2025-2026.zip', 'application/zip', UPLOAD_ERR_INI_SIZE, true)]])
        ->assertUnprocessable()->assertJsonValidationErrors('files.0');
    expect($response->json('errors')['files.0'][0])->toContain('PHP error 1')->toContain('PHP-FPM');
    $partial=$this->postJson(route('g12-national-exams.photos.preview'),['academic_year'=>'2025-2026','files'=>[new UploadedFile('', 'photo_2025-2026.zip', 'application/zip', UPLOAD_ERR_PARTIAL, true)]])
        ->assertUnprocessable()->assertJsonValidationErrors('files.0');
    expect($partial->json('errors')['files.0'][0])->toContain('partially uploaded')->toContain('PHP error 3');
});
it('rejects oversized photo ZIPs with a JSON size error through the upload route',function(){
    $this->withoutMiddleware()->actingAs(User::find(1));
    $file=UploadedFile::fake()->create('photo_2025-2026.zip',598*1024,'application/zip');
    $this->postJson(route('g12-national-exams.photos.preview'),['academic_year'=>'2025-2026','files'=>[$file]])
        ->assertUnprocessable()->assertJsonValidationErrors('files.0');
    expect(G12ExamImport::count())->toBe(0);
});
it('keeps identical MoEYS filenames for different students without overwriting either image',function(){
    $user=User::find(1);$exams=app(G12NationalExams::class);$first=$exams->save($user,g12ExamData());$second=$exams->save($user,g12ExamData(['student_id'=>'00456']));$files=app(G12NationalExamFiles::class);
    $a=$files->store($user,$first,'portrait',g12PhotoBytes(),'same-moeys-name.jpg');$b=$files->store($user,$second,'portrait',g12PhotoBytes().' ','same-moeys-name.jpg');
    $encoded=app(App\Services\G12NationalExamPhoto::class)->webp(g12PhotoBytes());
    expect($a->path)->not->toBe($b->path)->and($a->path)->toEndWith('/same-moeys-name.webp')->and(Storage::disk('local')->get($a->path))->toBe($encoded)->and(Storage::disk('local')->get($b->path))->toBe($encoded);
});
it('remembers a manual photo filename match for later uploads and rejects expired or foreign batches',function(){
    $admin=User::find(1);$record=app(G12NationalExams::class)->save($admin,g12ExamData());$files=app(G12NationalExamFiles::class);
    $photo=UploadedFile::fake()->createWithContent('120804049559121000001.jpg',g12PhotoBytes());$batch=$files->preview($admin,'2025-2026',[$photo]);expect($batch->payload['entries'][0]['status'])->toBe('unmatched');
    $files->process($admin,$batch,0,$record->id,false);expect($record->fresh()->photo_filename)->toBe('120804049559121000001.jpg');
    $retry=$files->preview($admin,'2025-2026',[$photo]);expect($retry->payload['entries'][0]['status'])->toBe('existing');
    $user=g12ExamUser(['view','photos','photos-upload']);expect(fn()=>$files->process($user,$retry,0,$record->id,true))->toThrow(HttpException::class);
    $retry->update(['expires_at'=>now()->subMinute()]);expect(fn()=>$files->process($admin,$retry,0,$record->id,true))->toThrow(ValidationException::class,'expired');
});
it('scopes list, reports, private files and record details to authorized campuses',function(){
    $admin=User::find(1);$service=app(G12NationalExams::class);$first=$service->save($admin,g12ExamData());$other=$service->save($admin,g12ExamData(['student_id'=>'other','campus_id'=>2]));
    $file=app(G12NationalExamFiles::class)->store($admin,$other,'soft_certificate',g12PhotoBytes(),'certificate.jpg');
    $user=g12ExamUser(['view','records','reports','export','certificates','documents-view']);$controller=app(G12NationalExamController::class);
    expect(Access::allows($user,'records',1))->toBeTrue()->and(Access::allows($user,'records'))->toBeTrue();
    $list=$controller->list(g12Request($user,['tab'=>'records','per_page'=>'15','page'=>99]))->getData(true);expect($list['total'])->toBe(1)->and($list['current_page'])->toBe(1)->and($list['data'][0]['id'])->toBe($first->id);
    expect(fn()=>$controller->show(g12Request($user),$other))->toThrow(HttpException::class);
    expect(fn()=>$controller->file(g12Request($user),$file))->toThrow(HttpException::class);
    $report=$controller->report(g12Request($user,['format'=>'print']));expect($report->getData()['records']->modelKeys())->toBe([$first->id]);
});
it('lists distinct academic years from student exam records within photo upload campus access',function(){
    $admin=User::find(1);$service=app(G12NationalExams::class);
    $service->save($admin,g12ExamData());$service->save($admin,g12ExamData(['student_id'=>'00999']));
    $service->save($admin,g12ExamData(['academic_year'=>'2026-2027']));
    $service->save($admin,g12ExamData(['academic_year'=>'2024-2025','campus_id'=>2]));
    $user=g12ExamUser(['view','photos','photos-upload']);
    $result=app(G12NationalExamController::class)->meta(g12Request($user,['tab'=>'photos']))->getData(true);
    expect($result['years'])->toBe(['2026-2027','2025-2026']);
    G12ExamRecord::where('campus_id',1)->update(['campus_id'=>2]);
    expect(app(G12NationalExamController::class)->meta(g12Request($user,['tab'=>'photos']))->getData(true)['years'])->toBe([]);
});
it('reads the provided MoEYS workbook fields, Excel dates and center settings without importing real records',function(){
    $path=storage_path('app/imports/g12_national_exams.xlsx');if(!is_file($path))$this->markTestSkipped('Local sample not present.');
    $copy=tempnam(sys_get_temp_dir(),'g12sample');try{copy($path,$copy);$book=app(G12NationalExamWorkbook::class)->read($copy);
        expect($book['records'])->not->toBeEmpty()->and($book['centers'])->not->toBeEmpty()->and($book['headers'])->toContain('student_id','inage_hard_certreceived_name','received_soft_certificate')->and($book['records'][0]['dob'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');expect(G12ExamRecord::count())->toBe(0);
    }finally{@unlink($copy);}
});
it('downloads a template that the actual importer can preview and confirm with text identifiers intact',function(){
    G12ExamCenter::create(['name'=>'Center & School','name_key'=>'center & school','source_id'=>'001']);
    G12ExamCenter::create(['name'=>'Old Center','name_key'=>'old center','source_id'=>'099','active'=>false]);
    $user=g12ExamUser(['view','imports','import']);$request=g12Request($user);
    $response=app(G12NationalExamController::class)->template($request);
    expect($response->headers->get('Content-Type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($response->headers->get('Content-Disposition'))->toContain('g12-national-exams-import-template.xlsx');
    $path=tempnam(sys_get_temp_dir(),'g12-template-test-');
    try {
        file_put_contents($path,$response->getContent());
        $book=app(G12NationalExamWorkbook::class)->read($path);
        expect($book['headers'])->toEqual(app(App\Support\G12NationalExamTemplate::class)->headers())
            ->toContain('photo_filename','received_soft_certificate','received_hard_cert_date','inage_hard_certreceived_name');
        expect($book['records'])->toHaveCount(1)->and($book['records'][0]['student_id'])->toBe('SAMPLE-001')
            ->and($book['records'][0]['phone_number'])->toBe('012345678')
            ->and($book['records'][0]['g12_exam_seat'])->toBe('005')
            ->and($book['records'][0]['photo_filename'])->toBe('120804049559121000001.jpg')
            ->and($book['records'][0]['campus'])->toBe('BCH')
            ->and($book['centers'])->toHaveCount(1)->and($book['centers'][0]['excenter_id'])->toBe('001')
            ->and($book['centers'][0]['exam_center'])->toBe('Center & School');
        $zip=new ZipArchive();$zip->open($path);
        expect($zip->getFromName('xl/workbook.xml'))->toContain('name="Instructions"')
            ->and($zip->getFromName('xl/worksheets/sheet3.xml'))->toContain('Replace the SAMPLE student row')
            ->and($zip->getFromName('xl/styles.xml'))->toContain('numFmtId="49"');
        for($i=0;$i<$zip->numFiles;$i++){
            $doc=new DOMDocument();expect($doc->loadXML($zip->getFromIndex($i),LIBXML_NONET))->toBeTrue();
        }
        $zip->close();
        $service=app(G12NationalExams::class);$batch=$service->preview($user,new UploadedFile($path,'template.xlsx',null,null,true));
        $preview=$service->inspect($user,$batch);expect($preview['errors'])->toBe(0);
        $service->confirm($user,$batch,[],['2'=>null]);
        expect(G12ExamRecord::sole()->student_code)->toBe('SAMPLE-001');
    }finally{@unlink($path);}
});
it('provides fictional sample center data on an empty installation and requires import permission to download',function(){
    $user=g12ExamUser(['view','imports']);$controller=app(G12NationalExamController::class);
    expect(fn()=>$controller->template(g12Request($user)))->toThrow(HttpException::class);
    $route=app('router')->getRoutes()->match(g12Request(User::find(1),[], '/g12-national-exams/imports/template'));
    expect($route->getName())->toBe('g12-national-exams.template');
    $response=$controller->template(g12Request(User::find(1)));
    $path=tempnam(sys_get_temp_dir(),'g12-template-empty-');
    try{
        file_put_contents($path,$response->getContent());$book=app(G12NationalExamWorkbook::class)->read($path);
        expect($book['centers'][0]['exam_center'])->toBe('SAMPLE EXAM CENTER')
            ->and($book['records'][0]['g12_exam_center'])->toBe('SAMPLE EXAM CENTER')
            ->and(G12ExamRecord::count())->toBe(0)->and(G12ExamImport::count())->toBe(0);
    }finally{@unlink($path);}
});
