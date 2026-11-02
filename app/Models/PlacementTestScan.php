<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlacementTestScan extends Model
{
    protected $table = 'tb_placement_test_scan';

    protected $guarded = ['id'];

    protected $casts = ['detected' => 'array', 'flags' => 'array', 'confidence' => 'array', 'verified_at' => 'datetime'];

    public function attempt()
    {
        return $this->belongsTo(PlacementTestAttempt::class, 'attempt_id');
    }
}
