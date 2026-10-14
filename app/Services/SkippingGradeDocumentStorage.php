<?php

namespace App\Services;

use App\Models\StudentSkippingGrade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SkippingGradeDocumentStorage
{
    public function directory(StudentSkippingGrade $record): string
    {
        $year=$record->targetAcademicYear?->academic_year??$record->student_snapshot['target_academic_year']??'Unknown-Year';
        return 'grade-skipping/attachments/'.$this->segment($year).'/'.$this->segment($record->reference_number?:'Request-'.$record->id);
    }

    public function segment(string $value): string
    {
        return Str::limit(preg_replace('/[^\pL\pN_-]+/u','-',trim($value))?:'Unknown',80,'');
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
