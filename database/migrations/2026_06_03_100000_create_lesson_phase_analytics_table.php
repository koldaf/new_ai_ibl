<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_phase_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->enum('stage', ['engage', 'explore', 'explain', 'elaborate', 'evaluate']);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->unsignedInteger('questions_generated')->default(0);
            $table->unsignedInteger('evidence_sources_consulted')->default(0);
            $table->text('reflection_text')->nullable();
            $table->unsignedTinyInteger('reflection_quality_auto')->nullable();
            $table->unsignedTinyInteger('reflection_quality_teacher')->nullable();
            $table->unsignedTinyInteger('reflection_quality_final')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id', 'stage'], 'lesson_phase_analytics_user_lesson_stage_unique');
            $table->index(['lesson_id', 'stage']);
            $table->index(['user_id', 'lesson_id']);
            $table->index(['lesson_id', 'user_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_phase_analytics');
    }
};
