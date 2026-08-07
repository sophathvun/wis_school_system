<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Commune extends Model { protected $table = 'tb_commune'; protected $fillable = ['district_id','commune_code','commune_name_en','commune_name_kh','status']; protected $casts = ['status'=>'boolean']; public function district(){ return $this->belongsTo(District::class); } public function villages(){ return $this->hasMany(Village::class); } }
