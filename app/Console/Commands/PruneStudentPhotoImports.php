<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Storage};

class PruneStudentPhotoImports extends Command
{
    protected $signature = 'student-photos:prune-imports';
    protected $description = 'Remove expired private bulk photo upload sources; retain import history and saved student photos.';

    public function handle(): int
    {
        $count=0;
        DB::table('student_photo_batches')->where('expires_at','<',now())->orderBy('id')->chunkById(100,function ($batches) use (&$count) {
            foreach ($batches as $batch) DB::transaction(function () use ($batch,&$count) {
                DB::table('student_photo_batches')->where('id',$batch->id)->lockForUpdate()->first();
                foreach (DB::table('student_photo_rows')->where('batch_id',$batch->id)->whereNotNull('source_path')->get() as $row) {
                    if (!str_starts_with($row->source_path,'student-photo-imports/'.$batch->id.'/')) continue;
                    if (Storage::disk('local')->delete($row->source_path)) {
                        DB::table('student_photo_rows')->where('id',$row->id)->update(['source_path'=>null]); $count++;
                    }
                }
            });
        });
        $this->info('Removed '.$count.' expired preview files. Saved student photos and history were preserved.');
        return self::SUCCESS;
    }
}
