<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('misconception_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
            $table->enum('stage', ['engage', 'explore', 'explain', 'elaborate', 'evaluate'])->default('engage');
            $table->foreignId('misconception_id')->nullable()->constrained('lesson_misconceptions')->nullOnDelete();
            $table->enum('source', ['template', 'ai_candidate', 'none'])->default('none');
            $table->text('student_answer');
            $table->text('evidence_span')->nullable();
            $table->decimal('confidence', 4, 2)->nullable();
            $table->enum('status', ['captured', 'queued_for_review'])->default('captured');
            $table->timestamps();

            $table->index(['lesson_id', 'stage', 'source']);
            $table->index(['user_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('misconception_events');
    }
};
