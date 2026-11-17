<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Schema, Storage};

require_once __DIR__.'/../Fixtures/UserPermissionInheritance.php';

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\TrackUserPresence::class);
    Storage::fake('local'); Storage::fake('public');
    createUserPermissionInheritanceFixture();
    Schema::create('tb_student',function(Blueprint $t){$t->id();$t->string('student_id');$t->string('full_name_en');$t->string('full_name_kh')->nullable();$t->string('photo_path')->nullable();$t->timestamps();});
    Schema::create('tb_academic_year',function(Blueprint $t){$t->id();$t->string('academic_year');$t->string('period_type');$t->string('lifecycle_status');});
    Schema::create('tb_student_enrollment',function(Blueprint $t){$t->id();$t->integer('student_id');$t->integer('academic_year_id');$t->integer('campus_id');$t->string('enrollment_status')->default('active');});
    DB::table('users')->where('id',1)->update(['active_campus_id'=>1,'is_global'=>false]);
    DB::table('access_user_roles')->where('user_id',1)->update(['campus_id'=>1]);
    DB::table('access_user_campuses')->insert(['user_id'=>1,'campus_id'=>1]);
    DB::table('tb_school_info')->insert([['id'=>1,'campus_name_en'=>'BCH'],['id'=>2,'campus_name_en'=>'BKK']]);
    DB::table('tb_academic_year')->insert(['id'=>1,'academic_year'=>'2025-2026','period_type'=>'regular','lifecycle_status'=>'started']);
    DB::table('tb_student')->insert([
        ['id'=>1,'student_id'=>'00123','full_name_en'=>'SOK DARA'],
        ['id'=>2,'student_id'=>'00456','full_name_en'=>'OTHER CAMPUS'],
    ]);
    DB::table('tb_student_enrollment')->insert([
        ['student_id'=>1,'academic_year_id'=>1,'campus_id'=>1],['student_id'=>2,'academic_year_id'=>1,'campus_id'=>2],
    ]);
    (require database_path('migrations/2026_11_17_000001_create_student_photo_imports.php'))->up();
    foreach (['students.view','students.enrollment.view','students.enrollment.bulk-photos'] as $code) {
        $id=DB::table('access_permissions')->where('code',$code)->value('id');
        if(!$id)$id=DB::table('access_permissions')->insertGetId(['code'=>$code,'name'=>$code,'module'=>'students.enrollment','action'=>'view']);
        if(!DB::table('access_role_permissions')->where('role_id',1)->where('permission_id',$id)->exists())DB::table('access_role_permissions')->insert(['role_id'=>1,'permission_id'=>$id]);
    }
    $this->actingAs(User::find(1));
    $this->base='/students/photo-imports';
    $this->batch=function(bool $replace=false){return $this->postJson($this->base,['replace_existing'=>$replace])->assertOk()->json('id');};
    $this->upload=function(string $batch,string $filename){return $this->postJson($this->base.'/'.$batch.'/upload',['file'=>UploadedFile::fake()->image($filename,300,400)]);};
    $this->confirm=function(string $batch){$ids=DB::table('student_photo_rows')->where('batch_id',$batch)->where('status','ready')->pluck('id')->all();return $this->postJson($this->base.'/'.$batch.'/confirm',['selected_ids'=>$ids]);};
});

it('previews exact student IDs without mutation and saves bounded WebP once after confirmation',function(){
    $id=($this->batch)();
    ($this->upload)($id,'Name_with_underscores_00123.jpg')->assertOk();
    expect(DB::table('tb_student')->where('id',1)->value('photo_path'))->toBeNull();
    $preview=$this->getJson($this->base.'/'.$id)->assertOk()->assertJsonPath('rows.0.student_code','00123')->assertJsonPath('rows.0.student_name','SOK DARA');
    $this->get($preview->json('rows.0.source_url'))->assertOk();
    ($this->confirm)($id)->assertOk();
    $this->postJson($this->base.'/'.$id.'/process')->assertOk()->assertJsonPath('status','completed');
    $path=DB::table('tb_student')->where('id',1)->value('photo_path');
    expect($path)->toStartWith('student_photos/')->toEndWith('.webp');
    $bytes=Storage::disk('public')->get($path);
    expect(strlen($bytes))->toBeLessThanOrEqual(40960)->and(getimagesizefromstring($bytes)[2])->toBe(IMAGETYPE_WEBP);
    $this->postJson($this->base.'/'.$id.'/process')->assertOk();
    expect(Storage::disk('public')->allFiles())->toHaveCount(1);
});

