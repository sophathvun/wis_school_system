<?php

use App\Http\Controllers\AttendanceTransferController;
use App\Models\User;
use App\Services\AttendanceTransfer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../Fixtures/G12NationalExam.php';

beforeEach(function(){
    $this->withoutVite();createG12NationalSchema();
    Schema::create('tb_academic_year',function(Blueprint $t){$t->id();$t->string('academic_year');$t->softDeletes();});
    Schema::create('tb_student_enrollment',function(Blueprint $t){$t->id();foreach(['student_id','academic_year_id','campus_id','class_id'] as $c)$t->integer($c);$t->integer('grade_id')->nullable();$t->string('enrollment_status');$t->date('enrolled_on')->nullable();$t->date('ended_on')->nullable();});
    foreach(['000003_create_daily_student_attendance','000004_scope_daily_attendance_by_grade','000005_add_daily_attendance_author','000008_add_daily_absent_periods','000011_create_attendance_leave'] as $file)(require database_path('migrations/2026_11_03_'.$file.'.php'))->up();
    (require database_path('migrations/2026_11_13_000001_create_attendance_transfers.php'))->up();
    DB::table('access_permissions')->insert(['code'=>'attendance.view','module'=>'attendance','action'=>'view','name'=>'Attendance']);
    DB::table('tb_academic_year')->insert(['id'=>1,'academic_year'=>'2025-2026']);
    DB::table('tb_student_enrollment')->insert(['id'=>1,'student_id'=>1,'academic_year_id'=>1,'campus_id'=>1,'class_id'=>1,'grade_id'=>12,'enrollment_status'=>'graduated','enrolled_on'=>'2025-01-01','ended_on'=>'2026-06-30']);
    $this->service=app(AttendanceTransfer::class);$this->admin=User::find(1);
});

function attendancePreview($test,array $rows,array $headers=AttendanceTransfer::COLUMNS,?User $user=null): string {
    $path=tempnam(sys_get_temp_dir(),'attendance-test-');$stream=fopen($path,'wb');fwrite($stream,"\xEF\xBB\xBF");fputcsv($stream,$headers,',','"','');foreach($rows as $row)fputcsv($stream,$row,',','"','');fclose($stream);
    try{return $test->service->preview($user??$test->admin,$path,'attendance.csv');}finally{unlink($path);}
}
function attendanceSource(string $date='2025-10-13',string $status='E',string $code='00123'): array {return [$code,'2025-2026',$date,$status,'8:40-9:30 AM','Sick','BCH','1'];}

it('previews old Access headings without changing attendance then imports E U and T for historical enrollment',function(){
    $headers=['Student ID','AcademicYear','AbsDate','Attandance','AbsTime','Reason','campus','missed_sessions'];
    $id=attendancePreview($this,[attendanceSource('10/13/2025'),attendanceSource('14-Oct-2025','U'),attendanceSource('2025-10-15','T')],$headers);
    expect(DB::table('attendance_daily_marks')->count())->toBe(0)->and(DB::table('attendance_import_rows')->where('outcome','pending')->count())->toBe(3);
    $this->service->confirm($this->admin,$id);
    expect(DB::table('attendance_daily_marks')->orderBy('attendance_date')->pluck('status')->all())->toBe(['EX','UX','T'])
        ->and(DB::table('attendance_daily_marks')->where('status','P')->count())->toBe(0)
        ->and(DB::table('attendance_daily_marks')->value('remarks'))->toBe('Sick | Time: 8:40-9:30 AM')
        ->and(DB::table('tb_student_enrollment')->value('enrollment_status'))->toBe('graduated')
        ->and(DB::table('attendance_daily_events')->count())->toBe(3);
    $this->service->confirm($this->admin,$id);
    expect(DB::table('attendance_daily_marks')->count())->toBe(3)->and(DB::table('attendance_daily_events')->count())->toBe(3);
    $repeat=attendancePreview($this,[attendanceSource()]);
    expect(DB::table('attendance_import_rows')->where('batch_id',$repeat)->value('outcome'))->toBe('duplicate');
});

