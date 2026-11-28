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

function attendancePeriodSetup(): void {
    Schema::table('tb_student_enrollment',function(Blueprint $t){$t->integer('session_id')->nullable();$t->integer('group_id')->nullable();});
    Schema::create('tb_session',function(Blueprint $t){$t->id();$t->string('session_short_name');$t->softDeletes();});
    Schema::create('tb_group',function(Blueprint $t){$t->id();$t->string('group_name');$t->softDeletes();});
    Schema::create('tb_grade',function(Blueprint $t){$t->id();$t->string('grade');$t->string('grade_short_name');$t->softDeletes();});
    Schema::create('schedule_periods',function(Blueprint $t){$t->id();$t->string('name');$t->string('session');$t->string('starts_at');$t->string('ends_at');$t->boolean('active')->default(true);$t->json('applicable_groups');$t->json('full_day_grades')->nullable();$t->softDeletes();});
    DB::table('tb_session')->insert([['id'=>1,'session_short_name'=>'M'],['id'=>2,'session_short_name'=>'A'],['id'=>3,'session_short_name'=>'III']]);
    DB::table('tb_grade')->insert(['id'=>12,'grade'=>'12','grade_short_name'=>'12']);
    DB::table('tb_student_enrollment')->where('id',1)->update(['session_id'=>1]);
    foreach([['08:00','08:50','M'],['09:00','09:50','M'],['13:00','13:50','A']] as $i=>[$start,$end,$group])
        DB::table('schedule_periods')->insert(['id'=>$i+1,'name'=>'Period '.($i+1),'session'=>$group==='M'?'morning':'afternoon','starts_at'=>$start,'ends_at'=>$end,'applicable_groups'=>json_encode([$group,'III']),'full_day_grades'=>json_encode(['12'])]);
}

it('infers one or multiple missed sessions from complete periods and preserves snapshots without campus input',function(){
    attendancePeriodSetup();$one=attendanceSource();$one[4]='08:00-08:50 a';$one[6]='';$one[7]='';
    $two=$one;$two[2]='2025-10-14';$two[3]='U';$two[4]='8:00 AM – 9:50 AM';
    $id=attendancePreview($this,[$one,$two]);
    expect(DB::table('attendance_import_rows')->where('batch_id',$id)->where('outcome','pending')->count())->toBe(2);
    $preview=app(AttendanceTransferController::class)->inspect(g12Request($this->admin),$id)->getData(true);
    expect($preview['rows']['data'][1]['missed_sessions'])->toBe(2)->and($preview['rows']['data'][1]['auto_periods'])->toBeTrue();
    $this->service->confirm($this->admin,$id);
    expect(DB::table('attendance_daily_marks')->orderBy('attendance_date')->pluck('missed_sessions')->all())->toBe([1,2]);
    $snapshots=json_decode(DB::table('attendance_daily_marks')->where('attendance_date','2025-10-14')->value('absent_periods'),true);
    expect(array_column($snapshots,'id'))->toBe([1,2])->and($snapshots[0]['starts_at'])->toBe('08:00');
    $again=attendancePreview($this,[$one]);expect(DB::table('attendance_import_rows')->where('batch_id',$again)->value('outcome'))->toBe('duplicate');
});

it('supports abbreviated PM and 24 hour ranges for absence and tardiness while present stays zero',function(){
    attendancePeriodSetup();DB::table('tb_student_enrollment')->where('id',1)->update(['session_id'=>2]);
    $rows=[];foreach(['01:00-01:50 p','13:00-13:50','1:00 p.m.-1:50 p.m.'] as $i=>$time){$row=attendanceSource('2025-10-'.(13+$i));$row[4]=$time;$row[7]='';$rows[]=$row;}
    $late=attendanceSource('2025-10-16','T');$late[4]='01:00-01:50 pm';$late[7]='';$rows[]=$late;
    $present=$late;$present[2]='2025-10-17';$present[3]='P';$rows[]=$present;
    $id=attendancePreview($this,$rows);$this->service->confirm($this->admin,$id);
    expect(DB::table('attendance_daily_marks')->orderBy('attendance_date')->pluck('missed_sessions')->all())->toBe([1,1,1,1,0]);
    expect(json_decode(DB::table('attendance_daily_marks')->where('status','T')->value('absent_periods'),true)[0]['id'])->toBe(3);
});

