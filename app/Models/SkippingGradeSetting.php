<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkippingGradeSetting extends Model
{
    protected $table = 'tb_skipping_grade_settings';
    protected $guarded = ['id'];
    protected $casts = ['committee_names'=>'array', 'request_template'=>'array', 'approval_template'=>'array'];
    public const CAMPUS_COMMITTEE = [0=>'Class Teacher',1=>'KH-Coordinator',2=>'VSP',3=>'School Principal'];
    public const CENTRAL_COMMITTEE = [4=>'IP Director',5=>'Registrar Director',6=>'Academic Directors',7=>'(Vice) President'];
    public const COMMITTEE = self::CAMPUS_COMMITTEE + self::CENTRAL_COMMITTEE;

    public function centralCommitteeNames(): array
    {
        return array_replace(array_fill_keys(array_keys(self::CENTRAL_COMMITTEE),''),array_intersect_key($this->committee_names ?? [],self::CENTRAL_COMMITTEE));
    }

    public function requestCommitteeNames(array $campusNames): array
    {
        return array_replace(array_fill(0,8,''),array_intersect_key($campusNames,self::CAMPUS_COMMITTEE),$this->centralCommitteeNames());
    }
    public static function current(): self { return self::firstOrCreate(['id'=>1]); }
}
