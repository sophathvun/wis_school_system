<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlacementTestTopic extends Model
{
    protected $table = 'tb_placement_test_topic';

    protected $guarded = ['id'];

    public function subject()
    {
        return $this->belongsTo(PlacementTestSubject::class, 'subject_id');
    }

    public function grades()
    {
        return $this->belongsToMany(Grade::class, 'tb_placement_test_topic_grade', 'topic_id', 'grade_id');
    }
}
