<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_performance_logs', function (Blueprint $table) {
            $table->id();
            // Which service triggered this call
            $table->string('caller', 50);  // rag_query | engage_decision | general_classify | stream_query
            // Nullable context — no FK constraints so logs survive lesson/user deletion
            $table->unsignedBigInteger('lesson_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('stage', 20)->nullable();
            // Model in use
            $table->string('model_name', 100);
            // Truncated question for reference (no PII concern — same data already stored in ai_chat_messages)
            $table->string('question_snippet', 255)->nullable();
            // Timing metrics (wall clock)
            $table->unsignedInteger('response_time_ms');     // total wall-clock latency
            // Ollama internal timing (derived from Ollama response metadata)
            $table->decimal('ttft_ms', 10, 2)->nullable();          // prompt_eval_duration / 1e6
            $table->decimal('total_duration_ms', 10, 2)->nullable(); // total_duration / 1e6
            $table->decimal('load_duration_ms', 10, 2)->nullable();  // load_duration / 1e6
            // Token metrics
            $table->unsignedInteger('prompt_tokens')->nullable();    // prompt_eval_count
            $table->unsignedInteger('tokens_generated')->nullable(); // eval_count
            $table->decimal('tokens_per_second', 8, 2)->nullable();  // eval_count / (eval_duration / 1e9)
            // Retrieval context
            $table->unsignedTinyInteger('context_chunks')->nullable();
            // Error capture (null = success)
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_performance_logs');
    }
};
