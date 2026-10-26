<?php

namespace App\Services;

use App\Models\StudentSkippingGrade;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SkippingGradeDocumentStorage
{
    public function __construct(private readonly GradeSkippingPdfCompressor $pdfs, private readonly GradeSkippingImageOptimizer $images) {}

    public function directory(StudentSkippingGrade $record): string
    {
        $year=$record->targetAcademicYear?->academic_year??$record->student_snapshot['target_academic_year']??'Unknown-Year';
        return 'grade-skipping/attachments/'.$this->segment($year).'/'.$this->segment($record->reference_number?:'Request-'.$record->id);
    }

    public function segment(string $value): string
    {
        return Str::limit(preg_replace('/[^\pL\pN_-]+/u','-',trim($value))?:'Unknown',80,'');
    }

    public function storeUpload(StudentSkippingGrade $record, UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $prepared = in_array($extension, ['jpg','jpeg','png','webp'], true) ? $this->images->prepare($file) : $this->pdfs->prepare($file);
        $extension = $prepared['extension']??$extension;
        if (!in_array($extension, ['pdf','jpg','jpeg','png','webp'], true)) $extension = 'bin';
        $path = $this->directory($record).'/'.Str::uuid().'.'.$extension;
        $stream = @fopen($prepared['path'], 'rb');
        if (!$stream) {
            $this->cleanup($prepared);
            throw new RuntimeException('Unable to read the uploaded document.');
        }
        try {
            if (!Storage::disk('local')->writeStream($path, $stream)) throw new RuntimeException('Unable to save the document.');
        } finally {
            fclose($stream);
            $this->cleanup($prepared);
        }
        $size = (int) Storage::disk('local')->size($path);
        $originalName=basename(str_replace('\\','/',$file->getClientOriginalName()));
        $name=($prepared['optimization_type']??null)==='image'?(pathinfo($originalName,PATHINFO_FILENAME).'.webp'):$originalName;
        return [
            'path' => $path,
            'name' => $name,
            'mime' => $prepared['mime']??$file->getMimeType(),
            'size' => $size,
            'original_size' => (int) $prepared['original_size'],
            'compressed' => (bool) $prepared['compressed'],
            'optimization_type' => $prepared['optimization_type']??(($prepared['compressed']??false)?'pdf':null),
        ];
    }

    private function cleanup(array $prepared): void
    {
        if (!empty($prepared['temporary_path'])) Storage::disk('local')->delete($prepared['temporary_path']);
    }

    public function organize(int $id): void
    {
        $copies=[];
        try {
            DB::transaction(function () use ($id,&$copies) {
                $record=StudentSkippingGrade::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($record->status!=='approved' || !$record->reference_number) return;
                if (\App\Models\SkippingDocumentArchive::whereIn('status',['building','ready','deleting','delete_failed','restoring'])
                    ->whereHas('files',fn($q)=>$q->where('request_id',$record->id))->exists()) return;
                $disk=Storage::disk('local'); $old=[]; $documents=$record->supporting_documents??[];
                $directory=$this->directory($record);
                $copy=function (string $source) use ($disk,$directory,&$copies,&$old): string {
                    if (dirname($source)===$directory) return $source;
                    if (!$disk->exists($source)) throw new RuntimeException('A supporting document is missing; folder organization was stopped.');
                    $target=$directory.'/'.Str::uuid().'.'.pathinfo($source,PATHINFO_EXTENSION);
                    $copies[]=$target;
                    if (!$disk->copy($source,$target)) throw new RuntimeException('Unable to copy the document.');
                    $sourceHash=hash_file('sha256',$disk->path($source)); $targetHash=hash_file('sha256',$disk->path($target));
                    if (!$sourceHash || !$targetHash || !hash_equals($sourceHash,$targetHash)) throw new RuntimeException('Unable to verify the document copy.');
                    $old[]=$source;
                    return $target;
                };
                foreach ($documents as &$document) if (empty($document['archived_at'])) $document['path']=$copy($document['path']);
                unset($document);
                $values=['supporting_documents'=>$documents];
                if ($record->signed_request_path && !$record->signed_request_archived_at) $values['signed_request_path']=$copy($record->signed_request_path);
                $record->update($values);
                DB::afterCommit(function () use ($disk,$old) {
                    // Once the pointers commit, cleanup must never remove the
                    // verified new copies, even if deleting an old copy fails.
                    try { if ($old && !$disk->delete($old)) report(new RuntimeException('An old Grade Skipping document copy could not be removed.')); }
                    catch (\Throwable $e) { report($e); }
                });
            });
        } catch (\Throwable $e) { Storage::disk('local')->delete($copies); throw $e; }
    }
}
