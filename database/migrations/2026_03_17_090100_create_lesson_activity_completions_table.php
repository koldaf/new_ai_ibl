<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_activity_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->enum('stage', ['engage', 'explore', 'explain', 'elaborate', 'evaluate']);
            $table->enum('activity_type', ['stage_content', 'media']);
            $table->unsignedBigInteger('activity_reference_id');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique([
                'user_id',
                'lesson_id',
                'stage',
                'activity_type',
                'activity_reference_id',
            ], 'lesson_activity_completions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_activity_completions');
    }
};