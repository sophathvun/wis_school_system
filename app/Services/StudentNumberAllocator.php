<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentNumberAllocator
{
    public function allocate(): string
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Student numbers must be allocated inside the enrollment transaction.');
        }

        // The lock is held until the enrollment commits, including the student insert.
        $sequence = DB::table('tb_student_number_sequence')->where('id', 1)->lockForUpdate()->first();
        if (!$sequence) {
            throw new \RuntimeException('Student number sequence is missing. Please run the database migrations.');
        }

        // Include existing/imported numbers so deployment never resets the numbering.
        $max = (int) Student::query()->selectRaw('MAX(CAST(student_no AS UNSIGNED)) as max_no')->value('max_no');
        $next = max((int) $sequence->last_number, $max) + 1;
        if ($next > 99999999) {
            throw ValidationException::withMessages([
                'student_no' => 'Unable to generate Student Number. The 8-digit limit has been reached.',
            ]);
        }

        DB::table('tb_student_number_sequence')->where('id', 1)->update(['last_number' => $next]);

        return str_pad((string) $next, 8, '0', STR_PAD_LEFT);
    }
}
