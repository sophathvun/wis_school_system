<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatConversation extends Model
{
    use SoftDeletes;

    protected $table = 'chat_conversations';
    protected $fillable = ['type', 'title', 'photo_path', 'created_by'];

    public function users() { return $this->belongsToMany(User::class, 'chat_conversation_users', 'conversation_id', 'user_id')->withPivot(['last_read_at', 'role'])->withTimestamps(); }
    public function messages() { return $this->hasMany(ChatMessage::class, 'conversation_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function calls() { return $this->hasMany(ChatCall::class, 'conversation_id'); }
}
