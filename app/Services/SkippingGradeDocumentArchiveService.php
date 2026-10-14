<?php

namespace App\Services;

use App\Jobs\ProcessSkippingDocumentArchive;
use App\Models\AcademicYear;
use App\Models\SkippingDocumentArchive as Archive;
use App\Models\SkippingDocumentArchiveFile as ArchiveFile;
use App\Models\SkippingDocumentArchivePart as Part;
use App\Models\StudentSkippingGrade;
use App\Models\User;
use App\Support\StudentSkippingGradePermissions as Permissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

class SkippingGradeDocumentArchiveService
{
    public const PART_BYTES=200*1024*1024;

    public function event(Archive $archive, string $action, ?int $userId, ?string $details=null): void
    {
        DB::table('skipping_document_archive_events')->insert(['archive_id'=>$archive->id,'user_id'=>$userId,'action'=>$action,'details'=>$details,'created_at'=>now()]);
    }

    public function queue(string $operation, int $id, int $userId, ?string $uploadPath=null): void
    {
        DB::afterCommit(function () use ($operation,$id,$userId,$uploadPath) {
            $job=new ProcessSkippingDocumentArchive($operation,$id,$userId,$uploadPath);
            try { \Illuminate\Support\Facades\Bus::dispatch($job); }
            catch (\Throwable $e) { report($e); $job->failed($e); }
        });
    }

    public function actor(int $userId, string $action): User
    {
        $user=User::findOrFail($userId);
        abort_unless((int)$user->status===1 && Permissions::allows($user,$action),403);
        return $user;
    }

    public function retention(): array
    {
        $years=AcademicYear::regular()->get();
        $current=$years->where('lifecycle_status','started')->filter(fn($y)=>$this->yearStart($y->academic_year)!==null)->sortByDesc('academic_year')->first();
        return ['current'=>$current?->academic_year,'earliest_kept'=>$current?(int)substr($current->academic_year,0,4)-1:null];
    }

    public function canDeleteYear(string $year): bool
    {
        $retention=$this->retention();
        $start=$this->yearStart($year);
        return $retention['earliest_kept']!==null && $start!==null && $start<$retention['earliest_kept'];
    }

    private function yearStart(string $year): ?int
    {
        return preg_match('/^(\d{4})-(\d{4})$/',$year,$match) && (int)$match[2]===(int)$match[1]+1?(int)$match[1]:null;
    }

    private function unchangedYear(Archive $archive): void
    {
        if (AcademicYear::withTrashed()->whereKey($archive->academic_year_id)->value('academic_year')!==$archive->academic_year) {
            $this->fail('The academic year changed since this backup. Create a new backup before deleting documents.');
        }
    }

    private function fail(string $message): never { throw ValidationException::withMessages(['archive'=>$message]); }

    private function localPath(string $path): string
    {
        if ((!str_starts_with($path,'grade-skipping/attachments/') && !str_starts_with($path,'grade-skipping/signed-requests/'))
            || str_contains($path,'..') || str_contains($path,'\\') || str_contains($path,':')) throw new RuntimeException('Invalid document storage path.');
        return Storage::disk('local')->path($path);
    }

    public function create(User $user, int $yearId, ?int $campusId): Archive
    {
        $this->actor($user->id,'archive-backup');
        if (!class_exists(ZipArchive::class)) $this->fail('Enable the PHP ZIP extension before creating a backup.');
        return DB::transaction(function () use ($user,$yearId,$campusId) {
            $year=AcademicYear::regular()->whereKey($yearId)->lockForUpdate()->firstOrFail();
            if (Archive::where('academic_year_id',$yearId)->whereIn('status',['building','deleting'])->exists()) $this->fail('An archive operation for this year is already running.');
            $archive=Archive::create(['reference'=>(string)Str::uuid(),'academic_year_id'=>$yearId,'academic_year'=>$year->academic_year,'campus_id'=>$campusId,'created_by'=>$user->id]);
            $this->event($archive,'backup_requested',$user->id);
            $this->queue('prepare',$archive->id,$user->id);
            return $archive;
        });
    }

