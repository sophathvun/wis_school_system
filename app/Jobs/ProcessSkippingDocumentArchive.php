<?php

namespace App\Jobs;

use App\Models\SkippingDocumentArchive;
use App\Models\SkippingDocumentArchivePart;
use App\Services\SkippingGradeDocumentArchiveService;
use App\Services\SkippingGradeDocumentStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessSkippingDocumentArchive implements ShouldQueue
{
    use Queueable;
    public int $timeout=1800;
    public int $tries=1;
    public bool $failOnTimeout=true;

    public function __construct(public string $operation, public int $id, public int $userId, public ?string $uploadPath=null)
    {
        $this->onConnection('document-archives');
        $this->onQueue('document-archives');
    }

    public function handle(SkippingGradeDocumentArchiveService $service): void
    {
        $service->actor($this->userId,match($this->operation) {'delete'=>'archive-delete','restore'=>'archive-restore','organize'=>'view',default=>'archive-backup'});
        if ($this->operation==='organize') { app(SkippingGradeDocumentStorage::class)->organize($this->id); return; }
        if ($this->operation==='prepare') { $service->prepare(SkippingDocumentArchive::findOrFail($this->id)); return; }
        if ($this->operation==='delete') { $service->deleteDocuments(SkippingDocumentArchive::findOrFail($this->id),$this->userId); return; }
        $part=SkippingDocumentArchivePart::findOrFail($this->id);
        if ($this->operation==='restore') {
            try {
                $path=Storage::disk('local')->path($this->uploadPath);
                $service->restore($part,new UploadedFile($path,'backup.zip','application/zip',null,true),$service->actor($this->userId,'archive-restore'));
            } finally { Storage::disk('local')->delete($this->uploadPath); }
        } elseif ($this->operation==='build') $service->build($part);
        else $service->verify($part);
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->uploadPath) Storage::disk('local')->delete($this->uploadPath);
        if ($this->operation==='organize') return;
        $message='The operation failed. '.$exception?->getMessage();
        if (in_array($this->operation,['delete','prepare'],true)) $archive=SkippingDocumentArchive::find($this->id);
        else {
            $part=SkippingDocumentArchivePart::find($this->id);
            if (!$part) return;
            if ($this->operation!=='restore') $part->update(['status'=>'failed','verified_at'=>null,'error'=>$message]);
            $archive=$part->archive;
        }
        if (!$archive) return;
        $archive->update(['status'=>match($this->operation) {'delete'=>'delete_failed','restore'=>'restore_failed',default=>'failed'},'error'=>$message]);
        app(SkippingGradeDocumentArchiveService::class)->event($archive,$this->operation.'_failed',$this->userId,$message);
    }
}
