<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class K3CertificateYear extends Model
{
    protected $table = 'tb_k3_certificate_year';
    protected $fillable = ['academic_year_id', 'given_date', 'number_prefix', 'typography'];
    protected $casts = ['given_date' => 'date', 'typography' => 'array'];

    public function certificates() { return $this->hasMany(K3Certificate::class, 'certificate_year_id'); }
}
