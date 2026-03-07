<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiChatMessage extends Model
{
    //
    protected $table = 'ai_chat_message';
    protected $fillable = [
        'user_id',
        'lesson_id',
        'stage',
        'question',
        'answer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
    
}