it('skips existing photos by default and preserves originals when explicitly replacing',function(){
    Storage::disk('public')->put('student_photos/original.webp','original');
    DB::table('tb_student')->where('id',1)->update(['photo_path'=>'student_photos/original.webp']);
    $skip=($this->batch)();($this->upload)($skip,'SOK DARA_00123.jpg')->assertOk();
    expect(DB::table('student_photo_rows')->where('batch_id',$skip)->value('status'))->toBe('skipped');
    $replace=($this->batch)(true);($this->upload)($replace,'SOK DARA_00123.jpg')->assertOk();
    ($this->confirm)($replace)->assertOk();$this->postJson($this->base.'/'.$replace.'/process')->assertOk();
    expect(DB::table('tb_student')->where('id',1)->value('photo_path'))->not->toBe('student_photos/original.webp');
    Storage::disk('public')->assertExists('student_photos/original.webp');
});

it('keeps both duplicate Student IDs for review and refuses their confirmation',function(){
    $id=($this->batch)();($this->upload)($id,'First_00123.jpg')->assertOk();($this->upload)($id,'Second_00123.jpg')->assertOk();
    expect(DB::table('student_photo_rows')->where('batch_id',$id)->where('status','review')->count())->toBe(2);
    $row=DB::table('student_photo_rows')->where('batch_id',$id)->value('id');
    $this->postJson($this->base.'/'.$id.'/confirm',['selected_ids'=>[$row]])->assertUnprocessable();
});

it('reviews invalid unknown and cross-campus files without revealing another campus student name',function(){
    $id=($this->batch)();
    foreach(['No ID.jpg','Unknown_99999.jpg','Other_00456.jpg'] as $name)($this->upload)($id,$name)->assertOk();
    $this->postJson($this->base.'/'.$id.'/upload',['file'=>UploadedFile::fake()->createWithContent('Invalid_00123.jpg','not an image')])->assertOk();
    $result=$this->getJson($this->base.'/'.$id)->assertOk()->assertJsonPath('summary.review',4);
    expect($result->getContent())->not->toContain('OTHER CAMPUS');
    $this->get($this->base.'/'.$id.'/review')->assertOk()->assertDownload();
});

it('rechecks campus ownership and concurrent photo changes at save time',function(){
    $id=($this->batch)(true);($this->upload)($id,'SOK DARA_00123.jpg')->assertOk();($this->confirm)($id)->assertOk();
    DB::table('tb_student')->where('id',1)->update(['photo_path'=>'student_photos/newer.webp']);
    $this->postJson($this->base.'/'.$id.'/process')->assertOk()->assertJsonPath('summary.review',1);
    expect(DB::table('tb_student')->where('id',1)->value('photo_path'))->toBe('student_photos/newer.webp');
    $id=($this->batch)(true);($this->upload)($id,'SOK DARA_00123.jpg')->assertOk();($this->confirm)($id)->assertOk();
    DB::table('tb_student_enrollment')->where('student_id',1)->update(['campus_id'=>2]);
    $this->postJson($this->base.'/'.$id.'/process')->assertOk()->assertJsonPath('summary.review',1);
    Storage::disk('public')->assertDirectoryEmpty('student_photos');
});

it('denies users without the bulk permission and isolates every preview image and batch by owner',function(){
    $id=($this->batch)();($this->upload)($id,'SOK DARA_00123.jpg')->assertOk();
    $image=$this->getJson($this->base.'/'.$id)->json('rows.0.source_url');
    DB::table('users')->insert(['id'=>2,'name'=>'Another staff','username'=>'another','active_campus_id'=>1]);
    DB::table('access_user_roles')->insert(['user_id'=>2,'role_id'=>1,'campus_id'=>1]);
    $this->actingAs(User::find(2));
    $this->getJson($this->base.'/'.$id)->assertNotFound();$this->get($image)->assertNotFound();
    $permission=DB::table('access_permissions')->where('code','students.enrollment.bulk-photos')->value('id');
    DB::table('access_role_permissions')->where('permission_id',$permission)->delete();
    $this->postJson($this->base,['replace_existing'=>false])->assertForbidden();
});

