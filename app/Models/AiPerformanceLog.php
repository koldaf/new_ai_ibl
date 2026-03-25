<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiPerformanceLog extends Model
{
    protected $fillable = [
        'caller',
        'lesson_id',
        'user_id',
        'stage',
        'model_name',
        'question_snippet',
        'response_time_ms',
        'ttft_ms',
        'total_duration_ms',
        'load_duration_ms',
        'prompt_tokens',
        'tokens_generated',
        'tokens_per_second',
        'context_chunks',
        'error',
    ];

    protected $casts = [
        'ttft_ms'           => 'float',
        'total_duration_ms' => 'float',
        'load_duration_ms'  => 'float',
        'tokens_per_second' => 'float',
    ];
}
