<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScholarshipSeatPrintSetting extends Model
{
    protected $table='tb_scholarship_seat_print_setting';
    protected $guarded=['id'];
    protected $casts=['prepared_date'=>'date','checked_date'=>'date','approved_date'=>'date'];
}
