<?php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB,Storage};
use App\Models\User;
use App\Support\G12NationalExamPermissions;
function createG12NationalSchema(bool $fakeStorage=true): void {
    Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->boolean('is_global')->default(false);$t->integer('active_campus_id')->nullable();$t->integer('department_id')->nullable();$t->boolean('status')->default(true);$t->timestamps();});
    Schema::create('tb_school_info',function(Blueprint $t){$t->id();$t->string('campus_name_en');$t->boolean('status')->default(true);$t->softDeletes();});
    Schema::create('tb_student',function(Blueprint $t){$t->id();$t->string('student_id');$t->string('full_name_en');$t->string('photo_path')->nullable();});
    Schema::create('access_roles',function(Blueprint $t){$t->id();$t->string('code');$t->boolean('is_global')->default(false);$t->boolean('status')->default(true);});
    Schema::create('access_permissions',function(Blueprint $t){$t->id();foreach(['code','module','action','name'] as $f)$t->string($f);$t->timestamps();});
    Schema::create('access_user_roles',function(Blueprint $t){$t->integer('user_id');$t->integer('role_id');$t->integer('campus_id')->nullable();$t->timestamps();});
    Schema::create('access_role_permissions',function(Blueprint $t){$t->integer('role_id');$t->integer('permission_id');$t->timestamps();});
    Schema::create('access_user_permission_overrides',function(Blueprint $t){$t->id();$t->integer('user_id');$t->integer('permission_id');$t->boolean('allowed');$t->timestamps();});
    Schema::create('access_user_campuses',function(Blueprint $t){$t->integer('user_id');$t->integer('campus_id');$t->boolean('is_primary')->default(true);$t->timestamp('assigned_at')->nullable();$t->timestamps();});
    Schema::create('access_departments',function(Blueprint $t){$t->id();$t->string('name');});
    Schema::create('access_department_permissions',function(Blueprint $t){$t->integer('department_id');$t->integer('permission_id');$t->timestamps();});
    DB::table('access_roles')->insert([['id'=>1,'code'=>'super-admin'],['id'=>2,'code'=>'registrar']]);
    DB::table('access_permissions')->insert(['code'=>'students.view','module'=>'students','action'=>'view','name'=>'Students']);
    DB::table('users')->insert([['id'=>1,'name'=>'National Exam Admin','is_global'=>true,'active_campus_id'=>1],['id'=>2,'name'=>'Campus Registrar','is_global'=>false,'active_campus_id'=>1]]);
    DB::table('access_user_roles')->insert(['user_id'=>1,'role_id'=>1,'campus_id'=>null]);
    DB::table('tb_school_info')->insert([['id'=>1,'campus_name_en'=>'BCH'],['id'=>2,'campus_name_en'=>'BKK']]);
    DB::table('access_user_campuses')->insert(['user_id'=>2,'campus_id'=>1]);
    DB::table('tb_student')->insert(['id'=>1,'student_id'=>'00123','full_name_en'=>'Existing school name']);
    (require database_path('migrations/2026_10_26_000001_create_g12_national_exam_module.php'))->up();
    if($fakeStorage)Storage::fake('local');
}
function g12ExamUser(array $actions=['view','records'],string $source='user'): User {
    $codes=array_map(fn($a)=>'g12-national-exams.'.$a,$actions);
    $ids=DB::table('access_permissions')->whereIn('code',$codes)->pluck('id');
    if($source==='role'){DB::table('access_user_roles')->insert(['user_id'=>2,'role_id'=>2,'campus_id'=>1]);foreach($ids as $id)DB::table('access_role_permissions')->insert(['role_id'=>2,'permission_id'=>$id]);}
    elseif($source==='department'){DB::table('access_departments')->insert(['id'=>1,'name'=>'Registrar']);DB::table('users')->where('id',2)->update(['department_id'=>1]);foreach($ids as $id)DB::table('access_department_permissions')->insert(['department_id'=>1,'permission_id'=>$id]);}
    else foreach($ids as $id)DB::table('access_user_permission_overrides')->insert(['user_id'=>2,'permission_id'=>$id,'allowed'=>true]);
    return User::findOrFail(2);
}
function g12ExamData(array $values=[]): array {return array_replace(['student_id'=>'00123','academic_year'=>'2025-2026','student_name_en'=>'NATIONAL EXAM STUDENT','student_name_kh'=>'សិស្ស','campus_id'=>1,'campus'=>'BCH','grade'=>'12A','g12_letter_grade'=>'A','g12_exam_center'=>'Test Center','g12_exam_room'=>'01','g12_exam_seat'=>'005'], $values);}
function g12PhotoBytes(int $red=240): string {$image=imagecreatetruecolor(600,800);imagefill($image,0,0,imagecolorallocate($image,$red,245,250));ob_start();imagejpeg($image);$bytes=ob_get_clean();imagedestroy($image);return $bytes;}
function g12Batch(array $rows,array $centers=[]){return \App\Models\G12ExamImport::create(['user_id'=>1,'kind'=>'excel','filename'=>'test.xlsx','payload'=>['records'=>$rows,'centers'=>$centers],'expires_at'=>now()->addHour()]);}
function g12Request(User $user,array $data=[],string $path='/g12-national-exams',string $method='GET'): \Illuminate\Http\Request {
    $request=\Illuminate\Http\Request::create($path,$method,$data);$request->setLaravelSession(app('session')->driver());app()->instance('request',$request);$request->setUserResolver(fn()=>$user);return $request;
}
