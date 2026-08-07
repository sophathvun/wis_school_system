<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Province extends Model { protected $table = 'tb_province'; protected $fillable = ['country_id','province_code','province_name_en','province_name_kh','status']; protected $casts = ['status'=>'boolean']; public function country(){ return $this->belongsTo(Country::class); } public function districts(){ return $this->hasMany(District::class); } }
