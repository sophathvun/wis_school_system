<?php

namespace App\Services;

use Illuminate\Support\Collection;

class G12ResultReports
{
    public const TYPES = [
        'grade-a' => 'List of Grade A Students',
        'grade-a-campus' => 'List of Grade A Students By Campus',
        'result-list' => 'List of Result',
        'statistics-doeys' => 'Passed/Failed Statistics to DoEYS',
        'statistics-all' => 'Passed/Failed Statistics All',
    ];

    public function prepare(Collection $records, string $type): array
    {
        $rows = $records->values()->map(fn ($record, $index) => $this->record($record, $index + 1));

        if (in_array($type, ['grade-a', 'grade-a-campus'], true)) {
            $rows = $rows->where('letter_grade', 'A')->sortByDesc(fn ($row) => (float) ($row['score_rank'] ?: -1))->values();
            $rows = $rows->map(fn ($row, $index) => array_replace($row, ['certificate_number' => $row['certificate_number'] ?: $this->certificateNumber($row, $index + 1)]));
        } elseif ($type === 'result-list') {
            $order = array_flip(['A', 'B', 'C', 'D', 'E', 'F', '']);
            $rows = $rows->sortBy(fn ($row) => sprintf('%s|%03d|%s', $row['campus'], $order[$row['letter_grade']] ?? 99, $row['name_kh']))->values();
        }

        $groups = $type === 'grade-a-campus' || $type === 'result-list'
            ? $rows->groupBy('campus')->map(fn ($items) => $items->values())->all()
            : [];

        return [
            'type' => $type,
            'title' => self::TYPES[$type],
            'orientation' => str_starts_with($type, 'statistics-') ? 'landscape' : 'portrait',
            'rows' => $rows->all(),
            'groups' => $groups,
            'statistics' => str_starts_with($type, 'statistics-') ? $this->statistics($rows, $type === 'statistics-all') : [],
        ];
    }

    public function excelRows(array $report, array $filters = []): array
    {
        $rows = [[$report['title']], ['Generated', now('Asia/Bangkok')->format('d M Y H:i')]];
        foreach (array_filter($filters, fn ($value) => filled($value)) as $label => $value) $rows[] = [ucwords(str_replace('_', ' ', $label)), (string) $value];
        $rows[] = [];

        if (in_array($report['type'], ['grade-a', 'grade-a-campus'], true)) {
            $rows[] = ['No.', 'Certificate No.', 'Name English', 'Name Khmer', 'Gender', 'Student ID', 'Grade', 'Letter Grade', 'Score / Rank', 'Phone'];
            $number = 0; $campus = null;
            foreach ($report['rows'] as $item) {
                if ($report['type'] === 'grade-a-campus' && $campus !== $item['campus']) { $campus = $item['campus']; $number = 0; $rows[] = [$campus]; }
                $rows[] = [++$number, $item['certificate_number'], $item['name_en'], $item['name_kh'], $item['gender'], $item['student_code'], $item['grade_line'], $item['letter_grade'], $item['score_rank'], $item['phone']];
            }
            return $rows;
        }

        if ($report['type'] === 'result-list') {
            $rows[] = ['No.', 'Name Khmer', 'Name English', 'Gender', 'Date of Birth', 'Exam Center', 'Room', 'Seat', 'Letter Grade', 'Selected'];
            foreach ($report['groups'] as $campus => $items) { $rows[] = [$campus]; foreach ($items as $index => $item) $rows[] = [$index + 1, $item['name_kh'], $item['name_en'], $item['gender'], $item['dob'], $item['center'], $item['room'], $item['seat'], $item['letter_grade'], $item['selected']]; }
            return $rows;
        }

        $rows[] = ['Campus', 'Examination', 'Registered M', 'Registered F', 'Examined M', 'Examined F', 'Passed M', 'Passed F', 'Pass % M', 'Pass % F', 'A M', 'A F', 'B M', 'B F', 'C M', 'C F', 'D M', 'D F', 'E M', 'E F', 'F M', 'F F'];
        foreach ($report['statistics']['rows'] as $item) {
            $line = [$item['campus'], $item['label'], $item['registered']['male'], $item['registered']['female'], $item['examined']['male'], $item['examined']['female'], $item['passed']['male'], $item['passed']['female'], $item['pass_percentage']['male'].'%', $item['pass_percentage']['female'].'%'];
            foreach (['A','B','C','D','E','F'] as $grade) array_push($line, $item['grades'][$grade]['male'], $item['grades'][$grade]['female']);
            $rows[] = $line;
        }
        return $rows;
    }

    public function filename(string $type, string $extension): string
    {
        return 'g12-'.str_replace('_', '-', $type).'-'.now('Asia/Bangkok')->format('Ymd').'.'.$extension;
    }

