<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSkippingDocumentArchive;
use App\Models\StudentSkippingGrade;
use App\Services\SkippingGradeDocumentArchiveService;
use Illuminate\Console\Command;

class OrganizeGradeSkippingDocuments extends Command
{
    protected $signature='grade-skipping:organize-documents {--user= : ID of an active user with document backup permission}';
    protected $description='Queue private supporting documents for organization by requested year and Approval Number';

    public function handle(SkippingGradeDocumentArchiveService $archives): int
    {
        if (!ctype_digit((string)$this->option('user'))) { $this->error('Provide --user=<authorized user ID>.'); return self::FAILURE; }
        $user=$archives->actor((int)$this->option('user'),'archive-backup');
        $count=0;
        StudentSkippingGrade::where('status','approved')->whereNotNull('reference_number')->chunkById(100,function ($records) use ($user,&$count) {
            foreach ($records as $record) { ProcessSkippingDocumentArchive::dispatch('organize',$record->id,$user->id); $count++; }
        });
        $this->info('Queued '.$count.' requests. Run the document-archives worker to process them.');
        return self::SUCCESS;
    }
}
