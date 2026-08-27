<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentDataTransferController
{
    private const STUDENT_COLUMNS = ['student_id', 'student_no', 'family_number', 'full_name_en', 'full_name_kh', 'gender', 'gender_kh', 'date_of_birth', 'nationality_country_id', 'home_phone', 'email', 'birth_country_id', 'birth_province_id', 'birth_district_id', 'birth_commune_id', 'birth_village_id', 'address_country_id', 'address_province_id', 'address_district_id', 'address_commune_id', 'address_village_id', 'address_house_no_en', 'address_house_no_kh', 'address_street_en', 'address_street_kh', 'current_address_en', 'current_address_kh', 'previous_school', 'experienced_english', 'test_result', 'tested_by', 'remarks', 'photo_path', 'status'];
    private const ENROLLMENT_COLUMNS = ['student_id', 'academic_year_id', 'campus_id', 'grade_id', 'class_id', 'session_id', 'group_id', 'student_type', 'enrollment_status', 'enrolled_on', 'ended_on', 'exit_reason', 'notes', 'status'];
    private const FAMILY_COLUMNS = ['family_number', 'student_id', 'relationship_type', 'full_name_en', 'full_name_kh', 'phone', 'email', 'occupation_id', 'nationality_country_id', 'workplace', 'is_primary_contact', 'has_pickup_authorization', 'has_portal_access', 'status'];

    public function index() { return view('student-data-transfer'); }

    public function template(string $type)
    {
        $columns = $this->templateColumns($type);
        abort_unless($columns, 404);
        return $this->csvResponse($type.'-template.csv', [$columns, $this->exampleRow($type, $columns)]);
    }

    public function export(string $type)
    {
        abort_unless(in_array($type, ['students', 'enrollments', 'families'], true), 404);
        $columns = $this->templateColumns($type);
        $rows = match ($type) {
            'students' => Student::with(['nationalityCountry', 'birthCountry', 'birthProvince', 'birthDistrict', 'birthCommune', 'birthVillage', 'addressCountry', 'addressProvince', 'addressDistrict', 'addressCommune', 'addressVillage'])->orderBy('id')->get()->map(fn ($item) => $this->friendlyRow($item, $columns, 'students')),
            'enrollments' => StudentEnrollment::with(['student', 'academicYear', 'campus', 'grade', 'schoolClass', 'session', 'schoolGroup'])->orderBy('student_id')->orderBy('academic_year_id')->get()->map(function ($item) {
                $row = $this->friendlyRow($item, $this->templateColumns('enrollments'), 'enrollments');
                $row['student_id'] = $item->student?->student_id;
                return $row;
            }),
            'families' => FamilyMember::with(['family', 'students'])->orderBy('family_id')->orderBy('relationship_type')->get()->flatMap(function ($item) {
                $row = $this->friendlyRow($item, $this->templateColumns('families'), 'families');
                $row['family_number'] = $item->family?->family_number;
                $students = $item->students;
                if ($students->isEmpty()) $students = collect([null]);
                return $students->map(function ($student) use ($row) {
                    $studentRow = $row;
                    $studentRow['student_id'] = $student?->student_id;
                    return $studentRow;
                });
            }),
        };
        return $this->csvResponse($type.'-export-'.now()->format('Ymd-His').'.csv', collect([$columns])->merge($rows->map(fn ($row) => array_values($row)))->all());
    }

    public function import(Request $request, string $type)
    {
        abort_unless($this->columns($type), 404);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        [$headers, $rows] = $this->readCsv($request->file('file')->getRealPath());
        $required = match ($type) { 'students' => ['student_id'], 'enrollments' => ['student_id', 'academic_year_id', 'campus_id', 'grade_id', 'class_id'], 'families' => ['family_number', 'relationship_type', 'full_name_en'] };
        $missing = array_diff($required, $headers);
        if ($missing) return back()->withErrors(['file' => 'Missing required columns: '.implode(', ', $missing)]);
        $count = 0;
        $failed = [];
        foreach ($rows as $index => $values) {
            $line = $index + 2;
            $data = array_combine($headers, array_slice(array_pad($values, count($headers), null), 0, count($headers)));
            if (!collect($data)->contains(fn ($value) => filled($value))) continue;

            $missingValues = collect($required)->filter(fn ($field) => !filled($data[$field] ?? null))->values()->all();
            if ($missingValues) {
                $failed[] = $this->failedImportRow($type, $data, $line, $missingValues, 'Required value is missing.');
                continue;
            }

            try {
                DB::transaction(fn () => $this->importRow($type, $data, $line));
                $count++;
            } catch (\Throwable $exception) {
                $failed[] = $this->failedImportRow($type, $data, $line, [], $exception->getMessage());
            }
        }

        return back()
            ->with('success', "{$count} {$type} row(s) imported successfully.")
            ->with('import_report', [
                'type' => $type,
                'total' => $count + count($failed),
                'imported' => $count,
                'failed' => $failed,
            ]);
    }

    private function failedImportRow(string $type, array $data, int $line, array $fields, string $reason): array
    {
        $identifier = $type === 'families'
            ? ($data['family_number'] ?? '')
            : ($data['student_id'] ?? '');

        return [
            'line' => $line,
            'identifier' => trim((string) $identifier) ?: '-',
            'fields' => $fields,
            'reason' => Str::limit($reason, 500),
            'data' => collect($data)->filter(fn ($value) => filled($value))->only([
                'student_id', 'family_number', 'relationship_type', 'academic_year_id',
                'campus_id', 'grade_id', 'class_id', 'session_id', 'full_name_en', 'full_name_kh',
            ])->all(),
        ];
    }

    private function importRow(string $type, array $data, int $line): void
    {
        if ($type === 'students') {
            $values = collect($data)->only(self::STUDENT_COLUMNS)->except(['student_id', 'full_name_en', 'full_name_kh', 'status'])->map(fn ($value) => $value === '' ? null : $value)->all();
            foreach (['nationality_country_id', 'birth_country_id', 'address_country_id'] as $field) $values[$field] = $this->lookupId($values[$field] ?? null, 'tb_country', ['country_name_en', 'country_name_kh', 'nationality_name_en', 'nationality_name_kh']);
            foreach (['birth_province_id', 'address_province_id'] as $field) $values[$field] = $this->lookupId($values[$field] ?? null, 'tb_province', ['province_name_en', 'province_name_kh']);
            foreach (['birth_district_id', 'address_district_id'] as $field) $values[$field] = $this->lookupId($values[$field] ?? null, 'tb_district', ['district_name_en', 'district_name_kh']);
            foreach (['birth_commune_id', 'address_commune_id'] as $field) $values[$field] = $this->lookupId($values[$field] ?? null, 'tb_commune', ['commune_name_en', 'commune_name_kh']);
            foreach (['birth_village_id', 'address_village_id'] as $field) $values[$field] = $this->lookupId($values[$field] ?? null, 'tb_village', ['village_name_en', 'village_name_kh']);
            $values['full_name_en'] = trim((string) ($data['full_name_en'] ?? ''));
            $values['full_name_kh'] = ($data['full_name_kh'] ?? '') === '' ? null : $data['full_name_kh'];
            $values['status'] = $this->boolean($data['status'] ?? '1');
            $student = Student::updateOrCreate(['student_id' => trim($data['student_id'])], $values);
            if (!$student->student_no) $student->update(['student_no' => 'S'.str_pad((string) $student->id, 6, '0', STR_PAD_LEFT)]);
            return;
        }
        if ($type === 'enrollments') {
            $student = Student::where('student_id', trim($data['student_id']))->firstOrFail();
            $values = collect($data)->only(self::ENROLLMENT_COLUMNS)->except('student_id')->map(fn ($value, $key) => in_array($key, ['status']) ? $this->boolean($value) : ($value === '' ? null : $value))->all();
            $values['academic_year_id'] = $this->lookupId($values['academic_year_id'] ?? null, 'tb_academic_year', ['academic_year', 'academic_year_code']);
            $values['campus_id'] = $this->lookupId($values['campus_id'] ?? null, 'tb_school_info', ['campus_name_en', 'campus_name_kh', 'school_name_en', 'school_name_kh']);
            $values['grade_id'] = $this->lookupId($values['grade_id'] ?? null, 'tb_grade', ['grade', 'grade_short_name']);
            $values['class_id'] = $this->lookupId($values['class_id'] ?? null, 'tb_class', ['class_name']);
            $values['group_id'] = $this->lookupId($values['group_id'] ?? null, 'tb_group', ['group_name']);
            $values['session_id'] = $this->lookupId($values['session_id'] ?? null, 'tb_session', ['session_name', 'session_short_name']);
            $values['student_id'] = $student->id;
            $values['enrollment_status'] = $values['enrollment_status'] ?: 'active';
            $values['status'] = array_key_exists('status', $data) && filled($data['status']) ? $this->boolean($data['status']) : 1;
            StudentEnrollment::updateOrCreate(['student_id' => $student->id, 'academic_year_id' => $values['academic_year_id']], $values);
            return;
        }
        $family = Family::firstOrCreate(['family_number' => trim($data['family_number'])], ['family_name' => trim($data['family_number']), 'status' => 1]);
        $student = null;
        if (filled($data['student_id'] ?? null)) {
            $student = Student::where('student_id', trim($data['student_id']))->firstOrFail();
            if ($student->family_number !== trim($data['family_number'])) {
                throw new \RuntimeException("Student '{$data['student_id']}' is not assigned to Family Number '{$data['family_number']}'.");
            }
        }
        $values = collect($data)->only(self::FAMILY_COLUMNS)->except(['family_number', 'student_id'])->map(fn ($value, $key) => in_array($key, ['is_primary_contact', 'has_pickup_authorization', 'has_portal_access', 'status']) ? $this->boolean($value) : ($value === '' ? null : $value))->all();
        $values['occupation_id'] = $this->lookupId($values['occupation_id'] ?? null, 'tb_occupation', ['occupation_name_en', 'occupation_name_kh']);
        $values['nationality_country_id'] = $this->lookupId($values['nationality_country_id'] ?? null, 'tb_country', ['country_name_en', 'country_name_kh', 'nationality_name_en', 'nationality_name_kh']);
        $values['full_name_en'] = $data['full_name_en'] ?? null;
        $values['full_name_kh'] = $data['full_name_kh'] ?? null;
        $values['status'] = array_key_exists('status', $data) && filled($data['status']) ? $this->boolean($data['status']) : 1;
        $values['name_en'] = $data['full_name_en'] ?? null;
        $values['name_kh'] = $data['full_name_kh'] ?? null;
        if ($student) {
            $member = $student->familyMembers()->where('tb_family_member.relationship_type', trim($data['relationship_type']))->first()
                ?: $family->members()->where('relationship_type', trim($data['relationship_type']))->where('full_name_en', $values['full_name_en'])->where('phone', $values['phone'])->first()
                ?: new FamilyMember(['family_id' => $family->id, 'relationship_type' => trim($data['relationship_type'])]);
            $member->fill($values)->save();
            DB::table('tb_student_family_member')->updateOrInsert(
                ['student_id' => $student->id, 'family_member_id' => $member->id],
                ['relationship_type' => trim($data['relationship_type']), 'is_primary_contact' => (bool) ($values['is_primary_contact'] ?? false), 'created_at' => now(), 'updated_at' => now()]
            );
        } else {
            FamilyMember::updateOrCreate(['family_id' => $family->id, 'relationship_type' => trim($data['relationship_type'])], $values);
        }
    }

    private function columns(string $type): array
    {
        return match ($type) { 'students' => self::STUDENT_COLUMNS, 'enrollments' => self::ENROLLMENT_COLUMNS, 'families' => self::FAMILY_COLUMNS, default => [] };
    }

    private function templateColumns(string $type): array
    {
        return match ($type) {
            'students' => array_values(array_diff(self::STUDENT_COLUMNS, ['student_no', 'status'])),
            'enrollments' => array_values(array_diff(self::ENROLLMENT_COLUMNS, ['status', 'enrollment_status'])),
            'families' => array_values(array_diff(self::FAMILY_COLUMNS, ['status'])),
            default => [],
        };
    }

    private function exampleRow(string $type, array $columns): array
    {
        $examples = match ($type) {
            'students' => [
                'student_id' => 'STU-0001', 'student_no' => 'S000001', 'family_number' => 'FAM-0001',
                'full_name_en' => 'SOK DARA', 'full_name_kh' => 'សុខ ដារ៉ា', 'gender' => 'Male', 'gender_kh' => 'ប្រុស',
                'date_of_birth' => '2012-05-15', 'nationality_country_id' => 'Cambodian', 'home_phone' => '+85512345678',
                'email' => 'dara.sok@example.com', 'birth_country_id' => 'Cambodia', 'birth_province_id' => 'Phnom Penh',
                'birth_district_id' => 'Chamkar Mon', 'birth_commune_id' => 'Tonle Bassac', 'birth_village_id' => 'Village 1',
                'address_country_id' => 'Cambodia', 'address_province_id' => 'Phnom Penh', 'address_district_id' => 'Chamkar Mon',
                'address_commune_id' => 'Tonle Bassac', 'address_village_id' => 'Village 1', 'address_house_no_en' => 'House 123',
                'address_house_no_kh' => 'ផ្ទះលេខ ១២៣', 'address_street_en' => 'Street 5', 'address_street_kh' => 'ផ្លូវ ៥',
                'current_address_en' => 'House 123, Street 5, Phnom Penh', 'current_address_kh' => 'ផ្ទះលេខ ១២៣ ផ្លូវ ៥ រាជធានីភ្នំពេញ',
                'previous_school' => 'Example International School', 'experienced_english' => 'Intermediate',
                'test_result' => 'Passed', 'tested_by' => 'Admissions Officer', 'remarks' => 'Example student record',
                'photo_path' => 'students/STU-0001.jpg', 'status' => '1',
            ],
            'enrollments' => [
                'student_id' => 'STU-0001', 'academic_year_id' => '2025-2026', 'campus_id' => 'BCH', 'grade_id' => 'Grade 1',
                'class_id' => '1A', 'session_id' => 'Morning', 'group_id' => 'Morning', 'student_type' => 'new',
                'enrollment_status' => 'active', 'enrolled_on' => '2025-08-01', 'ended_on' => '2026-06-30',
                'exit_reason' => 'Completed academic year', 'notes' => 'Example enrollment history record', 'status' => '1',
            ],
            'families' => [
                'family_number' => 'FAM-0001', 'student_id' => 'STU-0001', 'relationship_type' => 'mother', 'full_name_en' => 'SOK SREY MOM',
                'full_name_kh' => 'សុខ ស្រីមុំ', 'phone' => '+85512345679', 'email' => 'srey.mom@example.com', 'occupation_id' => 'Teacher',
                'nationality_country_id' => 'Cambodian', 'workplace' => 'Example Company', 'is_primary_contact' => '1',
                'has_pickup_authorization' => '1', 'has_portal_access' => '1', 'status' => '1',
            ],
            default => [],
        };

        return array_map(fn ($column) => $examples[$column] ?? '', $columns);
    }

    private function row($model, array $columns): array
    {
        return collect($columns)->mapWithKeys(fn ($column) => [$column => match ($column) {
            'full_name_en' => $model->full_name_en,
            'full_name_kh' => $model->full_name_kh,
            'name_en', 'name_kh' => $model->{$column} ?: trim(($model->{'first_name_'.substr($column, 5)} ?? '').' '.($model->{'last_name_'.substr($column, 5)} ?? '')),
            default => $model->{$column},
        }])->all();
    }

    private function friendlyRow($model, array $columns, string $type): array
    {
        return collect($columns)->mapWithKeys(function ($column) use ($model, $type) {
            $value = match ($type) {
                'students' => match ($column) {
                    'nationality_country_id' => $model->nationalityCountry?->country_name_en ?: $model->nationalityCountry?->country_name_kh,
                    'birth_country_id', 'address_country_id' => $model->{Str::camel(str_replace('_id', '', $column))}?->country_name_en ?: $model->{Str::camel(str_replace('_id', '', $column))}?->country_name_kh,
                    'birth_province_id', 'address_province_id', 'birth_district_id', 'address_district_id', 'birth_commune_id', 'address_commune_id', 'birth_village_id', 'address_village_id' => $model->{Str::camel(str_replace('_id', '', $column))}?->{$this->locationNameColumn($column)},
                    default => $model->{$column},
                },
                'enrollments' => match ($column) {
                    'academic_year_id' => $model->academicYear?->academic_year,
                    'campus_id' => $model->campus?->campus_name_en ?: $model->campus?->campus_name_kh,
                    'grade_id' => $model->grade?->grade,
                    'class_id' => $model->schoolClass?->class_name,
                    'session_id' => $model->session?->session_short_name ?: $model->session?->session_name,
                    'group_id' => $model->schoolGroup?->group_name,
                    default => $model->{$column},
                },
                default => match ($column) {
                    'occupation_id' => $model->occupation_en ?: $model->occupation_kh ?: $model->occupation,
                    'nationality_country_id' => $model->nationality_en ?: $model->nationality_kh,
                    default => $model->{$column},
                },
            };
            return [$column => $value];
        })->all();
    }

    private function locationNameColumn(string $column): string
    {
        return str_contains($column, 'province') ? 'province_name_en' : (str_contains($column, 'district') ? 'district_name_en' : (str_contains($column, 'commune') ? 'commune_name_en' : 'village_name_en'));
    }

    private function boolean($value): int { return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'active'], true) ? 1 : 0; }

    private function lookupId($value, string $table, array $columns): ?int
    {
        if ($value === null || trim((string) $value) === '') return null;
        if (ctype_digit(trim((string) $value))) return (int) $value;
        $query = DB::table($table);
        $query->where(function ($builder) use ($columns, $value) {
            foreach ($columns as $column) {
                try { $builder->orWhereRaw("LOWER(`{$column}`) = ?", [strtolower(trim((string) $value))]); } catch (\Throwable) { }
            }
        });
        $ids = $query->pluck('id')->unique()->values();
        if ($ids->isEmpty()) throw new \RuntimeException("Could not find '{$value}' in {$table}. Use its ID or exact English/Khmer name.");
        if ($ids->count() > 1) throw new \RuntimeException("The value '{$value}' matches multiple records in {$table}. Use the correct ID or a more specific name.");
        return (int) $ids->first();
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        $headers = array_map(fn ($header) => Str::of((string) $header)->trim()->lower()->replace(' ', '_')->toString(), fgetcsv($handle) ?: []);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) $rows[] = $row;
        fclose($handle);
        return [$headers, $rows];
    }

    private function csvResponse(string $filename, array $rows)
    {
        return response()->streamDownload(function () use ($rows) { $output = fopen('php://output', 'w'); fwrite($output, "\xEF\xBB\xBF"); foreach ($rows as $row) fputcsv($output, $row); fclose($output); }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
