<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends Model
{
    use SoftDeletes;

    protected $table = 'chat_messages';
    protected $fillable = ['conversation_id', 'user_id', 'message', 'message_type', 'media_path', 'media_name', 'media_mime', 'media_duration_seconds', 'edited_at'];
    protected $casts = ['edited_at' => 'datetime'];

    public function conversation() { return $this->belongsTo(ChatConversation::class, 'conversation_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
