<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GepAcademicYear extends Model
{
    protected $table='tb_gep_academic_year';
    protected $hidden=['lifecycle_status','started_slot'];
    protected $casts=['status'=>'boolean'];
    protected $fillable=['academic_year','status'];
}
