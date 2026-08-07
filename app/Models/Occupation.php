<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Occupation extends Model
{
    use SoftDeletes;
    protected $table = 'tb_occupation';
    protected $fillable = ['occupation_name_en', 'occupation_name_kh', 'status'];

    public function familyMembers() { return $this->hasMany(FamilyMember::class, 'occupation_id'); }
}
