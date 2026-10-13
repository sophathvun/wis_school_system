<?php

namespace App\Services;

use App\Models\Staff;
use App\Models\User;
use App\Support\StaffProfile;
use Illuminate\Validation\ValidationException;

class StaffWorkInformation
{
    public function schedule(array $data): array
    {
        $defaults = array_intersect_key($data,array_flip(['session_1_in','session_1_out','session_2_in','session_2_out']));
        $this->checkSessions($defaults,'');
        $result = [];
        foreach (StaffProfile::DAYS as $day=>$label) {
            $row = $data['work_schedule'][$day] ?? [];
            $period = $row['period'] ?? 'off';
            if ($period === 'off') continue;
            $values = ['period'=>$period];
            $overrides = ['period'=>$period];
            foreach ([1,2] as $session) {
                $active = $period === 'full_day' || ($session===1 && $period==='morning') || ($session===2 && $period==='afternoon');
                foreach (['in','out'] as $direction) {
                    $key = 'session_'.$session.'_'.$direction;
                    $values[$key] = $active ? ($row[$key] ?? $defaults[$key] ?? null) : null;
                    $overrides[$key] = $active ? ($row[$key] ?? null) : null;
                }
            }
            $this->checkSessions($values,'work_schedule.'.$day.'.');
            $result[$day] = $overrides;
        }
        return $result;
    }

    private function checkSessions(array $times, string $prefix): void
    {
        foreach ([1,2] as $session) {
            $in = $times['session_'.$session.'_in'] ?? null;
            $out = $times['session_'.$session.'_out'] ?? null;
            if ((bool)$in !== (bool)$out) throw ValidationException::withMessages([$prefix.'session_'.$session.'_out'=>'Enter both Time In and Time Out for the session.']);
            if ($in && $out <= $in) throw ValidationException::withMessages([$prefix.'session_'.$session.'_out'=>'Time Out must be after Time In.']);
        }
        if (!empty($times['session_1_out']) && !empty($times['session_2_in']) && $times['session_2_in'] < $times['session_1_out']) {
            throw ValidationException::withMessages([$prefix.'session_2_in'=>'Session 2 must start after Session 1 ends.']);
        }
    }

    public function snapshot(Staff $staff): array
    {
        $staff->load(['position','department','campuses','primaryCampus','supervisor']);
        return [
            'position_id'=>$staff->position_id,'position'=>$staff->position?->name,
            'department_id'=>$staff->department_id,'department'=>$staff->department?->name,
            'primary_campus_id'=>$staff->primary_campus_id,'primary_campus'=>$staff->primaryCampus?->campus_name_en,
            'campuses'=>$staff->campuses->sortBy('id')->map(fn($campus)=>['id'=>$campus->id,'name'=>$campus->campus_name_en])->values()->all(),
            'staff_category'=>$staff->staff_category,'employment_type'=>$staff->employment_type,'employment_status'=>$staff->employment_status,
            'joined_on'=>$staff->joined_on?->format('Y-m-d'),'ended_on'=>$staff->ended_on?->format('Y-m-d'),
            'employment_end_reason'=>$staff->employment_end_reason,'status'=>(bool)$staff->status,
            'contract_type'=>$staff->contract_type, 'contract_number'=>$staff->contract_number,
            'contract_started_on'=>$staff->contract_started_on?->format('Y-m-d'), 'contract_ends_on'=>$staff->contract_ends_on?->format('Y-m-d'),
            'probation_ends_on'=>$staff->probation_ends_on?->format('Y-m-d'),
            'reports_to_staff_id'=>$staff->reports_to_staff_id, 'supervisor'=>$staff->supervisor?->name_en,
            'session_1_in'=>$staff->session_1_in,'session_1_out'=>$staff->session_1_out,'session_2_in'=>$staff->session_2_in,'session_2_out'=>$staff->session_2_out,
            'work_schedule'=>$staff->effective_work_schedule,
        ];
    }

    public function record(Staff $staff, ?array $before, User $actor, array $data): void
    {
        $after = $this->snapshot($staff->fresh());
        $base = ['changed_by'=>$actor->id,'changed_by_name'=>$actor->name];
        if ($before && !$staff->workHistories()->exists()) {
            $staff->workHistories()->create($base+['change_type'=>'baseline','effective_on'=>now()->toDateString(),
                'reason'=>'Existing work information captured before this update.','before_snapshot'=>null,'after_snapshot'=>$before]);
        }
        if ($before === $after) return;
        $type = $data['work_change_type'] ?? 'work_update';
        if (!$before) $type = 'hired';
        elseif ($type==='work_update') {
            if ($before['employment_status'] !== $after['employment_status']) $type = 'employment_status';
            elseif ($before['position_id'] !== $after['position_id']) $type = 'position_transfer';
            elseif ($before['campuses'] !== $after['campuses'] || $before['primary_campus_id'] !== $after['primary_campus_id']) $type = 'campus_transfer';
        }
        $staff->workHistories()->create($base+['change_type'=>$type,
            'effective_on'=>$data['work_effective_on'] ?? (!$before ? ($after['joined_on'] ?? now()->toDateString()) : now()->toDateString()),
            'reason'=>$data['work_change_reason'] ?? null,'before_snapshot'=>$before,'after_snapshot'=>$after]);
    }
}
