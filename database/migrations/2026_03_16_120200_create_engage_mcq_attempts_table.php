<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engage_mcq_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
            $table->foreignId('engage_mcq_question_id')->constrained('engage_mcq_questions')->onDelete('cascade');
            $table->enum('selected_option', ['a', 'b', 'c', 'd']);
            $table->boolean('is_correct');
            $table->text('resolved_feedback')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id', 'engage_mcq_question_id'], 'engage_mcq_attempts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engage_mcq_attempts');
    }
};