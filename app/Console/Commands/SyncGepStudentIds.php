<?php

namespace App\Console\Commands;

use App\Services\GepStudentIdAllocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncGepStudentIds extends Command
{
    protected $signature='gep:sync-student-ids {--apply : Advance the counter after importing GEP profiles}';
    protected $description='Check the highest imported GEP Student ID sequence without renumbering students';
    public function handle(GepStudentIdAllocator $allocator): int
    {
        $stored=DB::table('tb_gep_student_id_sequence')->where('id',1)->value('last_number');
        if($stored===null){$this->error('Run the GEP Student ID counter migration first.');return self::FAILURE;}
        $highest=$allocator->highestImportedSequence();
        $last=$this->option('apply')?$allocator->synchronize():max((int)$stored,$highest);
        $this->info(json_encode(['mode'=>$this->option('apply')?'applied':'preview','stored_sequence'=>(int)$stored,'highest_imported_sequence'=>$highest,'last_sequence'=>$last,'next_sequence'=>$last<999999?$last+1:null,'student_ids_changed'=>0],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
