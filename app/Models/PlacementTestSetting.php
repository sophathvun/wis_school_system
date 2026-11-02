<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlacementTestSetting extends Model
{
    protected $table = 'tb_placement_test_setting';

    protected $guarded = ['id'];

    protected $casts = [
        'online_enabled' => 'boolean',
        'paper_enabled' => 'boolean',
        'manual_enabled' => 'boolean',
        'scan_enabled' => 'boolean',
        'show_result_to_candidate' => 'boolean',
        'enrollment_handoff_enabled' => 'boolean',
        'default_max_score' => 'decimal:2',
        'default_pass_percentage' => 'decimal:2',
    ];

    public static function defaults(?int $campusId = null): array
    {
        return [
            'campus_id' => $campusId,
            'online_enabled' => true,
            'paper_enabled' => true,
            'manual_enabled' => true,
            'scan_enabled' => true,
            'show_result_to_candidate' => true,
            'enrollment_handoff_enabled' => true,
            'max_attempts' => 3,
            'default_duration_minutes' => 60,
            'default_max_score' => '100.00',
            'default_pass_percentage' => '60.00',
            'online_grace_minutes' => 2,
            'candidate_instructions' => null,
        ];
    }

    public static function effective(?int $campusId): array
    {
        if (! $campusId) {
            return static::defaults();
        }

        return array_replace(static::defaults($campusId), static::where('campus_id', $campusId)->first()?->toArray() ?? []);
    }

    public function campus()
    {
        return $this->belongsTo(SchoolInfo::class, 'campus_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
