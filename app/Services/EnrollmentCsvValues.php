<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Resolve enrollment CSV values without creating or changing school configuration. */
class EnrollmentCsvValues
{
    private array $catalogs = [];

    public function resolve(array $data): array
    {
        $values = array_intersect_key($data, array_flip(['academic_year_id','campus_id','grade_id','class_id','session_id','group_id','student_type','enrollment_status','enrolled_on','ended_on','exit_reason','notes','status']));
        $values = array_map(fn ($v) => is_string($v) && trim($v) === '' ? null : $v, $values);
        $year = $this->reference($data['academic_year_id'] ?? null, 'tb_academic_year', ['academic_year','academic_year_code'], 'Academic Year', true);
        $campus = $this->reference($data['campus_id'] ?? null, 'tb_school_info', ['campus_name_en','campus_name_kh','school_name_en','school_name_kh'], 'Campus', true);
        $grade = $this->reference($data['grade_id'] ?? null, 'tb_grade', ['grade','grade_short_name'], 'Grade', true);
        if (array_key_exists('academic_track_id', $data)) {
            $values['academic_track_id'] = $this->reference($data['academic_track_id'], 'tb_academic_track', ['code','name_en','name_kh'], 'Academic Track for the selected Grade', false, ['grade_id'=>$grade->id])?->id;
        }
        $session = $this->reference($data['session_id'] ?? null, 'tb_session', ['session_name','session_short_name'], 'Session');
        $class = $this->classRecord($data['class_id'] ?? null, $year->id, $grade, $session?->id);
        $group = $this->groupRecord($data['group_id'] ?? null, $class->id, $session);
        foreach (['academic_year_id'=>$year->id,'campus_id'=>$campus->id,'grade_id'=>$grade->id,'class_id'=>$class->id,'session_id'=>$session?->id,'group_id'=>$group?->id] as $field=>$id) $values[$field]=$id;
        foreach (['enrolled_on','ended_on'] as $field) if (array_key_exists($field, $values)) $values[$field]=$this->date($values[$field], $field);
        if (isset($values['student_type'])) $values['student_type']=Str::lower(trim($values['student_type']));
        $values['enrollment_status']=Str::lower(trim((string) ($values['enrollment_status'] ?? ''))) ?: 'active';
        $values['status']=isset($values['status']) ? (int) in_array(Str::lower(trim((string) $values['status'])), ['1','true','yes','active'], true) : 1;
        return $values;
    }

    private function records(string $table): array
    {
        return $this->catalogs[$table] ??= DB::table($table)->whereNull('deleted_at')->get()->all();
    }

    private function reference($value, string $table, array $columns, string $label, bool $required=false, array $scope=[]): ?object
    {
        $value=trim((string) $value);
        if ($value === '') {
            if ($required) throw new \RuntimeException("{$label} is required.");
            return null;
        }
        $matches=array_filter($this->records($table), function ($row) use ($value,$columns,$scope) {
            foreach ($scope as $field=>$expected) if (($row->{$field} ?? null)!==null && (int) $row->{$field}!==(int) $expected) return false;
            if (ctype_digit($value)) return (int) $row->id === (int) $value;
            foreach ($columns as $column) if (isset($row->{$column}) && Str::lower(trim((string) $row->{$column})) === Str::lower($value)) return true;
            return false;
        });
        if (!$matches) throw new \RuntimeException("{$label} '{$value}' was not found. Use its configured name or ID.");
        if (count($matches)>1) throw new \RuntimeException("{$label} '{$value}' matches multiple records. Use its ID.");
        return reset($matches);
    }

    private function classRecord($value, int $year, object $grade, ?int $session): object
    {
        $value=trim((string) $value);
        if ($value==='') throw new \RuntimeException('Class is required.');
        $names=[Str::upper($value)];
        if (!ctype_digit($value) && preg_match('/^(K[1-3]|N|[1-9][0-9]*)[\s-]*([A-Z]+)$/i', $value, $parts)) {
            $prefix=Str::upper($parts[1]);
            $gradeNames=[Str::upper(trim((string) ($grade->grade_short_name ?? ''))), Str::upper(preg_replace('/^Grade\s*/i','',trim((string) $grade->grade)))];
            if (Str::lower(trim($grade->grade))==='nursery') $gradeNames[]='N';
            if (!in_array($prefix,$gradeNames,true)) throw new \RuntimeException("Class '{$value}' does not match the selected grade '{$grade->grade}'.");
            $names[]=Str::upper($parts[2]);
        }
        $matches=[];
        foreach ($this->records('tb_class') as $row) {
            if (ctype_digit($value) ? (int) $row->id !== (int) $value : !in_array(Str::upper(trim($row->class_name)), $names, true)) continue;
            $compatible=true;$specificity=0;
            foreach (['academic_year_id'=>$year,'grade_id'=>$grade->id,'session_id'=>$session] as $field=>$expected) {
                $configured=$row->{$field} ?? null;
                if ($configured!==null && $expected!==null && (int) $configured!==(int) $expected) $compatible=false;
                if ($configured!==null) $specificity++;
            }
            if ($compatible) $matches[]=['record'=>$row,'rank'=>$specificity*2+(int) (Str::upper(trim($row->class_name))===$names[0])];
        }
        if (!$matches) throw new \RuntimeException("Class '{$value}' was not found for the selected academic year, grade, and session. Use a configured class letter (A), grade/class label (10A or K1-A), or compatible class ID.");
        $best=max(array_column($matches,'rank'));
        $matches=array_values(array_filter($matches,fn($match)=>$match['rank']===$best));
        if (count($matches)!==1) throw new \RuntimeException("Class '{$value}' is ambiguous for the selected placement. Use the correct class ID.");
        return $matches[0]['record'];
    }

    private function groupRecord($value, int $class, ?object $session): ?object
    {
        $value=trim((string) $value);
        if ($value==='') return null;
        $named=array_filter($this->records('tb_group'),fn($row)=>ctype_digit($value) ? (int) $row->id===(int) $value : Str::lower(trim($row->group_name))===Str::lower($value));
        $matches=array_filter($named,fn($row)=>($row->class_id ?? null)===null || (int) $row->class_id===$class);
        if (count($matches)===1) return reset($matches);
        if (count($matches)>1) throw new \RuntimeException("Group '{$value}' is ambiguous. Use its ID.");
        // Older templates repeated the Session in Group. It is redundant, not a new group.
        if (!$named && $session && !ctype_digit($value)) {
            foreach (['session_name','session_short_name'] as $field) if (Str::lower($value)===Str::lower(trim((string) ($session->{$field} ?? '')))) return null;
        }
        throw new \RuntimeException("Group '{$value}' was not found for the selected class. Group is optional; leave it blank if no separate group is configured.");
    }

    private function date($value, string $field): ?string
    {
        $value=trim((string) $value);
        if ($value==='') return null;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$value,$p)) [$year,$month,$day]=[(int)$p[1],(int)$p[2],(int)$p[3]];
        elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/',$value,$p)) [$year,$month,$day]=[(int)$p[3],(int)$p[1],(int)$p[2]];
        else throw new \RuntimeException("{$field} '{$value}' is invalid. Use YYYY-MM-DD or M/D/YYYY.");
        if (!checkdate($month,$day,$year)) throw new \RuntimeException("{$field} '{$value}' is not a valid date.");
        return sprintf('%04d-%02d-%02d',$year,$month,$day);
    }
}