it('calculates multiple tardy sessions preserves T and skips identical reimports',function(){
    attendancePeriodSetup();$row=attendanceSource('2025-10-13','T');$row[4]='08:00-09:50 AM';$row[7]='';
    $id=attendancePreview($this,[$row]);
    $data=app(AttendanceTransferController::class)->inspect(g12Request($this->admin),$id)->getData(true);
    expect($data['rows']['data'][0]['missed_sessions'])->toBe(2)->and($data['rows']['data'][0]['auto_periods'])->toBeTrue();
    $this->service->confirm($this->admin,$id);
    $mark=DB::table('attendance_daily_marks')->first();
    expect($mark->status)->toBe('T')->and($mark->missed_sessions)->toBe(2)
        ->and(array_column(json_decode($mark->absent_periods,true),'id'))->toBe([1,2]);
    $again=attendancePreview($this,[$row]);
    expect(DB::table('attendance_import_rows')->where('batch_id',$again)->value('outcome'))->toBe('duplicate');
    // Earlier tardy imports are never silently rewritten when the calculation policy changes.
    DB::table('attendance_daily_marks')->where('id',$mark->id)->update(['missed_sessions'=>0,'absent_periods'=>'[]']);
    $conflict=attendancePreview($this,[$row]);$this->service->confirm($this->admin,$conflict);
    expect(DB::table('attendance_import_rows')->where('batch_id',$conflict)->value('outcome'))->toBe('review')
        ->and(DB::table('attendance_daily_marks')->value('missed_sessions'))->toBe(0);
});

it('reviews unmatched or missing tardy periods and honors explicit zero and manual counts',function(){
    attendancePeriodSetup();$rows=[];
    foreach([['08:10-08:50 AM',''],['',''],['old custom period','0'],['08:00-08:50 AM','3']] as $i=>[$time,$missed]){
        $row=attendanceSource('2025-10-'.(13+$i),'T');$row[4]=$time;$row[7]=$missed;$rows[]=$row;
    }
    $id=attendancePreview($this,$rows);$this->service->confirm($this->admin,$id);
    expect(DB::table('attendance_import_rows')->where('batch_id',$id)->where('outcome','review')->count())->toBe(2)
        ->and(DB::table('attendance_daily_marks')->orderBy('attendance_date')->pluck('missed_sessions')->all())->toBe([0,3])
        ->and(DB::table('attendance_daily_marks')->pluck('status')->all())->toBe(['T','T']);
});

it('confirms and recognizes duplicates when MySQL JSON reorders snapshot keys',function(){
    attendancePeriodSetup();$row=attendanceSource();$row[4]='08:00-08:50 AM';$row[7]='';
    $id=attendancePreview($this,[$row]);$record=DB::table('attendance_import_rows')->where('batch_id',$id)->first();
    $normalized=json_decode($record->normalized,true);
    foreach($normalized['absent_periods'] as &$snapshot)ksort($snapshot);unset($snapshot);
    DB::table('attendance_import_rows')->where('id',$record->id)->update(['normalized'=>json_encode($normalized)]);
    $this->service->confirm($this->admin,$id);
    expect(DB::table('attendance_import_rows')->where('id',$record->id)->value('outcome'))->toBe('imported');
    DB::table('attendance_daily_marks')->update(['absent_periods'=>json_encode($normalized['absent_periods'])]);
    $repeat=attendancePreview($this,[$row]);
    expect(DB::table('attendance_import_rows')->where('batch_id',$repeat)->value('outcome'))->toBe('duplicate')
        ->and(DB::table('attendance_daily_marks')->count())->toBe(1);
    $changed=$normalized['absent_periods'];$changed[0]['ends_at']='08:49';
    expect(App\Services\AttendanceImportPeriods::sameSnapshots($changed,$normalized['absent_periods']))->toBeFalse();
});

