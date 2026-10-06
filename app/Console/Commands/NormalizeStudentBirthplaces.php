<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Support\StudentBirthplaceResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NormalizeStudentBirthplaces extends Command
{
    protected $signature = 'legacy:normalize-student-birthplaces {--dry-run}';
    protected $description = 'Link saved legacy birthplace names to matching location records without using sample files';

    public function handle(StudentBirthplaceResolver $resolver): int
    {
        if (!Schema::hasColumn('tb_student', 'birth_province_en')) {
            $this->error('Run the legacy birthplace text migration first.');
            return self::FAILURE;
        }
        $count = 0;
        Student::query()->chunkById(200, function ($students) use ($resolver, &$count) {
            foreach ($students as $student) {
                DB::transaction(function () use ($student, $resolver, &$count) {
                    $current = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
                    $changes = $resolver->changes($current);
                    if (!$changes) return;
                    $count++;
                    if (!$this->option('dry-run')) $current->fill($changes)->saveQuietly();
                });
            }
        });
        $this->info(($this->option('dry-run') ? 'Would normalize: ' : 'Normalized: ').$count.' student birthplaces.');
        return self::SUCCESS;
    }
}
