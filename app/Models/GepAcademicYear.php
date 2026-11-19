<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GepAcademicYear extends Model
{
    protected $table='tb_gep_academic_year';
    protected $fillable=['academic_year','lifecycle_status'];
}
