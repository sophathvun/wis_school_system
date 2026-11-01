<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PlacementTestQuestion extends Model {
    protected $table='tb_placement_test_question';protected $guarded=['id'];protected $casts=['options'=>'array','approved_at'=>'datetime','default_points'=>'decimal:2'];
    public function grade(){return $this->belongsTo(Grade::class);}
    public function forms(){return $this->belongsToMany(PlacementTestForm::class,'tb_placement_test_form_question','question_id','form_id')->withPivot(['order_no','subject','points'])->withTimestamps();}
}
