<?php

namespace App\Services;

use App\Models\{Student, User};
use App\Support\StudentEnrollmentPermissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentPhotoImport
{
    public const MAX_ROWS = 500;
    private array $access = [];

    public function access(User $user): StudentEnrollmentPermissions
    {
        $access = $this->access[$user->id] ??= new StudentEnrollmentPermissions($user);
        abort_unless($access->allows('bulk-photos'), 403);
        return $access;
    }

    public function batch(User $user, string $id, bool $lock = false): object
    {
        $this->access($user);
        $query = DB::table('student_photo_batches')->where('id',$id)->where('user_id',$user->id);
        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    private function live(object $batch): void
    {
        abort_if(now()->greaterThan($batch->expires_at), 422, 'This preview expired. Create a new photo batch.');
    }

    public function create(User $user, bool $replace): string
    {
        $this->access($user); $id=(string)Str::uuid();
        DB::table('student_photo_batches')->insert(['id'=>$id,'user_id'=>$user->id,'replace_existing'=>$replace,
            'status'=>'preview','expires_at'=>now()->addDay(),'created_at'=>now(),'updated_at'=>now()]);
        return $id;
    }

    public function upload(User $user, string $id, UploadedFile $file): void
    {
        $created=[];
        try {
            DB::transaction(function () use ($user,$id,$file,&$created) {
                $batch=$this->batch($user,$id,true); $this->live($batch);
                abort_unless($batch->status==='preview',422,'This batch is already confirmed.');
                $entries=[]; $zip=null;
                try {
                    if (strtolower($file->getClientOriginalExtension())==='zip') {
                        $zip=new \ZipArchive();
                        if ($zip->open($file->getRealPath())!==true) $this->fail('The ZIP archive cannot be read.');
                        if ($zip->numFiles>1000) $this->fail('The archive contains too many entries.');
                        $expanded=0;
                        for ($i=0;$i<$zip->numFiles;$i++) {
                            $stat=$zip->statIndex($i); $name=str_replace('\\','/',$stat['name']);
                            if (str_ends_with($name,'/') || str_starts_with($name,'__MACOSX/') || str_starts_with(basename($name),'._')) continue;
                            if (preg_match('~(^/|^[A-Za-z]:|(?:^|/)\.\.(?:/|$))~',$name)) $this->fail('Unsafe ZIP entry name. Recreate the archive from your photo files.');
                            $expanded+=(int)$stat['size'];
                            if ($stat['size']>10*1024*1024 || $expanded>250*1024*1024) $this->fail('ZIP photos exceed the limits: 10 MB per photo and 250 MB uncompressed.');
                            $entries[]=['name'=>basename($name),'index'=>$i];
                        }
                    } else {
                        if ($file->getSize()>10*1024*1024) $this->fail('Each photo must be no larger than 10 MB.');
                        $entries[]=['name'=>$file->getClientOriginalName(),'index'=>null];
                    }
                    if (!$entries) $this->fail('No photo files were found.');
                    if (count($entries)+DB::table('student_photo_rows')->where('batch_id',$id)->count()>self::MAX_ROWS) $this->fail('Use at most 500 photos per batch.');
                    foreach ($entries as $entry) {
                        $bytes=$zip ? $zip->getFromIndex($entry['index'],10*1024*1024+1) : file_get_contents($file->getRealPath());
                        $this->stage($user,$batch,$entry['name'],$bytes===false?'':$bytes,$created);
                    }
                } finally { if ($zip) $zip->close(); }
            });
        } catch (\Throwable $e) { Storage::disk('local')->delete($created); throw $e; }
    }

    private function stage(User $user, object $batch, string $name, string $bytes, array &$created): void
    {
        $name=mb_substr(basename(str_replace('\\','/',$name)),0,255);
        $code=null; $student=null; $source=null; $reason=null; $status='ready';
        $extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        $size=@getimagesizefromstring($bytes);
        if (!in_array($extension,['jpg','jpeg','png','webp'],true) || !$size || !in_array($size[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true)
            || strlen($bytes)>10*1024*1024 || $size[0]*$size[1]>24000000) {
            $reason='Use a readable JPG, PNG or WebP photo up to 10 MB and 24 megapixels.';
        } else {
            $source='student-photo-imports/'.$batch->id.'/'.Str::uuid().'.'.$extension;
            $created[]=$source;
            if (!Storage::disk('local')->put($source,$bytes)) $this->fail('The uploaded photo could not be staged.');
            if (!preg_match('/^.+_([^_]+)$/u',pathinfo($name,PATHINFO_FILENAME),$match)) $reason='Filename must be Student Name_Student ID.jpg.';
            else {
                $code=trim($match[1]);
                $matches=Student::where('student_id',$code)->limit(2)->get();
                if ($matches->count()!==1) $reason=$matches->isEmpty()?'Student ID was not found.':'Student ID matches more than one student. Review the student records.';
                else {
                    $student=$matches->first();
                    if (!$this->access($user)->canAccessStudentCampus($student->id)) {
                        $student=null; $reason='Student is outside your permitted campus ownership.';
                    } elseif ($student->photo_path && !$batch->replace_existing) { $status='skipped'; $reason='Existing photo kept (fill missing photos only).'; }
                }
            }
        }
        if ($reason && $status!=='skipped') $status='review';
        if ($code && DB::table('student_photo_rows')->where('batch_id',$batch->id)->where('student_code',$code)->exists()) {
            DB::table('student_photo_rows')->where('batch_id',$batch->id)->where('student_code',$code)->update(['status'=>'review','reason'=>'More than one photo uses this Student ID. Upload one photo per student.','updated_at'=>now()]);
            $status='review'; $reason='More than one photo uses this Student ID. Upload one photo per student.';
        }
        DB::table('student_photo_rows')->insert(['batch_id'=>$batch->id,'filename'=>$name,'student_code'=>$code,
            'student_id'=>$student?->id,'student_name'=>$student?->full_name_en ?: $student?->full_name_kh,
            'source_path'=>$source,'before_path'=>$student?->photo_path,'status'=>$status,'reason'=>$reason,'created_at'=>now(),'updated_at'=>now()]);
    }

    public function confirm(User $user, string $id, array $selected): void
    {
        DB::transaction(function () use ($user,$id,$selected) {
            $batch=$this->batch($user,$id,true); $this->live($batch);
            abort_unless($batch->status==='preview',422,'This batch is already confirmed.');
            $ready=DB::table('student_photo_rows')->where('batch_id',$id)->where('status','ready');
            if (!$selected || (clone $ready)->whereIn('id',$selected)->count()!==count(array_unique($selected))) $this->fail('Select valid matched photos to import.');
            (clone $ready)->whereNotIn('id',$selected)->update(['status'=>'skipped','reason'=>'Not selected for import.','updated_at'=>now()]);
            DB::table('student_photo_batches')->where('id',$id)->update(['status'=>'processing','updated_at'=>now()]);
        });
    }

    public function process(User $user, string $id): void
    {
        $newPath=null;
        try {
            DB::transaction(function () use ($user,$id,&$newPath) {
                $batch=$this->batch($user,$id,true);
                if ($batch->status==='completed') return;
                $this->live($batch); abort_unless($batch->status==='processing',422,'Review and confirm this batch first.');
                $row=DB::table('student_photo_rows')->where('batch_id',$id)->where('status','ready')->orderBy('id')->lockForUpdate()->first();
                if ($row) {
                    $student=Student::whereKey($row->student_id)->lockForUpdate()->first();
                    $reason=null;
                    if (!$student || !$this->access($user)->canAccessStudentCampus($student->id)) $reason='Student is no longer available within your campus permissions.';
                    elseif (($student->photo_path ?: null)!==($row->before_path ?: null)) $reason='Student photo changed after preview. Review a new batch to avoid overwriting it.';
                    elseif (!$batch->replace_existing && $student->photo_path) $reason='Student already has a photo.';
                    if (!$reason) {
                        try {
                            $file=new UploadedFile(Storage::disk('local')->path($row->source_path),$row->filename,null,null,true);
                            $bytes=app(StudentPhotoProcessor::class)->webp($file);
                            $newPath='student_photos/'.Str::uuid().'.webp';
                            if (!Storage::disk('public')->put($newPath,$bytes)) throw new \RuntimeException('Photo storage failed.');
                            $student->update(['photo_path'=>$newPath]);
                        } catch (ValidationException $e) { $reason=collect($e->errors())->flatten()->first(); }
                    }
                    DB::table('student_photo_rows')->where('id',$row->id)->update(['status'=>$reason?'review':'updated','reason'=>$reason,'after_path'=>$reason?null:$newPath,'updated_at'=>now()]);
                }
                if (!DB::table('student_photo_rows')->where('batch_id',$id)->where('status','ready')->exists()) DB::table('student_photo_batches')->where('id',$id)->update(['status'=>'completed','updated_at'=>now()]);
            });
        } catch (\Throwable $e) { if ($newPath) Storage::disk('public')->delete($newPath); throw $e; }
        // Existing photos are retained for audit/recovery; never delete shared image paths here.
    }

    private function fail(string $message): never { throw ValidationException::withMessages(['file'=>$message]); }
}
