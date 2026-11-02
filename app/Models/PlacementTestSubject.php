<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlacementTestSubject extends Model
{
    protected $table = 'tb_placement_test_subject';

    protected $guarded = ['id'];

    public function grades()
    {
        return $this->belongsToMany(Grade::class, 'tb_placement_test_subject_grade', 'subject_id', 'grade_id');
    }

    public function topics()
    {
        return $this->hasMany(PlacementTestTopic::class, 'subject_id');
    }
}
