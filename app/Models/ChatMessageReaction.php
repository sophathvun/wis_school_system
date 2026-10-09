<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessageReaction extends Model
{
    public const EMOJIS = ["\u{2764}\u{FE0F}", "\u{1F64F}", "\u{1F44D}", "\u{1F602}", "\u{1F62E}", "\u{1F622}"];

    protected $fillable = ['message_id', 'user_id', 'emoji'];

    public function message() { return $this->belongsTo(ChatMessage::class); }
    public function user() { return $this->belongsTo(User::class); }
}
