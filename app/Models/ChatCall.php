<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatCall extends Model
{
    use SoftDeletes;

    protected $table = 'chat_calls';
    protected $fillable = ['conversation_id', 'created_by', 'call_type', 'status', 'started_at', 'ended_at', 'meta'];
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'meta' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants()
    {
        return $this->hasMany(ChatCallParticipant::class, 'call_id');
    }

    public function signals()
    {
        return $this->hasMany(ChatCallSignal::class, 'call_id');
    }
}
