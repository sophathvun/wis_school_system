<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PlacementTestAnswer extends Model {
    protected $table='tb_placement_test_answer';protected $guarded=['id'];
    protected $casts=['question_snapshot'=>'array','is_correct'=>'boolean','awarded_points'=>'decimal:2'];
    public function attempt(){return $this->belongsTo(PlacementTestAttempt::class,'attempt_id');}
}
