<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebauthnCredential extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'credential_id_hash',
        'credential_id',
        'credential_record',
        'last_used_at',
    ];

    protected $hidden = [
        'credential_id',
        'credential_id_hash',
        'credential_record',
    ];

    protected function casts(): array
    {
        return [
            'credential_record' => 'encrypted',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
