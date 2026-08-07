<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Nationality extends Model
{
    use SoftDeletes;

    protected $table = 'tb_nationality';
    protected $fillable = ['country_id', 'nationality_name_en', 'nationality_name_kh', 'status'];

    public function country() { return $this->belongsTo(Country::class); }
    public function familyMembers() { return $this->hasMany(FamilyMember::class); }
}