it('keeps invalid identities dates and ambiguous enrollment in review',function(){
    $rows=[attendanceSource('2025-02-30'),attendanceSource('2099-01-01'),attendanceSource('2024-01-01'),attendanceSource('2025-11-01','E','123'),attendanceSource('2025-11-02','invalid')];
    $id=attendancePreview($this,$rows);
    expect(DB::table('attendance_import_rows')->where('batch_id',$id)->where('outcome','review')->count())->toBe(5);
    DB::table('tb_student_enrollment')->insert(['id'=>2,'student_id'=>1,'academic_year_id'=>1,'campus_id'=>2,'class_id'=>2,'grade_id'=>12,'enrollment_status'=>'withdrawn','enrolled_on'=>'2025-01-01','ended_on'=>'2026-01-01']);
    $source=attendanceSource();$source[6]='';
    attendancePreview($this,[$source]);
    expect(DB::table('attendance_import_rows')->orderByDesc('id')->value('outcome'))->toBe('review');
    $this->service->confirm($this->admin,$id);
    expect(DB::table('attendance_daily_marks')->count())->toBe(0);
});

it('skips identical duplicates and reviews all differing daily source records',function(){
    $id=attendancePreview($this,[attendanceSource(),attendanceSource(),attendanceSource('2025-10-13','U')]);
    expect(DB::table('attendance_import_rows')->where('outcome','pending')->count())->toBe(0)
        ->and(DB::table('attendance_import_rows')->where('outcome','review')->count())->toBe(2);
    $this->service->confirm($this->admin,$id);
    expect(DB::table('attendance_daily_marks')->count())->toBe(0);
    $id=attendancePreview($this,[attendanceSource('2025-10-14'),attendanceSource('2025-10-14')]);
    $this->service->confirm($this->admin,$id);
    expect(DB::table('attendance_daily_marks')->count())->toBe(1)->and(DB::table('attendance_import_rows')->where('batch_id',$id)->where('outcome','duplicate')->count())->toBe(1);
});

it('never overwrites records created after preview or changed enrollments',function(){
    $old=attendancePreview($this,[attendanceSource()]);$new=attendancePreview($this,[attendanceSource('2025-10-13','U')]);
    $this->service->confirm($this->admin,$new);$this->service->confirm($this->admin,$old);
    expect(DB::table('attendance_daily_marks')->value('status'))->toBe('UX')->and(DB::table('attendance_import_rows')->where('batch_id',$old)->value('outcome'))->toBe('review');
    $changed=attendancePreview($this,[attendanceSource('2025-10-16')]);
    DB::table('tb_student_enrollment')->where('id',1)->update(['ended_on'=>'2025-10-15']);
    $this->service->confirm($this->admin,$changed);
    expect(DB::table('attendance_daily_marks')->count())->toBe(1)->and(DB::table('attendance_import_rows')->where('batch_id',$changed)->value('reason'))->toContain('Enrollment changed');
});

it('resumes imports in bounded batches and rejects another owners batch',function(){
    $rows=[];for($day=0;$day<105;$day++)$rows[]=attendanceSource(\Carbon\CarbonImmutable::parse('2025-01-01')->addDays($day)->toDateString());
    $id=attendancePreview($this,$rows);$this->service->confirm($this->admin,$id);
    expect(DB::table('attendance_daily_marks')->count())->toBe(100)->and(DB::table('attendance_import_batches')->value('status'))->toBe('importing');
    expect(fn()=>$this->service->confirm(User::find(2),$id))->toThrow(HttpException::class);
    $this->service->confirm($this->admin,$id);
    expect(DB::table('attendance_daily_marks')->count())->toBe(105)->and(DB::table('attendance_import_batches')->value('status'))->toBe('completed');
});