    public function prepare(Archive $archive): void
    {
        DB::transaction(function () use ($archive) {
            $archive=Archive::whereKey($archive->id)->lockForUpdate()->firstOrFail();
            if ($archive->parts()->exists()) return;
            $yearId=$archive->academic_year_id; $campusId=$archive->campus_id;
            $part=null; $bytes=0; $number=0;
            StudentSkippingGrade::where('target_academic_year_id',$yearId)->whereIn('status',['approved','rejected'])
                ->when($campusId,fn($q)=>$q->where('campus_id',$campusId))->with('campus')->chunkById(100,function ($records) use ($archive,&$part,&$bytes,&$number) {
                    foreach ($records as $record) {
                        $documents=[];
                        foreach ($record->supporting_documents??[] as $index=>$document) if (empty($document['archived_at'])) $documents[]=['kind'=>'supporting','index'=>$index,'path'=>$document['path'],'name'=>$document['name']];
                        if ($record->signed_request_path && !$record->signed_request_archived_at) $documents[]=['kind'=>'signed','index'=>null,'path'=>$record->signed_request_path,'name'=>'signed-request.'.pathinfo($record->signed_request_path,PATHINFO_EXTENSION)];
                        $storage=app(SkippingGradeDocumentStorage::class);
                        $folder=Str::limit($storage->segment($archive->academic_year),40,'').'/'.$storage->segment($record->reference_number?:'Request-'.$record->id);
                        foreach ($documents as $document) {
                            $source=$this->localPath($document['path']);
                            if (!is_file($source)) $this->fail('Request #'.$record->id.' has a missing document: '.$document['name'].'. Restore or correct it before backing up.');
                            $size=filesize($source);
                            if ($size>static::PART_BYTES) $this->fail('An existing document exceeds the archive part limit.');
                            if (!$part || $bytes+$size>static::PART_BYTES || $part->files()->count()>=1000) {
                                $number++; $bytes=0;
                                $part=Part::create(['archive_id'=>$archive->id,'number'=>$number,'path'=>'grade-skipping/archives/'.$archive->reference.'/part-'.$number.'.zip']);
                            }
                            $student=$record->student_snapshot??[];
                            $file=ArchiveFile::create(['archive_id'=>$archive->id,'part_id'=>$part->id,'request_id'=>$record->id,
                                'kind'=>$document['kind'],'document_index'=>$document['index'],'source_path'=>$document['path'],
                                'original_name'=>$document['name'],'entry_path'=>'pending','bytes'=>$size,
                                'student'=>['student_id'=>$student['student_id']??null,'name_kh'=>$student['name_kh']??null,'name_en'=>$student['name_en']??null,
                                    'campus'=>$record->campus?->campus_name_en,'approval_number'=>$record->reference_number,'request_id'=>$record->id],
                            ]);
                            $name=Str::limit($storage->segment(pathinfo($document['name'],PATHINFO_FILENAME)),60,'');
                            $extension=strtolower(pathinfo($document['name'],PATHINFO_EXTENSION));
                            $file->update(['entry_path'=>$folder.'/'.$file->id.'-'.$name.'.'.preg_replace('/[^a-z0-9]/','',$extension)]);
                            $bytes+=$size;
                        }
                    }
                });
            if (!$part) $this->fail('There are no online documents in completed requests for these filters. Draft and awaiting-approval requests are excluded.');
            foreach ($archive->parts as $p) $this->queue('build',$p->id,$archive->created_by);
        });
    }

