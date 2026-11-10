<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class G12Certificate extends Model
{
    protected $table = 'tb_g12_certificate';
    protected $fillable = ['certificate_year_id', 'student_id', 'enrollment_id', 'sequence', 'certificate_number', 'status', 'printed_at', 'released_at', 'voided_at', 'voided_by', 'void_reason'];
    protected $casts = ['printed_at'=>'datetime','released_at'=>'datetime','voided_at'=>'datetime'];

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
