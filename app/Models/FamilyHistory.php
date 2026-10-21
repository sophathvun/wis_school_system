<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FamilyHistory extends Model
{
    protected $table = 'tb_family_history';
    protected $guarded = ['id'];
    protected $casts = ['before_values' => 'array', 'after_values' => 'array'];
}
