<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class K3CertificateTemplate extends Model
{
    protected $table = 'tb_k3_certificate_template';
    protected $fillable = ['layout','version'];
    protected $casts = ['layout'=>'array','version'=>'integer'];
}
