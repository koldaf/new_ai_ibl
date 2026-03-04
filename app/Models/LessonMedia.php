<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class LessonMedia extends Model
{
    use HasFactory;

    protected $table = 'lesson_media';

    protected $fillable = ['lesson_id', 'stage', 'media_type', 'file_path', 'file_name', 'title', 'description', 'order'];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Get the full URL to the media file.
     */
    public function getUrlAttribute()
    {
        //return Storage::disk('public')->url($this->file_path);
        return Storage::url($this->file_path);
    }
}