    public function build(Part $part): void
    {
        if ($part->status==='verified') return;
        $part->update(['status'=>'building','error'=>null]);
        $disk=Storage::disk('local'); $disk->makeDirectory(dirname($part->path));
        $path=$disk->path($part->path); $temp=$path.'.building';
        $zip=new ZipArchive;
        if ($zip->open($temp,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) throw new RuntimeException('Unable to create the backup ZIP. Check available disk space.');
        try {
            $manifest=[];
            foreach ($part->files()->orderBy('id')->cursor() as $file) {
                $source=$this->localPath($file->source_path);
                if (!is_file($source) || filesize($source)!==(int)$file->bytes) throw new RuntimeException('A source file changed or is missing. No documents were deleted.');
                $hash=hash_file('sha256',$source);
                if (!$hash || !$zip->addFile($source,$file->entry_path) || !$zip->setCompressionName($file->entry_path,ZipArchive::CM_STORE)) throw new RuntimeException('Unable to add a source file to the backup.');
                $file->update(['sha256'=>$hash]);
                $manifest[]=['file_id'=>$file->id,'path'=>$file->entry_path,'original_name'=>$file->original_name,'kind'=>$file->kind,'bytes'=>$file->bytes,'sha256'=>$hash,'student'=>$file->student];
            }
            $json=json_encode(['format'=>1,'archive'=>$part->archive->reference,'academic_year'=>$part->archive->academic_year,'part'=>$part->number,'files'=>$manifest],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
            $students=[['Student ID','Khmer Name','English Name','Campus','Approval Number','Request Number']];
            $documents=[['Document','Folder / File','Bytes','SHA-256','Student ID','Request Number']]; $seen=[];
            foreach ($manifest as $entry) {
                $student=$entry['student'];
                if (!isset($seen[$student['request_id']])) {
                    $students[]=[$student['student_id'],$student['name_kh'],$student['name_en'],$student['campus'],$student['approval_number'],$student['request_id']];
                    $seen[$student['request_id']]=true;
                }
                $documents[]=[$entry['original_name'],$entry['path'],$entry['bytes'],$entry['sha256'],$student['student_id'],$student['request_id']];
            }
            if (!$zip->addFromString('manifest.json',$json) || !$zip->addFromString('students.csv',$this->csv($students))
                || !$zip->addFromString('documents.csv',$this->csv($documents))
                || !$zip->addFromString('README.txt',"Grade Skipping supporting document backup\nArchive: ".$part->archive->reference."\nPart: ".$part->number."\nKeep ALL ZIP parts, unchanged, outside the school server.\nRestore by uploading each original ZIP on the Supporting Document Archive page.\nThe server database contains approval history and restore metadata. Include it in your normal database backups.\n")
                || !$zip->close()) throw new RuntimeException('Unable to finish the backup ZIP. Check available disk space.');
            $zip=null;
            $this->verifyFile($part,$temp);
            if (!rename($temp,$path)) throw new RuntimeException('Unable to save the verified ZIP.');
            $part->update(['sha256'=>hash_file('sha256',$path),'bytes'=>filesize($path),'status'=>'verified','verified_at'=>now(),'error'=>null]);
            $this->finishVerification($part->archive);
        } finally {
            if ($zip) $zip->close();
            if (is_file($temp)) unlink($temp);
        }
    }

    private function streamHash($stream): string
    {
        if (!is_resource($stream)) throw new RuntimeException('Unable to read a backup entry.');
        try { $hash=hash_init('sha256'); if (hash_update_stream($hash,$stream)===false) throw new RuntimeException('Unable to hash a backup entry.'); return hash_final($hash); }
        finally { fclose($stream); }
    }

    private function csv(array $rows): string
    {
        $stream=fopen('php://temp','w+'); fwrite($stream,"\xEF\xBB\xBF");
        foreach ($rows as $row) {
            $row=array_map(function ($value) {
                $value=(string)$value;
                return preg_match('/^[=+@\-\t\r\n]/u',ltrim($value,' '))?"'".$value:$value;
            },$row);
            fputcsv($stream,$row,',','"','');
        }
        rewind($stream); $csv=stream_get_contents($stream); fclose($stream); return $csv;
    }

    public function verifyFile(Part $part, string $path): void
    {
        if (!is_file($path)) throw new RuntimeException('The backup ZIP is missing.');
        if ($part->sha256 && !hash_equals($part->sha256,hash_file('sha256',$path))) throw new RuntimeException('The ZIP checksum does not match the original backup.');
        $zip=new ZipArchive;
        if ($zip->open($path,ZipArchive::RDONLY)!==true) throw new RuntimeException('The backup ZIP cannot be read.');
        try {
            $files=$part->files()->orderBy('id')->get();
            if ($zip->numFiles!==$files->count()+4) throw new RuntimeException('The backup has missing or unexpected entries.');
            $manifest=json_decode($zip->getFromName('manifest.json')?:'',true,512,JSON_THROW_ON_ERROR);
            if (($manifest['archive']??null)!==$part->archive->reference || (int)($manifest['part']??0)!==(int)$part->number || count($manifest['files']??[])!==$files->count()) throw new RuntimeException('The backup manifest does not match this archive.');
            foreach ($files as $file) {
                $stat=$zip->statName($file->entry_path);
                if (!$stat || (int)$stat['size']!==(int)$file->bytes || !$file->sha256
                    || !hash_equals($file->sha256,$this->streamHash($zip->getStream($file->entry_path)))) throw new RuntimeException('A backed-up document failed checksum verification.');
            }
        } finally { $zip->close(); }
    }

    public function verify(Part $part): void
    {
        $this->verifyFile($part,Storage::disk('local')->path($part->path));
        $part->update(['status'=>'verified','verified_at'=>now(),'error'=>null]);
        $this->finishVerification($part->archive);
    }

    private function finishVerification(Archive $archive): void
    {
        DB::transaction(function () use ($archive) {
            $archive=Archive::whereKey($archive->id)->lockForUpdate()->firstOrFail();
            if (!$archive->parts()->where('status','!=','verified')->exists()) {
                $archive->update(['status'=>'ready','error'=>null]);
                $this->event($archive,'backup_verified',$archive->created_by);
            }
        });
    }

    public function requestVerification(Archive $archive, User $user): void
    {
        $this->actor($user->id,'archive-backup');
        DB::transaction(function () use ($archive,$user) {
            $archive=Archive::whereKey($archive->id)->lockForUpdate()->firstOrFail();
            if (!in_array($archive->status,['ready','failed'],true)) $this->fail('This archive cannot be verified in its current state.');
            $archive->update(['status'=>'building','error'=>null]);
            if (!$archive->parts()->exists()) $this->queue('prepare',$archive->id,$user->id);
            foreach ($archive->parts as $part) {
                $operation=$part->sha256?'verify':'build';
                $part->update(['status'=>'queued','verified_at'=>null,'error'=>null]);
                $this->queue($operation,$part->id,$user->id);
            }
            $this->event($archive,'verification_requested',$user->id);
        });
    }

    public function requestDeletion(Archive $archive, User $user): void
    {
        $this->actor($user->id,'archive-delete');
        DB::transaction(function () use ($archive,$user) {
            $archive=Archive::whereKey($archive->id)->lockForUpdate()->firstOrFail();
            $this->unchangedYear($archive);
            if (!$this->canDeleteYear($archive->academic_year)) $this->fail('The current and previous academic years are protected. A started regular academic year must be configured.');
            if (!in_array($archive->status,['ready','delete_failed'],true) || $archive->parts()->where(fn($q)=>$q->whereNull('verified_at')->orWhereNull('downloaded_at')->orWhere('status','!=','verified'))->exists()) $this->fail('Verify and download every ZIP part before deleting documents.');
            $archive->update(['status'=>'deleting','confirmed_by'=>$user->id,'confirmed_at'=>now(),'error'=>null]);
            $this->event($archive,'external_backup_confirmed',$user->id);
            $this->queue('delete',$archive->id,$user->id);
        });
    }

    public function deleteDocuments(Archive $archive, int $userId): void
    {
        $this->actor($userId,'archive-delete');
        $this->unchangedYear($archive);
        if ($archive->status!=='deleting' || !$archive->confirmed_at || !$this->canDeleteYear($archive->academic_year)) throw new RuntimeException('This archive is not eligible for deletion.');
        foreach ($archive->parts as $part) {
            if (!$part->downloaded_at || !$part->verified_at) throw new RuntimeException('Every part must be verified and downloaded.');
            $this->verifyFile($part,Storage::disk('local')->path($part->path));
        }
        // Verify every live source before removing the first file.
        foreach ($archive->files()->whereNull('deleted_at')->cursor() as $file) {
            $record=StudentSkippingGrade::findOrFail($file->request_id);
            $this->assertRecord($archive,$record,$file);
            $source=$this->localPath($file->source_path);
            if (!is_file($source) || !hash_equals($file->sha256,hash_file('sha256',$source))) throw new RuntimeException('A live document changed or is missing. Deletion was stopped.');
        }
        foreach ($archive->files()->orderBy('id')->cursor() as $file) {
            DB::transaction(function () use ($archive,$file) {
                $record=StudentSkippingGrade::whereKey($file->request_id)->lockForUpdate()->firstOrFail();
                $this->assertRecord($archive,$record,$file);
                if ($file->deleted_at) return;
                // Commit recoverable metadata before touching the live bytes.
                // A retry or restore can then recover even after a process crash.
                if ($file->kind==='signed') $record->update(['signed_request_archived_at'=>now(),'signed_request_archive_id'=>$archive->id]);
                else {
                    $documents=$record->supporting_documents;
                    $documents[$file->document_index]['archived_at']=now()->toIso8601String();
                    $documents[$file->document_index]['archive_id']=$archive->id;
                    $documents[$file->document_index]['archive_reference']=$archive->reference;
                    $record->update(['supporting_documents'=>$documents]);
                }
                $file->update(['deleted_at'=>now()]);
            });
            $source=$this->localPath($file->source_path);
            if (is_file($source) && (!hash_equals($file->sha256,hash_file('sha256',$source)) || !unlink($source))) throw new RuntimeException('Unable to remove a source document.');
        }
        // The downloaded external copies become the backup. Remove server ZIPs
        // too, so archiving actually releases server disk space.
        $archive->update(['status'=>'deleted','error'=>null]);
        $this->event($archive,'documents_deleted',$userId,'Online documents and temporary server ZIPs removed; database records and checksums retained.');
        foreach ($archive->parts as $part) if (Storage::disk('local')->exists($part->path) && !Storage::disk('local')->delete($part->path)) {
            $archive->update(['error'=>'Documents were archived, but a temporary server ZIP could not be removed. Ask the administrator to remove the ZIP after checking the external backup.']);
            $this->event($archive,'server_zip_cleanup_failed',$userId,'Part '.$part->number);
        }
    }

    private function assertRecord(Archive $archive, StudentSkippingGrade $record, ArchiveFile $file): void
    {
        if (!in_array($record->status,['approved','rejected'],true) || (int)$record->target_academic_year_id!==(int)$archive->academic_year_id
            || ($archive->campus_id && (int)$record->campus_id!==(int)$archive->campus_id)) throw new RuntimeException('A request no longer matches the completed archive scope.');
        $path=$file->kind==='signed'?$record->signed_request_path:($record->supporting_documents[$file->document_index]['path']??null);
        if ($path!==$file->source_path) throw new RuntimeException('A request document changed since the backup was created.');
        $owner=$file->kind==='signed'?$record->signed_request_archive_id:($record->supporting_documents[$file->document_index]['archive_id']??null);
        if ($owner && (int)$owner!==(int)$archive->id) throw new RuntimeException('A document belongs to another archive. Restore it there first.');
    }

    public function restore(Part $part, UploadedFile $upload, User $user): void
    {
        $this->actor($user->id,'archive-restore');
        if (!in_array($part->archive->status,['deleted','partially_restored','restored','delete_failed','restoring','restore_failed'],true)) $this->fail('Only archived documents can be restored.');
        $this->verifyFile($part,$upload->getRealPath());
        $zip=new ZipArchive;
        if ($zip->open($upload->getRealPath(),ZipArchive::RDONLY)!==true) $this->fail('Unable to read this ZIP.');
        try {
            foreach ($part->files()->whereNotNull('deleted_at')->whereNull('restored_at')->orderBy('id')->get() as $file) {
                $destination=$this->localPath($file->source_path); $created=false;
                $staging=$file->source_path.'.restore-'.Str::uuid();
                try {
                    DB::transaction(function () use ($file,$part,$zip,$destination,$staging,&$created) {
                        $record=StudentSkippingGrade::whereKey($file->request_id)->lockForUpdate()->firstOrFail();
                        $locked=ArchiveFile::whereKey($file->id)->lockForUpdate()->firstOrFail();
                        if ($locked->restored_at) return;
                        $this->assertRecord($part->archive,$record,$file);
                        if (is_file($destination)) {
                            if (!hash_equals($file->sha256,hash_file('sha256',$destination))) throw new RuntimeException('Restore would overwrite a different existing file.');
                        } else {
                            $stream=$zip->getStream($file->entry_path);
                            if (!is_resource($stream)) throw new RuntimeException('Unable to read the archived document.');
                            try { if (!Storage::disk('local')->put($staging,$stream)) throw new RuntimeException('Unable to restore the document. Check available disk space.'); }
                            finally { fclose($stream); }
                            $temporary=Storage::disk('local')->path($staging);
                            if (!hash_equals($file->sha256,hash_file('sha256',$temporary))) throw new RuntimeException('Restored document checksum failed.');
                            if (!rename($temporary,$destination)) throw new RuntimeException('Unable to save the restored document.');
                            $created=true;
                        }
                        if ($file->kind==='signed') $record->update(['signed_request_archived_at'=>null,'signed_request_archive_id'=>null]);
                        else {
                            $documents=$record->supporting_documents;
                            unset($documents[$file->document_index]['archived_at'],$documents[$file->document_index]['archive_id'],$documents[$file->document_index]['archive_reference']);
                            $record->update(['supporting_documents'=>$documents]);
                        }
                        $locked->update(['restored_at'=>now()]);
                    });
                } catch (\Throwable $e) { if ($created && is_file($destination)) unlink($destination); throw $e; }
                finally { Storage::disk('local')->delete($staging); }
            }
            $part->update(['restored_at'=>now()]);
            $archive=$part->archive;
            $remaining=$archive->files()->whereNotNull('deleted_at')->whereNull('restored_at')->exists();
            $archive->update(['status'=>$remaining?'partially_restored':'restored','error'=>null]);
            $this->event($archive,'part_restored',$user->id,'Part '.$part->number);
        } finally { $zip->close(); }
    }
}
