<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class K3Certificate extends Model
{
    protected $table = 'tb_k3_certificate';
    protected $fillable = ['certificate_year_id', 'student_id', 'enrollment_id', 'sequence', 'certificate_number'];
}
