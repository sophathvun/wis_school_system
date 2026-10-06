<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class G9CertificateYear extends Model
{
    protected $table = 'tb_g9_certificate_year';
    protected $fillable = ['academic_year_id', 'given_date', 'number_prefix', 'typography'];
    protected $casts = ['given_date' => 'date', 'typography' => 'array'];

    public function certificates() { return $this->hasMany(G9Certificate::class, 'certificate_year_id'); }
}
