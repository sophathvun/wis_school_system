<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\Family;
use App\Support\LegacyStudentProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RestoreLegacyStudentProfiles extends Command
{
    protected $signature = 'legacy:restore-student-profiles {--path=storage/app/imports} {--dry-run}';
    protected $description = 'Restore only missing birthplace and parent occupation text from original workbooks';

    public function handle(): int
    {
        if (!Schema::hasColumn('tb_student', 'birth_province_en')) {
            $this->error('Run the legacy birthplace text migration first.');
            return self::FAILURE;
        }
        $path = base_path($this->option('path'));
        $reader = new ImportLegacyStudentWorkbooks();
        $counts = ['students'=>0, 'parents'=>0];
        foreach (['Student Information.xlsx', 'Parent Information.xlsx'] as $file) {
            if (!is_file($path.DIRECTORY_SEPARATOR.$file)) {
                $this->error('Missing '.$file);
                return self::FAILURE;
            }
        }
        DB::transaction(function () use ($reader, $path, &$counts) {
            foreach ($reader->readWorkbook($path.'/Student Information.xlsx') as $row) {
                $id = trim((string) ($row['student id'] ?? ''));
                if ($id === '' || !$student = Student::where('student_id', $id)->lockForUpdate()->first()) continue;
                $data = LegacyStudentProfile::missingBirthplace($student, $row);
                if (!$data) continue;
                $counts['students']++;
                if (!$this->option('dry-run')) $student->fill($data)->saveQuietly();
            }
            foreach ($reader->readWorkbook($path.'/Parent Information.xlsx') as $row) {
                $number = trim((string) ($row['family_number'] ?? $row['family number'] ?? ''));
                if ($number === '' || !$family = Family::with(['members'=>fn ($query) => $query->lockForUpdate()])->where('family_number', $number)->first()) continue;
                foreach ($family->members as $member) {
                    $data = LegacyStudentProfile::missingOccupation($member, $row);
                    if (!$data) continue;
                    $counts['parents']++;
                    if (!$this->option('dry-run')) $member->fill($data)->saveQuietly();
                }
            }
        });
        $this->info(($this->option('dry-run') ? 'Would restore: ' : 'Restored: ').json_encode($counts));
        return self::SUCCESS;
    }
}
