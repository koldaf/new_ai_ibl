<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class LessonCheckpointCorpus extends Model
{
    use HasFactory;

    protected $table = 'lesson_checkpoint_corpora';

    protected $fillable = [
        'lesson_id',
        'stage',
        'title',
        'description',
        'file_path',
        'file_name',
        'file_type',
        'processing_status',
        'vector_store_path',
        'error_message',
        'sort_order',
        'created_by',
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }

    public function isReady(): bool
    {
        return $this->processing_status === 'completed' && !empty($this->vector_store_path);
    }
}