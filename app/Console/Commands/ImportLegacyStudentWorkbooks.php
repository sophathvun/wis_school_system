<?php

namespace App\Console\Commands;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class ImportLegacyStudentWorkbooks extends Command
{
    protected $signature = 'legacy:import-students {--path=storage/app/imports} {--only=all}';
    protected $description = 'Import legacy Student Information, Student Enrollment, and Parent Excel workbooks';

    public function handle(): int
    {
        $path = base_path($this->option('path'));
        $only = strtolower((string) $this->option('only'));
        $files = [
            'students' => $this->findFile($path, ['Student Information.xlsx']),
            'enrollments' => $this->findFile($path, ['Student Enrolment.xlsx', 'Student Enrollment.xlsx']),
            'parents' => $this->findFile($path, ['Parent Information.xlsx']),
        ];
        foreach ($files as $type => $file) if (!$file) { $this->error("Missing {$type} workbook in {$path}"); return self::FAILURE; }

        $students = $this->readWorkbook($files['students']);
        $enrollments = $only === 'parents' ? [] : $this->readWorkbook($files['enrollments']);
        $parents = $this->readWorkbook($files['parents']);
        $this->info('Students: '.count($students).', Enrollments: '.count($enrollments).', Parent rows: '.count($parents));

        $counts = ['students' => 0, 'enrollments' => 0, 'families' => 0, 'parents' => 0];
        DB::transaction(function () use ($students, $enrollments, $parents, &$counts) {
            $studentMap = [];
            foreach ($students as $row) {
                $studentId = trim((string) ($row['student id'] ?? ''));
                if ($studentId === '') continue;
                if (strtolower((string) $this->option('only')) === 'parents') {
                    $student = Student::where('student_id', $studentId)->first();
                    if ($student) { $student->update(['family_number' => $this->nullable($row['family_number'] ?? $row['family number'] ?? null)]); $studentMap[$studentId] = $student; $counts['students']++; }
                    continue;
                }
                $student = Student::updateOrCreate(['student_id' => $studentId], [
                    'student_no' => $this->studentNo($row, $studentId),
                    'family_number' => $this->nullable($row['family_number'] ?? $row['family number'] ?? null),
                    'first_name_en' => $this->nameValue($row['name in english'] ?? null, 50),
                    'last_name_en' => '',
                    'first_name_kh' => $this->nameValue($row['name in khmer'] ?? null, 50),
                    'last_name_kh' => null,
                    'gender' => $this->gender($row['sex'] ?? null),
                    'gender_kh' => $this->genderKh($row['sex'] ?? null),
                    'date_of_birth' => $this->dateValue($row['date of birth'] ?? null),
                    'nationality_country_id' => $this->countryId($row['nationality'] ?? null),
                    'home_phone' => $this->phone($row['home phone'] ?? null),
                    'email' => $this->nullable($row['e-mail address'] ?? null),
                    'current_address_en' => $this->nullable($row['home address'] ?? null),
                    'current_address_kh' => $this->nullable($row['kh_current_address'] ?? null),
                    'previous_school' => $this->nullable($row['previous school'] ?? null),
                    'experienced_english' => $this->nullable($row['experienced english'] ?? null),
                    'test_result' => $this->nullable($row['testresult'] ?? null),
                    'tested_by' => $this->nullable($row['testby'] ?? null),
                    'remarks' => $this->nullable($row['remarks'] ?? null),
                    'status' => 1,
                ]);
                $studentMap[$studentId] = $student;
                $counts['students']++;
            }

            foreach ($parents as $row) {
                $familyNumber = trim((string) ($row['family_number'] ?? $row['family number'] ?? ''));
                if ($familyNumber === '') continue;
                $family = Family::firstOrCreate(['family_number' => $familyNumber], ['family_name' => $familyNumber, 'status' => 1]);
                $counts['families']++;
                foreach ([
                    'mother' => ['name_en' => 'mother_name_en', 'name_kh' => 'mother_name_kh', 'occupation' => 'mother_occupation_en', 'phone' => 'mother_phone', 'nationality' => 'mother_nationality_en', 'workplace' => 'mother_place_of_work'],
                    'father' => ['name_en' => 'father_name_en', 'name_kh' => 'father_name_kh', 'occupation' => 'father_occupation_en', 'phone' => 'father_phone', 'nationality' => 'father_nationality_en', 'workplace' => 'father_place_of_work'],
                ] as $relationship => $fields) {
                    $name = $this->nullable($row[$fields['name_en']] ?? null);
                    if (!$name) continue;
                    FamilyMember::updateOrCreate(['family_id' => $family->id, 'relationship_type' => $relationship], [
                        'first_name_en' => $this->nameValue($name, 80), 'last_name_en' => '', 'first_name_kh' => $this->nameValue($row[$fields['name_kh']] ?? null, 80), 'last_name_kh' => null,
                        'name_en' => $this->nameValue($name, 160), 'name_kh' => $this->nameValue($row[$fields['name_kh']] ?? null, 160), 'phone' => $this->phone($row[$fields['phone']] ?? null),
                        'occupation_id' => $this->occupationId($row[$fields['occupation']] ?? null), 'nationality_country_id' => $this->countryId($row[$fields['nationality']] ?? null),
                        'workplace' => $this->nullable($row[$fields['workplace']] ?? null), 'status' => 1,
                    ]);
                    $counts['parents']++;
                }
            }

            foreach ($enrollments as $row) {
                $studentId = trim((string) ($row['student id'] ?? ''));
                $student = $studentMap[$studentId] ?? Student::where('student_id', $studentId)->first();
                if (!$student) continue;
                $yearId = $this->academicYearId($row['academicyear'] ?? null);
                $campusId = $this->campusId($row['campus'] ?? null);
                [$gradeId, $classId] = $this->gradeClass($row['grade'] ?? null, $yearId, $this->sessionId($row['stugroup'] ?? null));
                if (!$yearId || !$campusId || !$gradeId || !$classId) continue;
                $sessionId = $this->sessionId($row['stugroup'] ?? null);
                StudentEnrollment::updateOrCreate(['student_id' => $student->id, 'academic_year_id' => $yearId], [
                    'campus_id' => $campusId, 'grade_id' => $gradeId, 'class_id' => $classId, 'session_id' => $sessionId,
                    'student_type' => strtolower(trim((string) ($row['old/new'] ?? ''))) === 'old' ? 'old' : 'new',
                    'enrollment_status' => 'active', 'enrolled_on' => $this->dateValue($row['enrolldate'] ?? null), 'status' => 1,
                ]);
                if ($student->family_number) $this->linkFamily($student);
                $counts['enrollments']++;
            }
            if (strtolower((string) $this->option('only')) === 'parents') {
                foreach ($studentMap as $student) if ($student->family_number) $this->linkFamily($student);
            }
        });
        $this->info(json_encode($counts, JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }

    private function readWorkbook(string $file): array
    {
        $zip = new ZipArchive(); if ($zip->open($file) !== true) throw new \RuntimeException("Cannot open {$file}");
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) { $doc = simplexml_load_string($xml); foreach ($doc->si as $si) $shared[] = (string) ($si->t ?? implode('', array_map('strval', iterator_to_array($si->r->t ?? [])))); }
        $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml')); $zip->close();
        $headers = []; $result = [];
        foreach ($sheet->sheetData->row as $row) { $values = []; foreach ($row->c as $cell) { $ref = (string) $cell['r']; preg_match('/([A-Z]+)/', $ref, $match); $index = 0; foreach (str_split($match[1] ?? '') as $char) $index = $index * 26 + ord($char) - 64; $index--; $value = (string) ($cell->v ?? ''); if ((string) $cell['t'] === 's') $value = $shared[(int) $value] ?? ''; elseif ((string) $cell['t'] === 'inlineStr') $value = (string) ($cell->is->t ?? ''); $values[$index] = trim($value); } if (!$headers) { $headers = array_map(fn ($v) => strtolower(trim((string) $v)), array_values($values)); continue; } $rowData = []; foreach ($headers as $i => $header) $rowData[$header] = $values[$i] ?? null; $result[] = $rowData; }
        return $result;
    }

    private function findFile(string $path, array $names): ?string { foreach ($names as $name) if (is_file($path.DIRECTORY_SEPARATOR.$name)) return $path.DIRECTORY_SEPARATOR.$name; return null; }
    private function nullable($v): ?string { $v = trim((string) $v); return $v === '' ? null : $v; }
    private function nameValue($v, int $length): ?string { $v = preg_replace('/\s+/u', ' ', trim((string) $v)); return $v === '' ? null : mb_substr($v, 0, $length); }
    private function phone($v): ?string { return $this->nullable(preg_replace('/\s+/', '', (string) $v)); }
    private function gender($v): ?string { return match (strtoupper(trim((string) $v))) { 'M', 'MALE' => 'Male', 'F', 'FEMALE' => 'Female', default => $this->nullable($v) }; }
    private function genderKh($v): ?string { return match (strtoupper(trim((string) $v))) { 'M', 'MALE' => 'ប្រុស', 'F', 'FEMALE' => 'ស្រី', default => null }; }
    private function dateValue($v): ?string { if ($v === null || trim((string) $v) === '') return null; if (is_numeric($v)) return Carbon::create(1899, 12, 30)->addDays((int) round((float) $v))->format('Y-m-d'); foreach (['Y-m-d','d/m/Y','d-m-Y'] as $format) try { return Carbon::createFromFormat($format, trim((string) $v))->format('Y-m-d'); } catch (\Throwable) {} return null; }
    private function studentNo(array $row, string $studentId): string { return $this->nullable($row['student no'] ?? null) ?: 'S'.str_pad((string) (Student::max('id') + 1), 6, '0', STR_PAD_LEFT); }
    private function findId(string $table, $value, array $columns): ?int { $value = $this->nullable($value); if (!$value) return null; if (ctype_digit($value)) return (int) $value; return DB::table($table)->where(function ($q) use ($columns, $value) { foreach ($columns as $column) $q->orWhereRaw("LOWER(`{$column}`) = ?", [strtolower($value)]); })->value('id'); }
    private function countryId($v): ?int { return $this->findId('tb_country', $v, ['country_name_en','country_name_kh','nationality_name_en','nationality_name_kh']); }
    private function occupationId($v): ?int { return $this->findId('tb_occupation', $v, ['occupation_name_en','occupation_name_kh']); }
    private function academicYearId($v): ?int { return $this->findId('tb_academic_year', $v, ['academic_year','academic_year_code']); }
    private function campusId($v): ?int { return $this->findId('tb_school_info', $v, ['campus_name_en','campus_name_kh']); }
    private function sessionId($v): ?int { return $this->findId('tb_session', $v, ['session_short_name','session_name']); }
    private function gradeClass($value, ?int $yearId, ?int $sessionId): array { $value = strtoupper(trim((string) $value)); if (!preg_match('/^(K[1-3]|[0-9]+)(?:-?([A-Z]))?$/', $value, $m)) return [null, null]; $grade = DB::table('tb_grade')->where('grade_short_name', $m[1])->first(); if (!$grade) return [null, null]; $className = $m[2] ?? 'A'; $class = DB::table('tb_class')->where('class_name', $className)->where(function ($q) use ($yearId, $grade) { $q->whereNull('academic_year_id')->orWhere('academic_year_id', $yearId); })->where(function ($q) use ($grade) { $q->whereNull('grade_id')->orWhere('grade_id', $grade->id); })->first(); if (!$class) { $classId = DB::table('tb_class')->insertGetId(['academic_year_id' => $yearId, 'grade_id' => $grade->id, 'session_id' => $sessionId, 'class_name' => $className, 'class_order' => 1, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]); } else $classId = $class->id; return [$grade->id, $classId]; }
    private function linkFamily(Student $student): void { $family = Family::where('family_number', $student->family_number)->first(); if ($family) DB::table('tb_family_student')->updateOrInsert(['family_id' => $family->id, 'student_id' => $student->id], ['relationship_type' => 'parent', 'updated_at' => now(), 'created_at' => now()]); }
}
