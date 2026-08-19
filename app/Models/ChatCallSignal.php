<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatCallSignal extends Model
{
    protected $table = 'chat_call_signals';
    protected $fillable = ['call_id', 'user_id', 'signal_type', 'payload'];
    protected $casts = ['payload' => 'array'];

    public function call()
    {
        return $this->belongsTo(ChatCall::class, 'call_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
