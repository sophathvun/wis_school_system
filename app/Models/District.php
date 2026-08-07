<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class District extends Model { protected $table = 'tb_district'; protected $fillable = ['province_id','district_code','district_name_en','district_name_kh','status']; protected $casts = ['status'=>'boolean']; public function province(){ return $this->belongsTo(Province::class); } public function communes(){ return $this->hasMany(Commune::class); } }
