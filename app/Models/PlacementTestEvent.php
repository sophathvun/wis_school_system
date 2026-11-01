<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PlacementTestEvent extends Model {
    public $timestamps=false;protected $table='tb_placement_test_event';protected $guarded=['id'];protected $casts=['details'=>'array','created_at'=>'datetime'];
    public function user(){return $this->belongsTo(User::class,'user_id');}
    public function applicant(){return $this->belongsTo(PlacementTestApplicant::class,'applicant_id');}
}
