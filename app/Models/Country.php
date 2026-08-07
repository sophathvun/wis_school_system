<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Country extends Model { protected $table = 'tb_country'; protected $fillable = ['country_name_en','country_name_kh','nationality_name_en','nationality_name_kh','country_code','flag_path','status']; protected $casts = ['status'=>'boolean']; public function provinces(){ return $this->hasMany(Province::class); } }
