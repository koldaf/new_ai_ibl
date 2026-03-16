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
        'classification',
        'confidence',
        'feedback_text',
        'follow_up_question',
        'misconception_source',
        'misconception_id',
        'engage_status',
        'completion_reason',
        'context_source',
        'retrieval_mode',
        'turn_index',
        'parent_message_id',
    ];

    protected $casts = [
        'confidence' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function misconception()
    {
        return $this->belongsTo(LessonMisconception::class, 'misconception_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_message_id');
    }

    public function replies()
    {
        return $this->hasMany(self::class, 'parent_message_id');
    }
    
}
