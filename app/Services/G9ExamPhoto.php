<?php

namespace App\Services;

use App\Models\AcademicYear;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class G9ExamPhoto
{
    public function directory(int $yearId): string
    {
        $year=AcademicYear::findOrFail($yearId)->academic_year;
        $folder=trim(preg_replace('/[^A-Za-z0-9_-]+/','-',trim($year)),'-');
        if($folder==='')$folder='year-'.$yearId;
        return 'g9_national_exams/'.$folder;
    }

    public function store(int $yearId,string $bytes): string
    {
        $path=$this->directory($yearId).'/'.Str::uuid().'.webp';
        $optimized=app(G12NationalExamPhoto::class)->webp($bytes);
        if(!Storage::disk('public')->put($path,$optimized))throw new \RuntimeException('The G9 student photo could not be stored.');
        return $path;
    }

    /** Copy before updating the record, so a failed save keeps the current photo intact. */
    public function copyToYear(int $yearId,string $source): string
    {
        $path=$this->directory($yearId).'/'.Str::uuid().'.'.pathinfo($source,PATHINFO_EXTENSION);
        if(!Storage::disk('public')->copy($source,$path))throw new \RuntimeException('The G9 photo could not be copied to its academic year folder.');
        return $path;
    }
}