it('imports ZIP images without extracting archive paths and rejects traversal entries',function(){
    $photo=UploadedFile::fake()->image('SOK DARA_00123.jpg',300,400);
    $path=tempnam(sys_get_temp_dir(),'photo-zip-');
    try{
        $zip=new ZipArchive();$zip->open($path,ZipArchive::OVERWRITE);$zip->addFromString('portraits/SOK DARA_00123.jpg',file_get_contents($photo->getRealPath()));$zip->close();
        $id=($this->batch)();
        $this->postJson($this->base.'/'.$id.'/upload',['file'=>new UploadedFile($path,'photos.zip',null,null,true)])->assertOk();
        expect(DB::table('student_photo_rows')->where('batch_id',$id)->value('student_code'))->toBe('00123');
        $zip=new ZipArchive();$zip->open($path,ZipArchive::OVERWRITE);$zip->addFromString('../SOK DARA_00123.jpg',file_get_contents($photo->getRealPath()));$zip->close();
        $bad=($this->batch)();
        $this->postJson($this->base.'/'.$bad.'/upload',['file'=>new UploadedFile($path,'unsafe.zip',null,null,true)])->assertUnprocessable();
        expect(DB::table('student_photo_rows')->where('batch_id',$bad)->count())->toBe(0);
    }finally{@unlink($path);}
});

it('expires previews and prunes only staged files while preserving saved photos and audit history',function(){
    $id=($this->batch)();($this->upload)($id,'SOK DARA_00123.jpg')->assertOk();($this->confirm)($id)->assertOk();
    $this->postJson($this->base.'/'.$id.'/process')->assertOk();
    $path=DB::table('tb_student')->where('id',1)->value('photo_path');
    DB::table('student_photo_batches')->where('id',$id)->update(['expires_at'=>now()->subMinute()]);
    $this->get($this->base.'/'.$id.'/images/'.DB::table('student_photo_rows')->where('batch_id',$id)->value('id').'/source')->assertGone();
    $this->artisan('student-photos:prune-imports')->assertSuccessful();
    Storage::disk('public')->assertExists($path);Storage::disk('local')->assertDirectoryEmpty('student-photo-imports/'.$id);
    expect(DB::table('student_photo_rows')->where('batch_id',$id)->value('after_path'))->toBe($path);
});

it('retains only selected matches and can resume processing after a lost response',function(){
    DB::table('tb_student')->insert(['id'=>3,'student_id'=>'00789','full_name_en'=>'SECOND STUDENT']);
    DB::table('tb_student_enrollment')->insert(['student_id'=>3,'academic_year_id'=>1,'campus_id'=>1]);
    $id=($this->batch)();($this->upload)($id,'SOK DARA_00123.jpg')->assertOk();($this->upload)($id,'SECOND_00789.jpg')->assertOk();
    $row=DB::table('student_photo_rows')->where('batch_id',$id)->where('student_code','00123')->value('id');
    $this->postJson($this->base.'/'.$id.'/confirm',['selected_ids'=>[$row]])->assertOk()->assertJsonPath('summary.skipped',1);
    $this->getJson($this->base.'/'.$id)->assertJsonPath('batch.status','processing');
    $this->postJson($this->base.'/'.$id.'/process')->assertOk()->assertJsonPath('status','completed');
    expect(DB::table('tb_student')->where('id',3)->value('photo_path'))->toBeNull();
});

it('does not import expired previews or continue after permission revocation',function(){
    $id=($this->batch)();($this->upload)($id,'SOK DARA_00123.jpg')->assertOk();
    DB::table('student_photo_batches')->where('id',$id)->update(['expires_at'=>now()->subMinute()]);
    ($this->confirm)($id)->assertUnprocessable();
    $id=($this->batch)();($this->upload)($id,'SOK DARA_00123.jpg')->assertOk();($this->confirm)($id)->assertOk();
    $permission=DB::table('access_permissions')->where('code','students.enrollment.bulk-photos')->value('id');
    DB::table('access_role_permissions')->where('permission_id',$permission)->delete();
    $this->postJson($this->base.'/'.$id.'/process')->assertForbidden();
    expect(DB::table('tb_student')->where('id',1)->value('photo_path'))->toBeNull();
});

it('rejects ZIP expansion beyond the per-photo limit before staging any files',function(){
    $path=tempnam(sys_get_temp_dir(),'photo-large-zip-');
    try{
        $zip=new ZipArchive();$zip->open($path,ZipArchive::OVERWRITE);$zip->addFromString('Large_00123.jpg',str_repeat('x',10*1024*1024+1));$zip->close();
        $id=($this->batch)();
        $this->postJson($this->base.'/'.$id.'/upload',['file'=>new UploadedFile($path,'oversized.zip',null,null,true)])->assertUnprocessable();
        expect(DB::table('student_photo_rows')->where('batch_id',$id)->count())->toBe(0);
        Storage::disk('local')->assertDirectoryEmpty('student-photo-imports/'.$id);
    }finally{@unlink($path);}
});