    private function record($record, int $number): array
    {
        $data = $record->data ?? [];
        $grade = strtoupper(trim((string) ($record->overall_grade ?: ($data['g12_letter_grade'] ?? ''))));
        $gender = $this->gender((string) ($data['gender'] ?? ''));
        $language = $this->language((string) ($data['language_exam'] ?? ''));
        $selected = $this->selected((string) ($data['class_sellected'] ?? $data['class_selected'] ?? ''));
        $score = $data['g12_score_rank'] ?? '';
        $certificate = $record->certificates->first(fn ($item) => filled($item->number ?? null));

        return [
            'number' => $number, 'academic_year' => $record->academic_year, 'campus' => $record->campus?->campus_name_en ?: ($data['campus'] ?? 'Unassigned'),
            'name_en' => $record->name_en, 'name_kh' => $record->name_kh, 'student_code' => $record->student_code,
            'gender' => $gender['label'], 'gender_key' => $gender['key'], 'dob' => $data['dob'] ?? '', 'phone' => $data['phone_number'] ?? '',
            'grade' => $record->grade, 'grade_line' => implode('-', array_filter([$record->academic_year, $record->campus?->campus_name_en ?: ($data['campus'] ?? null), $record->grade])),
            'center' => $record->center?->name ?: ($data['g12_exam_center'] ?? ''), 'exam_date' => $data['g12_exam_date'] ?? '',
            'room' => $data['g12_exam_room'] ?? '', 'seat' => $data['g12_exam_seat'] ?? '',
            'language' => $language, 'selected' => $selected, 'exam_group' => $this->groupKey($selected, $language),
            'letter_grade' => in_array($grade, ['A','B','C','D','E','F'], true) ? $grade : '',
            'result' => in_array($grade, ['A','B','C','D','E'], true) ? 'Passed' : ($grade === 'F' ? 'Failed' : 'Pending'),
            'score_rank' => is_numeric($score) ? number_format((float) $score, 3, '.', '') : '',
            'certificate_number' => $data['g12_lettera_certnumber'] ?? ($certificate?->number ?? ''),
        ];
    }

    private function statistics(Collection $rows, bool $byCampus): array
    {
        $labels = ['science_english' => 'Science - English', 'science_khmer' => 'Science - Khmer', 'social_english' => 'Social Science - English', 'social_khmer' => 'Social Science - Khmer'];
        $sets = $byCampus ? $rows->groupBy('campus') : collect(['All Campuses' => $rows]);
        $output = [];
        foreach ($sets as $campus => $campusRows) {
            foreach ($labels as $key => $label) $output[] = $this->statRow($campusRows->where('exam_group', $key), (string) $campus, $label);
            $output[] = $this->statRow($campusRows, (string) $campus, 'Total', true);
        }
        if ($byCampus) $output[] = $this->statRow($rows, 'All Campuses', 'Grand Total', true);
        return ['rows' => $output];
    }

    private function statRow(Collection $rows, string $campus, string $label, bool $total = false): array
    {
        $count = fn (string $gender, ?array $grades = null) => $rows->filter(fn ($row) => $row['gender_key'] === $gender && ($grades === null || in_array($row['letter_grade'], $grades, true)))->count();
        $registered = ['male' => $count('male'), 'female' => $count('female')];
        $examined = ['male' => $count('male', ['A','B','C','D','E','F']), 'female' => $count('female', ['A','B','C','D','E','F'])];
        $passed = ['male' => $count('male', ['A','B','C','D','E']), 'female' => $count('female', ['A','B','C','D','E'])];
        $grades = [];
        foreach (['A','B','C','D','E','F'] as $grade) $grades[$grade] = ['male' => $count('male', [$grade]), 'female' => $count('female', [$grade])];
        $gradePercentages = [];
        foreach ($grades as $grade => $counts) $gradePercentages[$grade] = [
            'male' => $examined['male'] ? number_format($counts['male'] * 100 / $examined['male'], 2) : '0.00',
            'female' => $examined['female'] ? number_format($counts['female'] * 100 / $examined['female'], 2) : '0.00',
        ];
        return ['campus' => $campus, 'label' => $label, 'total' => $total, 'registered' => $registered, 'examined' => $examined, 'passed' => $passed,
            'pass_percentage' => ['male' => $examined['male'] ? number_format($passed['male'] * 100 / $examined['male'], 2) : '0.00', 'female' => $examined['female'] ? number_format($passed['female'] * 100 / $examined['female'], 2) : '0.00'], 'grades' => $grades, 'grade_percentages' => $gradePercentages];
    }

    private function gender(string $value): array
    {
        $value = mb_strtolower(trim($value));
        if (in_array($value, ['ប', 'ប្រុស', 'male', 'm'], true)) return ['key' => 'male', 'label' => 'ប'];
        if (in_array($value, ['ស', 'ស្រី', 'female', 'f'], true)) return ['key' => 'female', 'label' => 'ស'];
        return ['key' => 'unknown', 'label' => $value];
    }

    private function language(string $value): string { return str_contains(mb_strtolower($value), 'english') || str_contains($value, 'អង់គ្លេស') ? 'English' : 'Khmer'; }
    private function selected(string $value): string { return str_contains(mb_strtolower($value), 'social') || str_contains($value, 'សង្គម') ? 'Social Science' : 'Science'; }
    private function groupKey(string $selected, string $language): string { return ($selected === 'Social Science' ? 'social' : 'science').'_'.strtolower($language); }
    private function certificateNumber(array $row, int $number): string { preg_match('/(\d{4})$/', $row['academic_year'], $match); return '12'.substr($match[1] ?? date('Y'), -2).'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT); }
}
