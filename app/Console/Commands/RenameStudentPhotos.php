<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RenameStudentPhotos extends Command
{
    protected $signature = 'students:rename-photos';
    protected $description = 'Rename student photos to Student Name_Student ID';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $renamed = 0;
        $missing = 0;

        Student::query()->whereNotNull('photo_path')->orderBy('id')->each(function (Student $student) use ($disk, &$renamed, &$missing) {
            if (!$disk->exists($student->photo_path)) {
                $missing++;
                $this->warn("Missing file for {$student->student_id}: {$student->photo_path}");
                return;
            }

            $extension = pathinfo($student->photo_path, PATHINFO_EXTENSION) ?: 'jpg';
            $name = trim((string) ($student->full_name_en ?: $student->full_name_kh ?: 'Student'));
            $name = preg_replace('/[^\pL\pN]+/u', '_', $name) ?: 'Student';
            $name = trim($name, '_');
            $studentId = preg_replace('/[^\pL\pN]+/u', '_', trim((string) $student->student_id)) ?: $student->id;
            $target = 'student_photos/' . $name . '_' . trim($studentId, '_') . '.' . strtolower($extension);

            if ($student->photo_path === $target) return;
            if ($disk->exists($target)) {
                $this->error("Target already exists for {$student->student_id}: {$target}");
                return;
            }

            if ($disk->move($student->photo_path, $target)) {
                $student->update(['photo_path' => $target]);
                $renamed++;
            }
        });

        $this->info("Renamed {$renamed} student photo(s). Missing: {$missing}.");
        return self::SUCCESS;
    }
}
