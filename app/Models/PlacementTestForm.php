<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PlacementTestForm extends Model {
    protected $table='tb_placement_test_form';protected $guarded=['id'];
    protected $casts=['subject_rules'=>'array','blueprint'=>'array','approved_at'=>'datetime','published_at'=>'datetime','max_score'=>'decimal:2','pass_score'=>'decimal:2'];
    public function grade(){return $this->belongsTo(Grade::class);}
    public function questions(){return $this->belongsToMany(PlacementTestQuestion::class,'tb_placement_test_form_question','form_id','question_id')->withPivot(['order_no','subject','points'])->withTimestamps()->orderByPivot('order_no');}
    public function attempts(){return $this->hasMany(PlacementTestAttempt::class,'form_id');}
}