it('recognizes full day nursery kindergarten and primary short grade separators',function(){
    attendancePeriodSetup();DB::table('tb_student_enrollment')->where('id',1)->update(['session_id'=>3]);
    DB::table('schedule_periods')->where('id',1)->update(['full_day_grades'=>'["N","K1","K2","6"]']);
    foreach(['N-','K1-','K2-','6-'] as $i=>$grade){
        DB::table('tb_grade')->where('id',12)->update(['grade_short_name'=>$grade]);
        $row=attendanceSource('2025-10-'.(13+$i));$row[4]='08:00-08:50 AM';$row[7]='';
        $id=attendancePreview($this,[$row]);
        expect(DB::table('attendance_import_rows')->where('batch_id',$id)->value('outcome'))->toBe('pending');
        $this->service->confirm($this->admin,$id);
    }
    expect(DB::table('attendance_daily_marks')->count())->toBe(4);
});

it('reviews unmatched partial invalid or ambiguous times while honoring explicit session counts',function(){
    attendancePeriodSetup();$rows=[];
    foreach(['08:10-08:50 AM','07:00-07:50 AM','8:00','25:00-26:00','09:50-08:00 AM',''] as $i=>$time){$row=attendanceSource('2025-10-'.(13+$i));$row[4]=$time;$row[7]='';$rows[]=$row;}
    $manual=attendanceSource('2025-10-20');$manual[4]='old custom time';$manual[7]='3';$rows[]=$manual;
    $id=attendancePreview($this,$rows);$this->service->confirm($this->admin,$id);
    expect(DB::table('attendance_import_rows')->where('batch_id',$id)->where('outcome','review')->count())->toBe(6)
        ->and(DB::table('attendance_daily_marks')->count())->toBe(1)->and(DB::table('attendance_daily_marks')->value('missed_sessions'))->toBe(3);
    DB::table('schedule_periods')->insert(['name'=>'Ambiguous duplicate','session'=>'morning','starts_at'=>'08:00','ends_at'=>'08:50','applicable_groups'=>'["M"]']);
    $row=attendanceSource('2025-10-21');$row[4]='08:00-08:50 AM';$row[7]='';
    $id=attendancePreview($this,[$row]);expect(DB::table('attendance_import_rows')->where('batch_id',$id)->value('reason'))->toContain('overlapping');
});

it('matches only applicable study groups and full day grades and rejects changed settings after preview',function(){
    attendancePeriodSetup();$row=attendanceSource();$row[4]='08:00-08:50 AM';$row[7]='';
    DB::table('tb_student_enrollment')->where('id',1)->update(['session_id'=>2]);
    $id=attendancePreview($this,[$row]);expect(DB::table('attendance_import_rows')->where('batch_id',$id)->value('outcome'))->toBe('review');
    DB::table('tb_student_enrollment')->where('id',1)->update(['session_id'=>3]);
    DB::table('schedule_periods')->where('id',1)->update(['full_day_grades'=>'["6"]']);
    $id=attendancePreview($this,[$row]);expect(DB::table('attendance_import_rows')->where('batch_id',$id)->value('outcome'))->toBe('review');
    DB::table('tb_student_enrollment')->where('id',1)->update(['session_id'=>1]);
    $changedGroup=attendancePreview($this,[$row]);
    DB::table('tb_student_enrollment')->where('id',1)->update(['session_id'=>2]);
    $this->service->confirm($this->admin,$changedGroup);
    expect(DB::table('attendance_import_rows')->where('batch_id',$changedGroup)->value('reason'))->toContain('changed after preview');
    DB::table('tb_student_enrollment')->where('id',1)->update(['session_id'=>1]);
    $id=attendancePreview($this,[$row]);DB::table('schedule_periods')->where('id',1)->update(['active'=>false]);
    $this->service->confirm($this->admin,$id);
    expect(DB::table('attendance_import_rows')->where('batch_id',$id)->value('reason'))->toContain('changed after preview')->and(DB::table('attendance_daily_marks')->count())->toBe(0);
});

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
