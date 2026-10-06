<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class G12CertificateTemplate extends Model
{
    protected $table = 'tb_g12_certificate_template';
    protected $fillable = ['layout','version'];
    protected $casts = ['layout'=>'array','version'=>'integer'];
}
