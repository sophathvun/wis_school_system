<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\Session;
use App\Models\SchoolInfo;
use App\Models\StudentSkippingGrade;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StudentSkippingGradeReport
{
    public function query(array $filters): Builder
    {
        // The request list and printout share the recorded current year and placement filters.
        $scope = StudentSkippingGrade::query()
            ->when($filters['academic_year_id'] ?? null, fn($q,$id)=>$q->where('academic_year_id', $id))
            ->when($filters['campus_id'] ?? null, fn($q,$id)=>$q->where('campus_id', $id))
            ->when($filters['status'] ?? null, fn($q,$status)=>$q->where('status', $status));
        if (!empty($filters['grade_class'])) {
            [$grade,$class,$session] = explode(':', $filters['grade_class']);
            $scope->where('source_grade_id', $grade)->where('source_class_id', $class);
            $session === 'none' ? $scope->whereNull('source_session_id') : $scope->where('source_session_id', $session);
        }
        $scope->when($filters['approval_number'] ?? null, fn($q,$number)=>$q->where('reference_number', $number));
        $term = trim($filters['search'] ?? '');
        if ($term !== '') {
            $scope->where(function ($query) use ($term) {
                foreach (['student_snapshot->student_id','student_snapshot->name_kh','student_snapshot->name_en','reference_number','parent_name','parent_phone'] as $field) {
                    $query->orWhere($field, 'like', '%'.$term.'%');
                }
            });
        }
        return app(SkippingGradeWorkflow::class)->visibleRequests($scope, auth()->user())->orderByDesc('id');
    }

    public function options(array $filters): array
    {
        $scope = $this->query(array_diff_key($filters, array_flip(['grade_class','approval_number','search'])));
        $sessions = Session::withTrashed()->get()->keyBy('id');
        $gradeOptions = (clone $scope)->reorder()->orderBy('source_grade_id')->orderBy('source_class_id')->get()
            ->unique(fn($record)=>$this->gradeKey($record))->map(fn($record)=>[
                'value'=>$this->gradeKey($record),
                'label'=>$this->gradeLabel($record->student_snapshot ?? [], 'source', $sessions->get($record->source_session_id)),
            ])->values();
        $approvalNumbers = $this->query(array_diff_key($filters, array_flip(['approval_number','search'])))
            ->reorder()->whereNotNull('reference_number')->distinct()->orderBy('reference_number')->pluck('reference_number');
        return compact('gradeOptions','approvalNumbers');
    }

    public function rows(Collection $records): Collection
    {
        $sessions = Session::withTrashed()->get()->keyBy('id');
        return $records->mapWithKeys(function ($record) use ($sessions) {
            $snapshot = $record->student_snapshot ?? [];
            return [$record->id=>[
                'source_grade'=>$this->gradeLabel($snapshot, 'source', $sessions->get($record->source_session_id)),
                'target_grade'=>$this->gradeLabel($snapshot, 'target', $sessions->get($record->target_session_id)),
                'dob'=>!empty($snapshot['date_of_birth']) ? Carbon::parse($snapshot['date_of_birth'])->format('d-M-Y') : '—',
            ]];
        });
    }

    public function data(array $filters): array
    {
        $records = $this->query($filters)->with('student')->get();
        $reportRows = $this->rows($records);
        $reportYear = AcademicYear::find($filters['academic_year_id'] ?? null);
        $reportYearPairs = $records->map(fn($record)=>[
            'from'=>$record->student_snapshot['academic_year'] ?? $reportYear?->academic_year ?? '—',
            'to'=>$record->student_snapshot['target_academic_year'] ?? $record->student_snapshot['academic_year'] ?? '—',
        ])->unique(fn($years)=>$years['from'].'|'.$years['to'])->values();
        $statusCounts = $records->countBy('status');
        $reportStatusSummary = collect(['approved'=>'Approved','rejected'=>'Rejected','draft'=>'Draft','pending'=>'Submitted'])
            ->filter(fn($label,$status)=>($statusCounts[$status] ?? 0)>0)
            ->map(fn($label,$status)=>$label.': '.$statusCounts[$status])->implode(' | ');
        return compact('filters','records','reportRows','reportYear','reportYearPairs','reportStatusSummary') + $this->options($filters) + [
            'reportCampus'=>SchoolInfo::find($filters['campus_id'] ?? null),
        ];
    }

    private function gradeKey(StudentSkippingGrade $record): string
    {
        return $record->source_grade_id.':'.$record->source_class_id.':'.($record->source_session_id ?? 'none');
    }

    public function statistics(array $data): array
    {
        // Requests are newest first: retain the latest matching request per student/year.
        $students = $data['records']->unique(fn($record) => $record->academic_year_id.':'.$record->student_id)->values();
        $grades = Grade::withTrashed()->get()->keyBy('id');
        $columns = ['N', 'K1', 'K2', 'K3', ...array_map('strval', range(1, 10))];
        $placements = $students->mapWithKeys(function ($record) use ($grades) {
            $snapshot = $record->student_snapshot ?? [];
            $source = $this->statisticsGrade($snapshot['source_grade'] ?? $grades->get($record->source_grade_id)?->grade_short_name ?? $grades->get($record->source_grade_id)?->grade);
            $target = $this->statisticsGrade($snapshot['target_grade'] ?? $grades->get($record->target_grade_id)?->grade_short_name ?? $grades->get($record->target_grade_id)?->grade);
            return [$record->id => compact('source', 'target')];
        });
        foreach ($placements as $placement) if (!in_array($placement['source'], $columns, true)) $columns[] = $placement['source'];
        $groups = collect($columns)->groupBy(fn($label) => in_array($label, ['N', 'K1', 'K2', 'K3'], true) ? 'early' : (ctype_digit($label) && (int)$label <= 6 ? 'primary' : (ctype_digit($label) ? 'secondary' : 'other')));
        $columns = $groups->flatten()->all();
        $skipTargets = ['N'=>'K2', 'K1'=>'K3', 'K2'=>'G1', 'K3'=>'G2'];
        foreach (range(1, 10) as $grade) $skipTargets[(string)$grade] = 'G'.($grade + 2);
        $headers = collect($columns)->mapWithKeys(fn($column)=>[$column=>[
            'source'=>ctype_digit($column) ? 'G'.$column : $column,
            'target'=>$skipTargets[$column] ?? null,
        ]])->all();
        $empty = array_fill_keys($columns, ['total' => 0, 'female' => 0]);
        $campuses = SchoolInfo::withTrashed()->where(function ($query) use ($students) {
            $query->where('status', 1)->orWhereIn('id', $students->pluck('campus_id'));
        })->when($data['filters']['campus_id'] ?? null, fn($query, $id) => $query->where('id', $id))->orderBy('campus_name_en')->get();
        $rows = $campuses->mapWithKeys(fn($campus) => [$campus->id => ['campus' => $campus->campus_name_en, 'cells' => $empty, 'total' => 0, 'female' => 0]])->all();
        $totals = $empty;
        foreach ($students as $record) {
            $grade = $placements[$record->id]['source'];
            $female = in_array(strtolower(trim($record->student_snapshot['gender'] ?? $record->student?->gender ?? '')), ['female', 'f'], true) ? 1 : 0;
            $rows[$record->campus_id] ??= ['campus' => $record->student_snapshot['campus_en'] ?? 'Unknown campus', 'cells' => $empty, 'total' => 0, 'female' => 0];
            $rows[$record->campus_id]['cells'][$grade]['total']++;
            $rows[$record->campus_id]['cells'][$grade]['female'] += $female;
            $rows[$record->campus_id]['total']++;
            $rows[$record->campus_id]['female'] += $female;
            $totals[$grade]['total']++;
            $totals[$grade]['female'] += $female;
        }
        $groupTotals = $groups->map(fn($labels) => ['total' => $labels->sum(fn($label) => $totals[$label]['total']), 'female' => $labels->sum(fn($label) => $totals[$label]['female']), 'span' => $labels->count() * 2]);
        $notes = $placements->values()->unique(fn($pair) => $pair['source'].'|'.$pair['target'])
            ->reject(fn($pair) => in_array($pair['source'].'|'.$pair['target'], ['2|4', '3|5'], true))
            ->sortBy(fn($pair) => array_search($pair['source'], $columns, true))->values();
        return ['columns' => $columns, 'headers' => $headers, 'groups' => $groups, 'rows' => array_values($rows), 'totals' => $totals, 'groupTotals' => $groupTotals, 'total' => $students->count(), 'female' => array_sum(array_column($rows, 'female')), 'notes' => $notes, 'studentRecords' => $students];
    }

    private function statisticsGrade(?string $grade): string
    {
        $grade = trim($grade ?? '');
        if (preg_match('/^(N|Nursery)$/i', $grade)) return 'N';
        if (preg_match('/^(?:K|Kindergarten\s*)([123])$/i', $grade, $match)) return 'K'.$match[1];
        if (preg_match('/^(?:G(?:rade)?\s*)?(\d+)$/i', $grade, $match)) return (string)(int)$match[1];
        return $grade ?: 'Unknown';
    }

    private function gradeLabel(array $snapshot, string $prefix, ?Session $session): string
    {
        $grade = trim($snapshot[$prefix.'_grade'] ?? '');
        $class = trim($snapshot[$prefix.'_class'] ?? '');
        $label = $class !== '' && str_starts_with(strtolower($class), strtolower(rtrim($grade, '-')))
            ? $class : $grade.ltrim($class, '-');
        $label = preg_replace('/^G(?=\d)/i', '', $label);
        $group = trim($snapshot[$prefix.'_session'] ?? ($session?->session_short_name ?: $session?->session_name ?? ''));
        if ($group !== '' && !str_ends_with(strtolower($label), '-'.strtolower($group))) $label .= '-'.$group;
        return $label ?: '—';
    }
}
