<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class G9Certificate extends Model
{
    use \App\Models\Concerns\PreservesIssuedWisCertificate;
    protected $table = 'tb_g9_certificate';
    protected $casts = ['historical_import'=>'array'];
    protected $fillable = ['certificate_year_id', 'student_id', 'enrollment_id', 'sequence', 'certificate_number'];

    protected static function booted(): void
    {
        static::creating(function (self $certificate) {
            do {
                $token = Str::random(40);
            } while (static::where('qr_token', $token)->exists());
            $certificate->qr_token = $token;
        });
    }
}
