<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class G9CertificateTemplate extends Model
{
    protected $table = 'tb_g9_certificate_template';
    protected $fillable = ['layout','version'];
    protected $casts = ['layout'=>'array','version'=>'integer'];
}
