<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends Model
{
    use SoftDeletes;

    protected $table = 'chat_messages';
    protected $fillable = ['conversation_id', 'user_id', 'message', 'message_type', 'media_path', 'media_name', 'media_mime', 'media_duration_seconds', 'edited_at', 'reply_to_message_id'];
    protected $casts = ['edited_at' => 'datetime'];

    public function conversation() { return $this->belongsTo(ChatConversation::class, 'conversation_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function reactions() { return $this->hasMany(ChatMessageReaction::class, 'message_id'); }
    public function replyTo() { return $this->belongsTo(self::class, 'reply_to_message_id'); }
    public function hiddenByUsers() { return $this->belongsToMany(User::class, 'chat_message_deletions', 'message_id', 'user_id')->withTimestamps(); }
}
