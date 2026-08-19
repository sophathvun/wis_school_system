<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatCallParticipant extends Model
{
    protected $table = 'chat_call_participants';
    protected $fillable = ['call_id', 'user_id', 'role', 'joined_at', 'left_at', 'last_seen_at'];
    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function call()
    {
        return $this->belongsTo(ChatCall::class, 'call_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