it('enforces import permissions and campus scope including explicit denials',function(){
    $controller=app(AttendanceTransferController::class);$user=User::find(2);
    expect(fn()=>$controller->template(g12Request($user)))->toThrow(HttpException::class);
    foreach(['view','transfer','import'] as $action){$permission=DB::table('access_permissions')->where('code','attendance.'.$action)->value('id');DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>$permission,'allowed'=>true]);}
    $user=User::find(2);g12Request($user);
    $id=attendancePreview($this,[attendanceSource()],user:$user);
    expect(DB::table('attendance_import_rows')->where('batch_id',$id)->value('outcome'))->toBe('pending');
    DB::table('tb_student_enrollment')->where('id',1)->update(['campus_id'=>2]);
    $source=attendanceSource();$source[6]='BKK';
    $other=attendancePreview($this,[$source],user:$user);
    expect(DB::table('attendance_import_rows')->where('batch_id',$other)->value('outcome'))->toBe('review');
    DB::table('access_user_permission_overrides')->where('user_id',2)->where('permission_id',DB::table('access_permissions')->where('code','attendance.import')->value('id'))->update(['allowed'=>false]);
    $request=g12Request(User::find(2));
    expect(fn()=>$controller->confirm($request,$id,$this->service))->toThrow(HttpException::class);
    expect(DB::table('attendance_daily_marks')->count())->toBe(0);
});

it('exports recorded marks in the selected scope and retains review source information',function(){
    $controller=app(AttendanceTransferController::class);
    $id=attendancePreview($this,[attendanceSource(),attendanceSource('2025-10-15','U','missing')]);$this->service->confirm($this->admin,$id);
    $request=g12Request($this->admin,['academic_year_id'=>1,'date_to'=>'2025-10-14']);
    $csv=$controller->export($request)->getCallback();ob_start();$csv();$output=ob_get_clean();
    expect($output)->toContain('00123','EX','2025-10-13','Sick | Time: 8:40-9:30 AM')->not->toContain('missing');
    $review=$controller->review(g12Request($this->admin),$id)->getCallback();ob_start();$review();$reviewOutput=ob_get_clean();
    expect($reviewOutput)->toContain('missing','review_reason','No unique enrollment');
    $path=tempnam(sys_get_temp_dir(),'attendance-review-');file_put_contents($path,$reviewOutput);
    try{$reviewId=$this->service->preview($this->admin,$path,'review.csv');}finally{unlink($path);}
    $again=$controller->review(g12Request($this->admin),$reviewId)->getCallback();ob_start();$again();$againOutput=ob_get_clean();
    $header=str_getcsv(explode("\n",$againOutput)[0],',','"','');
    expect(count($header))->toBe(count(array_unique($header)));
    $counts=$controller->inspect(g12Request($this->admin),$id)->getData(true);
    expect($counts['counts']['imported'])->toBe(1)->and($counts['counts']['review'])->toBe(1);
});

it('blocks scheduled approved leave and preserves existing leave records',function(){
    $leave=DB::table('attendance_leaves')->insertGetId(['academic_year_id'=>1,'campus_id'=>1,'enrollment_id'=>1,'student_id'=>1,'class_id'=>1,'grade_id'=>12,'created_by'=>1,'starts_on'=>'2025-10-13','ends_on'=>'2025-10-13','reason'=>'Approved','approved_by'=>'Principal','mode'=>'full','periods'=>'[]']);
    DB::table('attendance_leave_days')->insert(['leave_id'=>$leave,'student_id'=>1,'academic_year_id'=>1,'attendance_date'=>'2025-10-13','status'=>'scheduled']);
    $id=attendancePreview($this,[attendanceSource()]);
    expect(DB::table('attendance_import_rows')->value('reason'))->toContain('Approved leave');
    $this->service->confirm($this->admin,$id);
    expect(DB::table('attendance_daily_marks')->count())->toBe(0)->and(DB::table('attendance_leave_days')->value('status'))->toBe('scheduled');
});

it('rejects malformed headers and columns without saving partial batches',function(){
    expect(fn()=>attendancePreview($this,[['00123']],['student_id']))->toThrow(ValidationException::class);
    expect(DB::table('attendance_import_batches')->count())->toBe(0);
    $id=attendancePreview($this,[array_slice(attendanceSource(),0,4)]);
    expect(DB::table('attendance_import_rows')->where('batch_id',$id)->value('reason'))->toContain('number of columns');
});

it('returns JSON validation errors to the attendance uploader instead of an HTML redirect',function(){
    $this->withoutMiddleware(\App\Http\Middleware\TrackUserPresence::class);
    $this->actingAs($this->admin)->postJson('/attendance/import-export/preview',[])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
    expect(DB::table('attendance_import_batches')->count())->toBe(0);
});
