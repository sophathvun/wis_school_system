<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentIdCardTemplate extends Model
{
    protected $table = 'tb_student_id_card_template';
    protected $fillable = ['layout', 'version'];
    protected $casts = ['layout' => 'array', 'version' => 'integer'];
}
